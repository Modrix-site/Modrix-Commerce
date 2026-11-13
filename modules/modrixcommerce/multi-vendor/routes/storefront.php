<?php

use EcoModular\MultiVendor\Http\Controllers\MultiVendorStorefrontController;
use Illuminate\Support\Facades\Route;

Route::get('catalog', [MultiVendorStorefrontController::class, 'catalog'])->name('catalog');
Route::get('vendors', [MultiVendorStorefrontController::class, 'vendors'])->name('vendors.index');
Route::get('vendors/{slug}', [MultiVendorStorefrontController::class, 'vendorStore'])->name('vendors.show');

Route::get('register', [MultiVendorStorefrontController::class, 'showRegistrationForm'])->name('register');
Route::post('register', [MultiVendorStorefrontController::class, 'register'])->name('register.store');

Route::get('login', [MultiVendorStorefrontController::class, 'showLoginForm'])->name('login');
Route::post('login', [MultiVendorStorefrontController::class, 'login'])->name('login.store');
Route::post('logout', [MultiVendorStorefrontController::class, 'logout'])->name('logout');

Route::get('dashboard', [MultiVendorStorefrontController::class, 'dashboard'])->name('dashboard');
Route::match(['put', 'post'], 'dashboard/verification', [MultiVendorStorefrontController::class, 'submitVerification'])->name('dashboard.verification');
Route::put('dashboard/payout-settings', [MultiVendorStorefrontController::class, 'updatePayoutSettings'])->name('dashboard.payout-settings');
Route::post('dashboard/products', [MultiVendorStorefrontController::class, 'storeProduct'])->name('dashboard.products.store');
Route::put('dashboard/orders/{vendorOrder}/fulfillment', [MultiVendorStorefrontController::class, 'updateOrderFulfillment'])->name('dashboard.orders.fulfillment');
Route::post('dashboard/payouts', [MultiVendorStorefrontController::class, 'requestPayout'])->name('dashboard.payouts.store');
Route::post('dashboard/payouts/request', [MultiVendorStorefrontController::class, 'requestPayout'])->name('dashboard.payouts.request');
