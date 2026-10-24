<?php

use Illuminate\Support\Facades\Schedule;

// Runs when the host's cron calls `php artisan schedule:run` every minute (optional).
Schedule::command('marketplace:prune-downloads')->hourly();
