<?php

/*
| Loaded by the platform under /admin/m/modrixcommerce/cross-sell-upsell with the
| admin middleware and the "admin.modrixcommerce.cross-sell-upsell." name prefix.
*/

use EcoModular\CrossSellUpsell\Http\OfferAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:cross_sell.manage')->group(function () {
    Route::get('/', [OfferAdminController::class, 'index'])->name('index');
    Route::get('create', [OfferAdminController::class, 'create'])->name('create');
    Route::post('/', [OfferAdminController::class, 'store'])->name('store');
    Route::get('products/search', [OfferAdminController::class, 'searchProducts'])->name('products.search');
    Route::get('{offer}/edit', [OfferAdminController::class, 'edit'])->name('edit');
    Route::put('{offer}', [OfferAdminController::class, 'update'])->name('update');
    Route::delete('{offer}', [OfferAdminController::class, 'destroy'])->name('destroy');
});
