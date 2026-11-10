<?php

use Illuminate\Support\Facades\Route;
use ModrixCommerce\PayTabs\Http\PayTabsAdminController;

Route::middleware(['can:payments.manage', 'password.confirm'])->group(function () {
    Route::get('/', [PayTabsAdminController::class, 'index'])->name('index');
    Route::put('settings', [PayTabsAdminController::class, 'update'])->name('update');
    Route::post('refunds/reconcile', [PayTabsAdminController::class, 'reconcileRefund'])->middleware('throttle:10,1')->name('refunds.reconcile');
});
