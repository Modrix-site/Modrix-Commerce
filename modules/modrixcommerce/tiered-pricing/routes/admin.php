<?php

/*
| Loaded under /admin/m/modrixcommerce/tiered-pricing with admin middleware
| and the "admin.modrixcommerce.tiered-pricing." name prefix.
*/

use EcoModular\TieredPricing\Http\TieredPricingAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:tiered_pricing.manage')->group(function () {
    Route::get('/', [TieredPricingAdminController::class, 'index'])->name('index');
    Route::get('/create', [TieredPricingAdminController::class, 'create'])->name('create');
    Route::post('/', [TieredPricingAdminController::class, 'store'])->name('store');
    Route::get('/settings', [TieredPricingAdminController::class, 'settings'])->name('settings');
    Route::put('/settings', [TieredPricingAdminController::class, 'updateSettings'])->name('settings.update');
    Route::get('/{rule}/edit', [TieredPricingAdminController::class, 'edit'])->name('edit');
    Route::put('/{rule}', [TieredPricingAdminController::class, 'update'])->name('update');
    Route::delete('/{rule}', [TieredPricingAdminController::class, 'destroy'])->name('destroy');
    Route::post('/{rule}/toggle', [TieredPricingAdminController::class, 'toggle'])->name('toggle');
});
