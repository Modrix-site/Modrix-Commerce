<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Catches payments whose webhooks were missed or delayed, and abandoned payment pages.
Schedule::command('ecomodular:payments:reconcile')->everyFiveMinutes()->withoutOverlapping();

// Marketplace license status and update availability (never disables anything).
Schedule::command('ecomodular:marketplace:check')->daily()->withoutOverlapping();
