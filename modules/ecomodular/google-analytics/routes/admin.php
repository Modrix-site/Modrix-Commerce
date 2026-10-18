<?php

/*
| Loaded by the platform under /admin/m/ecomodular/google-analytics with the
| admin middleware and the "admin.ecomodular.google-analytics." name prefix.
*/

use EcoModular\GoogleAnalytics\Http\AnalyticsAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:analytics.manage')->group(function () {
    Route::get('/', [AnalyticsAdminController::class, 'index'])->name('index');
});
