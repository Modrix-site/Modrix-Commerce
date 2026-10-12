<?php

use App\Core\Operations\HealthCheck;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Catches payments whose webhooks were missed or delayed, and abandoned payment pages.
Schedule::command('ecomodular:payments:reconcile')->everyFiveMinutes()->withoutOverlapping();

// Heartbeat for the health check: proves the scheduler (cron) is running.
Schedule::call(fn () => HealthCheck::beat())->everyMinute()->name('ecomodular:heartbeat');

// Daily backup with retention (ECOMODULAR_BACKUP_SCHEDULE, empty to disable).
if ((string) config('ecomodular.backup.schedule') !== '') {
    Schedule::command('ecomodular:backup')->dailyAt((string) config('ecomodular.backup.schedule'))->withoutOverlapping()->onOneServer();
}

// Marketplace license status and update availability (never disables anything).
Schedule::command('ecomodular:marketplace:check')->daily()->withoutOverlapping();
