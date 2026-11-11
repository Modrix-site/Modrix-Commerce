<?php

use EcoModular\StorePickup\Http\StorePickupStorefrontController;
use Illuminate\Support\Facades\Route;

/*
| Loaded by the platform under /m/modrixcommerce/store-pickup with the web
| middleware and the "modrixcommerce.store-pickup." name prefix.
*/

Route::get('branches', [StorePickupStorefrontController::class, 'branches'])->name('branches');
Route::post('select', [StorePickupStorefrontController::class, 'select'])->name('select');
Route::post('clear', [StorePickupStorefrontController::class, 'clear'])->name('clear');
