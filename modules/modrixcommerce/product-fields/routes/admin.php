<?php

/*
| Loaded by the platform under /admin/m/modrixcommerce/product-fields with the
| admin middleware and the "admin.modrixcommerce.product-fields." name prefix.
*/

use EcoModular\ProductFields\Http\ProductFieldAdminController;
use Illuminate\Support\Facades\Route;

// Filling in a product's values from the product editor.
Route::put('product/{product}', [ProductFieldAdminController::class, 'updateProduct'])
    ->middleware('can:products.update')
    ->name('product.update');

Route::middleware('can:product_fields.manage')->group(function () {
    Route::get('/', [ProductFieldAdminController::class, 'index'])->name('index');
    Route::post('/', [ProductFieldAdminController::class, 'store'])->name('store');
    Route::post('reorder', [ProductFieldAdminController::class, 'reorder'])->name('reorder');
    Route::put('{field}', [ProductFieldAdminController::class, 'update'])->name('update');
    Route::delete('{field}', [ProductFieldAdminController::class, 'destroy'])->name('destroy');
});
