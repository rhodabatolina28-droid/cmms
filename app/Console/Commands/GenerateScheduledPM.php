<?php

namespace App\Console\Commands;

use App\Models\PMSchedule;
use App\Services\GeneratePMScheduleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateScheduledPM extends Command
{
    protected $signature = 'pm:generate-scheduled';
    protected $description = 'Generate PM requests for all active schedules whose next_scheduled_date <= today';

    public function handle(GeneratePMScheduleService $service): int
    {
        $schedules = PMSchedule::active()
            
            ->get();

        if ($schedules->isEmpty()) {
            $this->info('No schedules due for generation.');
            return Command::SUCCESS;
        }

        $totalGenerated = 0;
        $errors = [];

        foreach ($schedules as $schedule) {
            try {
                $created = $service->generate($schedule);
                $count = count($created);
                $totalGenerated += $count;
                $this->info("Schedule '{$schedule->schedule_name}': generated {$count} request(s).");
                Log::info("PM Schedule auto-generated: {$schedule->schedule_name} - {$count} requests");
            } catch (\Exception $e) {
                $errors[] = "Schedule #{$schedule->id} ({$schedule->schedule_name}): {$e->getMessage()}";
                $this->error("Failed: {$schedule->schedule_name} - {$e->getMessage()}");
                Log::error("PM Schedule auto-generation failed for schedule #{$schedule->id}: {$e->getMessage()}");
            }
        }

        $this->info("Done. Total generated: {$totalGenerated}");

        if (!empty($errors)) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}

