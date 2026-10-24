<?php

use Illuminate\Support\Facades\Schedule;

// Runs when the host's cron calls `php artisan schedule:run` every minute.
Schedule::command('marketplace:prune-downloads')->hourly();

// Shared hosting has no long-running worker: send queued email (license keys) each minute.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')->everyMinute()->withoutOverlapping();
