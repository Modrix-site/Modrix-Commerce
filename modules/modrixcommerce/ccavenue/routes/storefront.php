<?php

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;
use ModrixCommerce\CCAvenue\Http\CCAvenueCheckoutController;

Route::get('pay/{payment}', [CCAvenueCheckoutController::class, 'handoff'])->middleware('throttle:30,1')->name('handoff');
// CCAvenue POSTs cross-site without a store CSRF token; confirmation uses its API.
Route::post('response', [CCAvenueCheckoutController::class, 'response'])
    ->withoutMiddleware(PreventRequestForgery::class)->middleware('throttle:60,1')->name('response');
