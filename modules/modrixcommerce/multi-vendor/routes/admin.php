<?php

use EcoModular\MultiVendor\Http\Controllers\MultiVendorAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:multi_vendor.manage')->group(function () {
    Route::get('/', [MultiVendorAdminController::class, 'index'])->name('index');
    Route::put('settings', [MultiVendorAdminController::class, 'updateSettings'])->name('settings.update');
    Route::post('vendors', [MultiVendorAdminController::class, 'storeVendor'])->name('vendors.store');
    Route::put('vendors/{vendor}/status', [MultiVendorAdminController::class, 'updateVendorStatus'])->name('vendors.status');
    Route::put('vendors/{vendor}/credentials', [MultiVendorAdminController::class, 'updateVendorCredentials'])->name('vendors.credentials');
    Route::put('vendors/{vendor}/verification', [MultiVendorAdminController::class, 'reviewVerification'])->name('vendors.verification');
    Route::post('products/assign', [MultiVendorAdminController::class, 'assignProduct'])->name('products.assign');
    Route::put('products/{vendorProduct}/review', [MultiVendorAdminController::class, 'reviewProduct'])->name('products.review');
    Route::put('payouts/{payout}/process', [MultiVendorAdminController::class, 'processPayout'])->name('payouts.process');
    Route::post('payouts/batch', [MultiVendorAdminController::class, 'batchPayouts'])->name('payouts.batch');
});
