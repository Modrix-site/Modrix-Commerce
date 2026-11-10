<?php

/*
| Loaded under /m/modrixcommerce/easebuzz with web middleware
| and the "modrixcommerce.easebuzz." name prefix.
*/

use EcoModular\Easebuzz\Http\EasebuzzCheckoutController;
use Illuminate\Support\Facades\Route;

Route::get('handoff/{payment}', [EasebuzzCheckoutController::class, 'handoff'])
    ->name('handoff');

Route::match(['get', 'post'], 'response', [EasebuzzCheckoutController::class, 'response'])
    ->middleware('throttle:60,1')
    ->name('response');
