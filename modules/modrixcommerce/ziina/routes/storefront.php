<?php

/*
| Loaded under /m/modrixcommerce/ziina with web middleware
| and the "modrixcommerce.ziina." name prefix.
*/

use EcoModular\Ziina\Http\ZiinaCheckoutController;
use Illuminate\Support\Facades\Route;

Route::match(['get', 'post'], 'response', [ZiinaCheckoutController::class, 'response'])
    ->middleware('throttle:60,1')
    ->name('response');
