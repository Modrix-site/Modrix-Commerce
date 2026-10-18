<?php

/*
| Loaded under /m/ecomodular/authorize-net. The page posts the form token to
| Authorize.Net and is shown only to the customer who placed the order.
*/

use EcoModular\AuthorizeNet\Http\AuthorizeNetHandoffController;
use Illuminate\Support\Facades\Route;

Route::get('pay/{payment}', AuthorizeNetHandoffController::class)
    ->middleware('throttle:30,1')
    ->name('handoff');
