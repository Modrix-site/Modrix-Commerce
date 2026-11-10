<?php

/*
| Loaded under /m/modrixcommerce/cashfree with web middleware
| and the "modrixcommerce.cashfree." name prefix.
*/

use EcoModular\Cashfree\Http\CashfreeCheckoutController;
use Illuminate\Support\Facades\Route;

Route::get('pay/{payment}', [CashfreeCheckoutController::class, 'handoff'])
    ->middleware('throttle:30,1')
    ->name('handoff');

Route::get('response', [CashfreeCheckoutController::class, 'response'])
    ->middleware('throttle:60,1')
    ->name('response');
