<?php

use EcoModular\StorePickup\Http\StorePickupAdminController;
use Illuminate\Support\Facades\Route;

/*
| Loaded by the platform under /admin/m/modrixcommerce/store-pickup with the admin
| middleware and the "admin.modrixcommerce.store-pickup." name prefix.
*/

Route::middleware('can:store_pickup.manage')->group(function () {
    Route::get('/', [StorePickupAdminController::class, 'index'])->name('index');
    Route::post('branches', [StorePickupAdminController::class, 'storeBranch'])->name('branches.store');
    Route::put('branches/{branch}', [StorePickupAdminController::class, 'updateBranch'])->name('branches.update');
    Route::delete('branches/{branch}', [StorePickupAdminController::class, 'destroyBranch'])->name('branches.destroy');
    Route::post('orders/{orderPickup}/status', [StorePickupAdminController::class, 'updateOrderStatus'])->name('orders.status');
    Route::post('settings', [StorePickupAdminController::class, 'saveSettings'])->name('settings.save');
});
