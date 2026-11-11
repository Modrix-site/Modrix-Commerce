<?php

/*
| Loaded under /m/modrixcommerce/payu with web middleware
| and the "modrixcommerce.payu." name prefix.
*/

use EcoModular\PayU\Http\PayuCheckoutController;
use Illuminate\Support\Facades\Route;

Route::get('handoff/{payment}', [PayuCheckoutController::class, 'handoff'])
    ->name('handoff');

Route::match(['get', 'post'], 'response', [PayuCheckoutController::class, 'response'])
    ->middleware('throttle:60,1')
    ->name('response');
