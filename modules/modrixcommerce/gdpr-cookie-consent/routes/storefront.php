<?php

/*
| Loaded by the platform under /m/modrixcommerce/gdpr-cookie-consent with the
| web middleware and the "modrixcommerce.gdpr-cookie-consent." name prefix.
*/

use EcoModular\GdprCookieConsent\Http\ConsentController;
use Illuminate\Support\Facades\Route;

Route::post('store', [ConsentController::class, 'store'])
    ->middleware('throttle:30,1')
    ->name('store');
