<?php

/*
| Loaded under /m/modrixcommerce/phonepe with web middleware
| and the "modrixcommerce.phonepe." name prefix.
*/

use EcoModular\PhonePe\Http\PhonePeCheckoutController;
use Illuminate\Support\Facades\Route;

Route::match(['get', 'post'], 'response', [PhonePeCheckoutController::class, 'response'])
    ->middleware('throttle:60,1')
    ->name('response');
