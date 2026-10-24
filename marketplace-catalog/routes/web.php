<?php

use Illuminate\Support\Facades\Route;
use Marketplace\Http\Controllers\CheckoutController;
use Marketplace\Http\Controllers\StripeWebhookController;

// Payments. The webhook is excluded from CSRF (bootstrap/app.php); Stripe signs it.
Route::post('stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');

Route::middleware('auth')->group(function () {
    Route::post('checkout/{package:slug}', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout.store');
    Route::get('checkout/success', [CheckoutController::class, 'success'])->name('checkout.success');
});
