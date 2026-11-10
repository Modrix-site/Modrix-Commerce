<?php

/*
| Loaded under /m/modrixcommerce/payby with web middleware
| and the "modrixcommerce.payby." name prefix.
*/

use EcoModular\PayBy\Http\PaybyCheckoutController;
use Illuminate\Support\Facades\Route;

Route::match(['get', 'post'], 'response', [PaybyCheckoutController::class, 'response'])
    ->middleware('throttle:60,1')
    ->name('response');
