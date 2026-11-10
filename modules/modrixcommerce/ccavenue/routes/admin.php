<?php

use Illuminate\Support\Facades\Route;
use ModrixCommerce\CCAvenue\Http\CCAvenueAdminController;

Route::middleware(['can:payments.manage', 'password.confirm'])->group(function () {
    Route::get('/', [CCAvenueAdminController::class, 'index'])->name('index');
    Route::put('settings', [CCAvenueAdminController::class, 'update'])->name('update');
});
