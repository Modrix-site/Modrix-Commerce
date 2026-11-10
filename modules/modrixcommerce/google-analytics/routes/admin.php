<?php

/*
| Loaded by the platform under /admin/m/modrixcommerce/google-analytics with the
| admin middleware and the "admin.modrixcommerce.google-analytics." name prefix.
*/

use EcoModular\GoogleAnalytics\Http\AnalyticsAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:analytics.manage')->group(function () {
    Route::get('/', [AnalyticsAdminController::class, 'index'])->name('index');
});
