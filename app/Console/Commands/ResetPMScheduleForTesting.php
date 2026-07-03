<?php

namespace App\Console\Commands;

use App\Models\PMSchedule;
use App\Models\Request as RequestModel;
use App\Models\PreventiveMaintenance;
use App\Models\PMScheduleHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetPMScheduleForTesting extends Command
{
    protected $signature = 'pm:reset-for-testing
        {id? : The PM schedule ID (omit to list all)}
        {--clean : Also delete all PM requests and history tied to this schedule (full clean slate)}
        {--complete : Mark all Scheduled/In Progress requests of this schedule as Completed}';

    protected $description = '[TESTING ONLY] Reset a PM schedule so the cron can trigger a new cycle immediately';

    public function handle(): int
    {
        $id = $this->argument('id');

        if (!$id) {
            $this->showAllSchedules();
            return Command::SUCCESS;
        }

        $schedule = PMSchedule::find($id);

        if (!$schedule) {
            $this->error("PM Schedule with ID {$id} not found.");
            return Command::FAILURE;
        }

        // --complete: mark all requests as Completed
        if ($this->option('complete')) {
            return $this->completeAllRequests($schedule);
        }

        // Count related records
        $requestCount = RequestModel::where('pm_schedule_id', $schedule->id)->count();
        $historyCount = PMScheduleHistory::where('pm_schedule_id', $schedule->id)->count();

        $this->info("--- Current State ---");
        $this->table(
            ['Field', 'Value'],
            [
                ['ID', $schedule->id],
                ['Name', $schedule->schedule_name],
                ['Is Active', $schedule->is_active ? 'Yes' : 'No'],
                ['Current Focus Division', $schedule->current_focus_division ?? 'null (no active cycle)'],
                ['Next Scheduled Date', $schedule->next_scheduled_date?->toDateString() ?? 'null'],
                ['Last Generated Date', $schedule->last_generated_date?->toDateString() ?? 'null'],
                ['Frequency', $schedule->frequency],
                ['Linked PM Requests', $requestCount],
                ['Generation History Logs', $historyCount],
            ]
        );

        $clean = $this->option('clean');

        if ($clean) {
            $this->warn("--clean flag detected. This will DELETE all {$requestCount} PM request(s) and {$historyCount} history log(s) for this schedule.");
            if (!$this->confirm('Are you sure you want to delete all linked PM data? This cannot be undone.')) {
                $this->info('Aborted.');
                return Command::SUCCESS;
            }
        } else {
            if (!$this->confirm('Reset this schedule so the cron triggers a new cycle today?')) {
                $this->info('Aborted.');
                return Command::SUCCESS;
            }
        }

        DB::transaction(function () use ($schedule, $clean) {
            if ($clean) {
                // Collect all request numbers (including soft-deleted) for this schedule
                $requestNumbers = RequestModel::withTrashed()
                    ->where('pm_schedule_id', $schedule->id)
                    ->pluck('request_number')
                    ->toArray();

                // Hard-delete all preventive_maintenance records by form_no (direct DB to bypass SoftDeletes)
                if (!empty($requestNumbers)) {
                    $pmDeleted = DB::table('preventive_maintenance')
                        ->whereIn('form_no', $requestNumbers)
                        ->delete();
                    $this->info("  Deleted {$pmDeleted} PreventiveMaintenance record(s).");
                }

                // Also delete by detail_id for any remaining linked records
                $detailIds = RequestModel::withTrashed()
                    ->where('pm_schedule_id', $schedule->id)
                    ->whereNotNull('detail_id')
                    ->pluck('detail_id')
                    ->toArray();
                if (!empty($detailIds)) {
                    DB::table('preventive_maintenance')->whereIn('id', $detailIds)->delete();
                }

                // Force delete requests (hard delete, bypasses SoftDeletes)
                $deleted = RequestModel::withTrashed()->where('pm_schedule_id', $schedule->id)->forceDelete();
                $this->info("  Deleted {$deleted} PM request(s).");

                $deletedHistory = PMScheduleHistory::where('pm_schedule_id', $schedule->id)->delete();
                $this->info("  Deleted {$deletedHistory} history log(s).");
            }

            $schedule->update([
                'current_focus_division' => null,
                'next_scheduled_date'    => now()->toDateString(),
                'last_generated_date'    => null,
            ]);
        });

        $this->newLine();
        $this->info("--- After Reset ---");
        $this->table(
            ['Field', 'Value'],
            [
                ['current_focus_division', 'null ✓'],
                ['next_scheduled_date', now()->toDateString() . ' (today) ✓'],
                ['last_generated_date', 'null ✓'],
            ]
        );

        $this->newLine();
        $this->info('Schedule is ready. Run the cron to trigger Cycle 1:');
        $this->line('  php artisan pm:generate-scheduled');
        $this->newLine();
        $this->info('After Cycle 1 generates, simulate completion then reset for Cycle 2:');
        $this->line('  php artisan pm:reset-for-testing ' . $schedule->id . ' --complete');
        $this->line('  php artisan pm:reset-for-testing ' . $schedule->id);
        $this->line('  php artisan pm:generate-scheduled');

        return Command::SUCCESS;
    }

    private function completeAllRequests(PMSchedule $schedule): int
    {
        $requests = RequestModel::where('pm_schedule_id', $schedule->id)
            ->whereIn('status', ['Scheduled', 'In Progress', 'scheduled', 'in_progress'])
            ->get();

        if ($requests->isEmpty()) {
            $this->warn('No Scheduled/In Progress requests found for this schedule.');
            return Command::SUCCESS;
        }

        $this->info("Found {$requests->count()} request(s) to mark as Completed:");
        $this->table(
            ['Request Number', 'User ID', 'Status'],
            $requests->map(fn($r) => [$r->request_number, $r->user_id, $r->status])->toArray()
        );

        if (!$this->confirm('Mark all as Completed?')) {
            $this->info('Aborted.');
            return Command::SUCCESS;
        }

        $updated = RequestModel::where('pm_schedule_id', $schedule->id)
            ->whereIn('status', ['Scheduled', 'In Progress', 'scheduled', 'in_progress'])
            ->update(['status' => 'Completed']);

        $this->info("Marked {$updated} request(s) as Completed.");
        $this->newLine();
        $this->info('Now reset the schedule for Cycle 2:');
        $this->line('  php artisan pm:reset-for-testing ' . $schedule->id);
        $this->line('  php artisan pm:generate-scheduled');

        return Command::SUCCESS;
    }

    private function showAllSchedules(): void
    {
        $schedules = PMSchedule::active()->get();

        if ($schedules->isEmpty()) {
            $this->warn('No active PM schedules found.');
            return;
        }

        $this->info('Active PM Schedules:');
        $this->table(
            ['ID', 'Name', 'Frequency', 'Focus Division', 'Next Scheduled Date'],
            $schedules->map(fn($s) => [
                $s->id,
                $s->schedule_name,
                $s->frequency,
                $s->current_focus_division ?? '(none)',
                $s->next_scheduled_date?->toDateString() ?? 'null',
            ])->toArray()
        );

        $this->newLine();
        $this->line('Usage:');
        $this->line('  php artisan pm:reset-for-testing <id>             # reset dates only');
        $this->line('  php artisan pm:reset-for-testing <id> --clean     # delete all PM data + reset');
        $this->line('  php artisan pm:reset-for-testing <id> --complete  # mark all requests as Completed');
    }
}
