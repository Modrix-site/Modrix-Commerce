<?php

/*
| Loaded by the platform under /admin/m/modrixcommerce/flash-sales with the admin
| middleware and the "admin.modrixcommerce.flash-sales." name prefix.
*/

use EcoModular\FlashSales\Http\FlashSaleAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:flash_sales.manage')->group(function () {
    Route::get('/', [FlashSaleAdminController::class, 'index'])->name('index');
    Route::get('create', [FlashSaleAdminController::class, 'create'])->name('create');
    Route::post('/', [FlashSaleAdminController::class, 'store'])->name('store');
    Route::get('products/search', [FlashSaleAdminController::class, 'searchProducts'])->name('products.search');
    // The product editor panel (also needs products.update, checked in the controller).
    Route::put('product/{product}', [FlashSaleAdminController::class, 'updateProduct'])->name('product.update');
    Route::get('{sale}/edit', [FlashSaleAdminController::class, 'edit'])->name('edit');
    Route::put('{sale}', [FlashSaleAdminController::class, 'update'])->name('update');
    Route::delete('{sale}', [FlashSaleAdminController::class, 'destroy'])->name('destroy');
    Route::post('{sale}/products', [FlashSaleAdminController::class, 'addProducts'])->name('products.store');
    Route::put('{sale}/products/{item}', [FlashSaleAdminController::class, 'updateItem'])->name('products.update');
    Route::delete('{sale}/products/{item}', [FlashSaleAdminController::class, 'removeItem'])->name('products.destroy');
});
