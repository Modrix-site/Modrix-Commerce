<?php

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;
use ModrixCommerce\Razorpay\Http\RazorpayCheckoutController;

Route::get('pay/{payment}', [RazorpayCheckoutController::class, 'show'])->middleware('throttle:30,1')->name('checkout');
Route::post('return/{payment}', [RazorpayCheckoutController::class, 'complete'])
    ->withoutMiddleware(PreventRequestForgery::class)->middleware('throttle:60,1')->name('return');
