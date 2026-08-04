<?php

namespace App\Console\Commands;

use App\Models\PMSchedule;
use App\Models\PMDivisionSchedule;
use App\Models\PMGenerationSchedule;
use App\Models\AuditLog;
use App\Services\GeneratePMScheduleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateScheduledPM extends Command
{
    protected $signature = 'pm:generate-scheduled';
    protected $description = 'Generate PM requests for active schedules. Checks per-division next_scheduled_at dates.';

    public function handle(GeneratePMScheduleService $service): int
    {
        $allActive = PMSchedule::active()->get();

        // Debug: show all active schedules
        $this->info('Debug - Total active schedules: ' . $allActive->count());
        foreach ($allActive as $sched) {
            $this->info('  ID:' . $sched->id . ' ' . $sched->schedule_name . ' Focus:' . ($sched->current_focus_division ?? 'null'));
        }

        // PHASE 0: Process due manual PM generation queue rows (additive step).
        $this->info('Phase 0 — Processing manual PM generation queue...');
        $this->processManualQueue($service);
        $this->info('');

        // PHASE 1: Check all active schedules for completed divisions and advance them.
        $this->info('Phase 1 — Checking for completed divisions and advancing cycles...');
        $advancedScheduleIds = [];
        $phaseOneActed = false;
        foreach ($allActive as $sched) {
            if (!$sched->current_focus_division) {
                continue;
            }
            $prevDivision = $sched->current_focus_division;
            try {
                [$advanced, $cycleComplete] = $service->checkAndAdvance($sched);
                if ($advanced) {
                    $this->info("  '{$sched->schedule_name}': '{$prevDivision}' done → advancing to '{$advanced}'.");
                    $advancedScheduleIds[] = $sched->id;
                    $phaseOneActed = true;
                } elseif ($cycleComplete) {
                    // All divisions done — each division already has its own next date saved
                    $sched->refresh();
                    $divDates = $sched->divisionSchedules()->orderBy('next_scheduled_at')->get()
                        ->map(fn($d) => $d->division_name . ' → ' . $d->next_scheduled_at?->toDateString())
                        ->join(', ');
                    $this->info("  '{$sched->schedule_name}': '{$prevDivision}' done → ALL DIVISIONS DONE ✓ Full cycle complete.");
                    $this->info("  Per-division next dates: {$divDates}");
                    Log::info("PM full cycle complete for schedule #{$sched->id}. Per-division dates: {$divDates}");
                    $phaseOneActed = true;
                }
            } catch (\Exception $e) {
                $this->error("  Advance check failed for '{$sched->schedule_name}': {$e->getMessage()}");
                Log::error("PM advance check failed for schedule #{$sched->id}: {$e->getMessage()}");
            }
        }
        if (!$phaseOneActed) {
            $this->line('  No divisions ready to advance.');
        }

        // PHASE 2: Generate for schedules that are due.
        // A schedule is due if:
        // (a) It has no active cycle AND at least one division is due today (per pm_division_schedules)
        // (b) It has never run before (no pm_division_schedules records) — fresh start
        // (c) It was just advanced in Phase 1
        // (d) It has a focus division set but no pending requests yet

        $dueScheduleIds = [];

        foreach ($allActive as $sched) {
            // Already captured in Phase 1 advance
            if (in_array($sched->id, $advancedScheduleIds)) {
                $dueScheduleIds[] = $sched->id;
                continue;
            }

            // Has a focus division but no pending requests (advanced but not yet generated)
            if ($sched->current_focus_division) {
                $hasPending = $sched->requests()
                    ->where('is_auto_generated', true)
                    ->whereIn('status', ['Scheduled', 'Ongoing', 'Awaiting Signature'])
                    ->exists();
                if (!$hasPending) {
                    $dueScheduleIds[] = $sched->id;
                }
                continue;
            }

            // No active cycle — check if any division is due today (scoped to last cycle)
            $lastCycle = \App\Models\PMCycle::where('pm_schedule_id', $sched->id)
                ->whereNotNull('completed_at')
                ->latest('completed_at')
                ->first();

            $hasDivisionDue = false;
            $neverRun = !\App\Models\PMCycle::where('pm_schedule_id', $sched->id)->exists();

            if ($lastCycle) {
                $hasDivisionDue = \App\Models\PMDivisionSchedule::where('pm_cycle_id', $lastCycle->id)
                    ->whereNotNull('next_scheduled_at')
                    ->where('next_scheduled_at', '<=', now()->toDateString())
                    ->exists();
            }

            if ($hasDivisionDue || $neverRun) {
                $dueScheduleIds[] = $sched->id;
            }
        }

        $schedules = PMSchedule::active()->whereIn('id', $dueScheduleIds)->get();

        if ($schedules->isEmpty()) {
            $this->info('No schedules due for generation.');
            return Command::SUCCESS;
        }

        $totalGenerated = 0;
        $errors = [];

        $this->info('Phase 2 — Generating PM requests...');
        foreach ($schedules as $schedule) {
            try {
                $created = $service->generate($schedule);
                if (isset($created['__cooldown__'])) {
                    $this->line("  Schedule '{$schedule->schedule_name}': in cooldown until {$created['__cooldown__']}. Skipping.");
                    continue;
                }
                $count = count($created);
                $totalGenerated += $count;
                $division = $schedule->fresh()->current_focus_division ?? 'N/A';

                // ── MONITORING: Alert super admin if generation produced 0 requests ──
                // This happens when the schedule is due but no eligible users were found
                // (e.g. all assets disposed, wrong branch config, data issue)
                if ($count === 0) {
                    $message = "PM Schedule '{$schedule->schedule_name}' ran but generated 0 work orders. "
                        . "Please check if assets are properly assigned to users in this branch.";
                    Log::warning("PM generation produced 0 requests for schedule #{$schedule->id}: {$schedule->schedule_name}");
                    $this->notifySuperAdmins($schedule, $message);
                } else {
                    // Update last_generated_date for health tracking
                    \Illuminate\Support\Facades\DB::table('pm_schedules')
                        ->where('id', $schedule->id)
                        ->update(['last_generated_date' => now()]);
                }

                $this->info("  Schedule '{$schedule->schedule_name}': generated {$count} request(s). Focus: {$division}");
                Log::info("PM Schedule auto-generated: {$schedule->schedule_name} - {$count} requests, Focus: {$division}");
            } catch (\Exception $e) {
                $errors[] = "Schedule #{$schedule->id} ({$schedule->schedule_name}): {$e->getMessage()}";
                $this->error("  Failed: {$schedule->schedule_name} - {$e->getMessage()}");
                Log::error("PM Schedule auto-generation failed for schedule #{$schedule->id}: {$e->getMessage()}");

                // ── MONITORING: Alert super admin on generation failure ──
                $message = "PM Schedule '{$schedule->schedule_name}' FAILED to generate work orders. "
                    . "Error: {$e->getMessage()}. Please check the system logs.";
                $this->notifySuperAdmins($schedule, $message);
            }
        }

        $this->info("Done. Total generated: {$totalGenerated}");

        if (!empty($errors)) {
            foreach ($errors as $err) {
                $this->warn("  Error: {$err}");
            }
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Notify all super admins in the schedule's branch about a PM monitoring alert.
     * Sends both in-app notification AND email so super admin is alerted
     * even when not logged in — critical for cron failure detection.
     * Each branch's super admin only receives alerts for their own branch.
     * Does NOT crash the command if notification fails.
     */
    private function processManualQueue(GeneratePMScheduleService $service): void
    {
        $dueQueue = PMGenerationSchedule::dueOnOrBefore(now())->get();

        if ($dueQueue->isEmpty()) {
            $this->line('  No manual PM generation queue rows due.');
            return;
        }

        $processed = 0;
        $generated = 0;
        $failed = 0;

        foreach ($dueQueue as $queueRow) {
            $processed++;
            try {
                $locked = PMGenerationSchedule::lockForUpdate()->find($queueRow->id);

                if (!$locked || !$locked->isPending()) {
                    continue;
                }

                $locked->update(['status' => PMGenerationSchedule::STATUS_PROCESSING]);

                $pmSchedule = PMSchedule::find($locked->pm_schedule_id);

                if (!$pmSchedule || !$pmSchedule->is_active) {
                    $locked->update([
                        'status' => PMGenerationSchedule::STATUS_FAILED,
                        'failure_message' => 'PM schedule not found or inactive',
                    ]);
                    $this->logQueueAudit($locked, 'Scheduled PM Generation Failed', 'PM schedule not found or inactive');
                    $failed++;
                    continue;
                }

                $created = $service->generate($pmSchedule);

                if (isset($created['__cooldown__'])) {
                    $locked->update([
                        'status' => PMGenerationSchedule::STATUS_FAILED,
                        'failure_message' => "Service cooldown active until {$created['__cooldown__']}",
                    ]);
                    $this->logQueueAudit($locked, 'Scheduled PM Generation Skipped', "Cooldown active until {$created['__cooldown__']}");
                    $this->line("  Queue #{$locked->id}: cooldown until {$created['__cooldown__']}");
                    continue;
                }

                if (empty($created)) {
                    $locked->update([
                        'status' => PMGenerationSchedule::STATUS_GENERATED,
                        'generated_at' => now(),
                        'generated_count' => 0,
                        'generated_division' => $pmSchedule->current_focus_division,
                        'pm_cycle_id' => $pmSchedule->current_cycle_id,
                        'failure_message' => 'Generation produced 0 work orders (no eligible users found)',
                    ]);
                    $this->logQueueAudit($locked, 'Scheduled PM Generation Executed', 'Generated 0 work orders');
                    $this->line("  Queue #{$locked->id}: 0 work orders generated");
                    continue;
                }

                $pmSchedule->refresh();
                $locked->update([
                    'status' => PMGenerationSchedule::STATUS_GENERATED,
                    'generated_at' => now(),
                    'generated_count' => count($created),
                    'generated_division' => $pmSchedule->current_focus_division,
                    'pm_cycle_id' => $pmSchedule->current_cycle_id,
                ]);
                $this->logQueueAudit($locked, 'Scheduled PM Generation Executed', 'Generated ' . count($created) . ' work orders');
                $generated++;
                $this->info("  Queue #{$locked->id}: generated " . count($created) . " work orders");

            } catch (\Exception $e) {
                $queueRow->update([
                    'status' => PMGenerationSchedule::STATUS_FAILED,
                    'failure_message' => $e->getMessage(),
                ]);
                $this->logQueueAudit($queueRow, 'Scheduled PM Generation Failed', $e->getMessage());
                $failed++;
                $this->error("  Queue #{$queueRow->id}: failed - {$e->getMessage()}");
                Log::error("PM generation queue #{$queueRow->id} failed: {$e->getMessage()}");
            }
        }

        $this->info("  Manual queue: {$processed} processed, {$generated} generated, {$failed} failed.");
    }

    private function logQueueAudit(PMGenerationSchedule $queueRow, string $action, string $details): void
    {
        try {
            AuditLog::create([
                'user_id' => $queueRow->generated_by,
                'action' => $action,
                'module' => 'PM Schedule',
                'details' => $details,
                'region' => $queueRow->generator?->branch ?? 'System',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Console/Cron',
            ]);
        } catch (\Throwable $e) {
            Log::warning("Failed to log console audit for PM generation queue #{$queueRow->id}: {$e->getMessage()}");
        }
    }

    private function notifySuperAdmins(PMSchedule $schedule, string $message): void
    {
        try {
            // Get the schedule's actor/creator to find the right branch
            $creator = $schedule->created_by
                ? \App\Models\User::find($schedule->created_by)
                : null;
            $branch = $creator?->branch;

            // Only notify super admins in the same branch as the schedule
            // This ensures a super admin from one region doesn't get alerts for another region
            $superAdmins = \App\Models\User::where('role', 'super_admin')
                ->where('is_active', true)
                ->when($creator?->region, fn($q) => $q->where('region', $creator->region))
                ->when($branch, fn($q) => $q->where('branch', $branch))
                ->get();

            // Build detailed email content with schedule context
            $scheduleUrl = route('pm-schedules.show', $schedule->id);
            $detailedMessage = implode("\n", [
                $message,
                '',
                '— Schedule Details —',
                'Schedule Name : ' . $schedule->schedule_name,
                'Branch        : ' . ($branch ?? 'Not set'),
                'Frequency     : ' . $schedule->frequency,
                'Cycle #       : ' . ($schedule->cycle_count ?? 0),
                'Last Run      : ' . ($schedule->last_generated_date
                    ? \Carbon\Carbon::parse($schedule->last_generated_date)->format('F d, Y h:i A')
                    : 'Never'),
                'Time of Alert : ' . now()->format('F d, Y h:i A'),
                '',
                'Please log in to your CMMS dashboard to investigate:',
                $scheduleUrl,
            ]);

            foreach ($superAdmins as $admin) {
                // In-app notification (visible in notification bell)
                \App\Models\Notification::send(
                    $admin->id,
                    null,
                    'PM Alert',
                    $message
                );

                // Email alert — so super admin is notified even when not logged in
                if ($admin->email) {
                    try {
                        $subject = str_contains($message, 'FAILED')
                            ? 'PM Generation FAILED — Action Required'
                            : 'PM Generation Alert — 0 Work Orders Generated';

                        \Illuminate\Support\Facades\Mail::to($admin->email)->send(
                            new \App\Mail\SystemNotificationMail(
                                $admin->full_name,
                                $subject,
                                $detailedMessage,
                                'SYSTEM',
                                $scheduleUrl,
                                $branch,
                                $admin->region ?? null
                            )
                        );
                        Log::info("PM alert email sent to {$admin->email} (branch: {$branch})");
                    } catch (\Throwable $mailError) {
                        Log::warning("Failed to send PM alert email to {$admin->email}: {$mailError->getMessage()}");
                    }
                }
            }

            \App\Models\AuditLog::log(
                'PM Monitoring Alert',
                'PM Schedule',
                "[{$branch}] {$message}",
                $branch ?? 'System'
            );
        } catch (\Throwable $e) {
            Log::warning("Failed to send PM monitoring notification: {$e->getMessage()}");
        }
    }
}
