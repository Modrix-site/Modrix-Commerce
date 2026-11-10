<?php

/*
| Loaded under /m/modrixcommerce/paytm with web middleware
| and the "modrixcommerce.paytm." name prefix.
*/

use EcoModular\Paytm\Http\PaytmCheckoutController;
use Illuminate\Support\Facades\Route;

Route::get('handoff', [PaytmCheckoutController::class, 'handoff'])
    ->name('handoff');

Route::match(['get', 'post'], 'response', [PaytmCheckoutController::class, 'response'])
    ->middleware('throttle:60,1')
    ->name('response');
