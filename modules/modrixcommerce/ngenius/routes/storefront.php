<?php

/*
| Loaded under /m/modrixcommerce/ngenius with web middleware
| and the "modrixcommerce.ngenius." name prefix.
*/

use EcoModular\NGenius\Http\NGeniusCheckoutController;
use Illuminate\Support\Facades\Route;

Route::match(['get', 'post'], 'response', [NGeniusCheckoutController::class, 'response'])
    ->middleware('throttle:60,1')
    ->name('response');
