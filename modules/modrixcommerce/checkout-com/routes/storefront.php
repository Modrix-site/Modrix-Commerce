<?php

/*
| Loaded under /m/modrixcommerce/checkout-com. Flow is shown only to the customer
| who placed the order.
*/

use EcoModular\CheckoutCom\Http\CheckoutComFlowController;
use Illuminate\Support\Facades\Route;

Route::get('pay/{payment}', CheckoutComFlowController::class)
    ->middleware('throttle:30,1')
    ->name('flow');
