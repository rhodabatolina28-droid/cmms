<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto-generate PM requests for schedules that are due (next_scheduled_date <= today)
Schedule::command('pm:generate-scheduled')->daily();

// BUG-SCHED-1: restored from the dead app/Console/Kernel.php (Laravel 13 never
// reads Kernel::schedule — bootstrap/app.php only registers routes/console.php).
// These three never ran until now; verified signatures + manual first runs before
// enabling. With no server yet they fire only via `php artisan schedule:work` or
// manual runs; the future prod cron (`schedule:run` every minute) picks all up.
Schedule::command('pm:send-reminders')->dailyAt('06:00');
Schedule::command('parts:check-low-stock')->dailyAt('07:00');
Schedule::command('inventory:verify-asset-sets')->dailyAt('08:00');

