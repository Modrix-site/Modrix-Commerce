<?php

use EcoModular\OrderReturns\Http\ReturnAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:returns.manage')->group(function () {
    Route::get('/', [ReturnAdminController::class, 'index'])->name('index');
    Route::post('{returnRequest}/approve', [ReturnAdminController::class, 'approve'])->name('approve');
    Route::post('{returnRequest}/reject', [ReturnAdminController::class, 'reject'])->name('reject');
});
