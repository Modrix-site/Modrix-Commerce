<?php

use App\Http\Controllers\DashboardRedirectController;
use App\Http\Controllers\Storefront\AccountController;
use App\Http\Controllers\Storefront\AccountOrderController;
use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\CatalogController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\ProductController;
use App\Http\Middleware\ShareStorefrontData;
use Illuminate\Support\Facades\Route;

Route::middleware(['installed', ShareStorefrontData::class])->group(function () {
    Route::get('/', [CatalogController::class, 'home'])->name('home');
    Route::get('search', [CatalogController::class, 'search'])->name('storefront.search');
    Route::get('c/{slug}', [CatalogController::class, 'category'])->name('storefront.category');
    Route::get('p/{slug}', [ProductController::class, 'show'])->name('storefront.product');

    Route::get('cart', [CartController::class, 'show'])->name('cart.show');
    Route::middleware('throttle:60,1')->group(function () {
        Route::post('cart/items', [CartController::class, 'store'])->name('cart.items.store');
        Route::patch('cart/items/{item}', [CartController::class, 'update'])->name('cart.items.update');
        Route::delete('cart/items/{item}', [CartController::class, 'destroy'])->name('cart.items.destroy');
        Route::post('cart/coupon', [CartController::class, 'applyCoupon'])->name('cart.coupon.apply');
        Route::delete('cart/coupon', [CartController::class, 'removeCoupon'])->name('cart.coupon.remove');
    });

    Route::get('checkout', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('checkout', [CheckoutController::class, 'store'])->middleware('throttle:checkout')->name('checkout.store');
    Route::get('checkout/complete/{order}', [CheckoutController::class, 'complete'])->name('checkout.complete');
    Route::post('checkout/orders/{order}/pay', [CheckoutController::class, 'pay'])->middleware('throttle:checkout')->name('checkout.pay');
    Route::get('checkout/orders/{order}/payment/return', [CheckoutController::class, 'paymentReturn'])->middleware('throttle:30,1')->name('checkout.payment.return');

    Route::middleware('auth')->prefix('account')->name('account.')->group(function () {
        Route::get('/', [AccountController::class, 'show'])->name('show');
        Route::get('addresses', [AccountController::class, 'addresses'])->name('addresses');
        Route::post('addresses', [AccountController::class, 'storeAddress'])->name('addresses.store');
        Route::put('addresses/{address}', [AccountController::class, 'updateAddress'])->name('addresses.update');
        Route::delete('addresses/{address}', [AccountController::class, 'destroyAddress'])->name('addresses.destroy');
        Route::get('orders', [AccountOrderController::class, 'index'])->name('orders');
        Route::get('orders/{order}', [AccountOrderController::class, 'show'])->name('orders.show');
    });
});

Route::middleware(['installed', 'auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardRedirectController::class)->name('dashboard');
});

require __DIR__.'/settings.php';
