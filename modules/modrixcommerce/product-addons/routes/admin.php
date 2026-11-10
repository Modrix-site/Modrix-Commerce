<?php

use EcoModular\ProductAddons\Http\AddonAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:product_addons.manage')->group(function () {
    Route::get('/', [AddonAdminController::class, 'index'])->name('index');
    Route::get('create', [AddonAdminController::class, 'create'])->name('create');
    Route::post('/', [AddonAdminController::class, 'store'])->name('store');
    Route::get('products', [AddonAdminController::class, 'searchProducts'])->name('products');
    Route::get('{addon}/edit', [AddonAdminController::class, 'edit'])->name('edit');
    Route::put('{addon}', [AddonAdminController::class, 'update'])->name('update');
    Route::delete('{addon}', [AddonAdminController::class, 'destroy'])->name('destroy');
});
