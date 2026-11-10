<?php

use App\Core\Operations\HealthCheck;
use App\Core\Settings\BackupSettings;
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

// Daily backup with retention: Settings → Backups, defaulting to
// ECOMODULAR_BACKUP_SCHEDULE / ECOMODULAR_BACKUP_KEEP (empty schedule disables it).
$backupSettings = app(BackupSettings::class);
if ($backupSettings->schedule() !== '') {
    Schedule::command('ecomodular:backup', $backupSettings->withFiles() ? [] : ['--database-only'])
        ->dailyAt($backupSettings->schedule())->withoutOverlapping()->onOneServer();
}

// Old read notifications (staff and customer bells).
Schedule::command('notifications:prune')->daily()->withoutOverlapping();

// Looks for a newer signed platform release on Modrix Cloud.
Schedule::command('ecomodular:core-check')->daily()->withoutOverlapping()->onOneServer();
