<?php

/*
| Loaded under /m/modrixcommerce/instamojo with web middleware
| and the "modrixcommerce.instamojo." name prefix.
*/

use EcoModular\Instamojo\Http\InstamojoCheckoutController;
use Illuminate\Support\Facades\Route;

Route::match(['get', 'post'], 'response', [InstamojoCheckoutController::class, 'response'])
    ->middleware('throttle:60,1')
    ->name('response');
