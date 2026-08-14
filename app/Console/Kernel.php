<?php

namespace App\Console;

use App\Console\Commands\GenerateScheduledPM;
use App\Console\Commands\SendPMDueReminders;
use App\Console\Commands\CheckPartsLowStock;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command(GenerateScheduledPM::class)->dailyAt('02:00');
        $schedule->command(SendPMDueReminders::class)->dailyAt('06:00');
        $schedule->command(CheckPartsLowStock::class)->dailyAt('07:00');
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
    }
}
