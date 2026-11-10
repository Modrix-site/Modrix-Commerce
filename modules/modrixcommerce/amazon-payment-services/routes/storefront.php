<?php

/*
| Loaded under /m/modrixcommerce/amazon-payment-services with web middleware
| and the "modrixcommerce.amazon-payment-services." name prefix.
*/

use EcoModular\AmazonPaymentServices\Http\AmazonPaymentServicesCheckoutController;
use Illuminate\Support\Facades\Route;

Route::get('pay/{payment}', [AmazonPaymentServicesCheckoutController::class, 'handoff'])
    ->middleware('throttle:30,1')
    ->name('handoff');

Route::match(['get', 'post'], 'response', [AmazonPaymentServicesCheckoutController::class, 'response'])
    ->middleware('throttle:60,1')
    ->name('response');
