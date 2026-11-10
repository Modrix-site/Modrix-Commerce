<?php

/*
| Loaded under /admin/m/modrixcommerce/buy-together-save with admin middleware
| and the "admin.modrixcommerce.buy-together-save." name prefix.
*/

use EcoModular\BuyTogetherSave\Http\BuyTogetherAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:buy_together.manage')->group(function () {
    Route::get('/', [BuyTogetherAdminController::class, 'index'])->name('index');
    Route::get('/create', [BuyTogetherAdminController::class, 'create'])->name('create');
    Route::post('/', [BuyTogetherAdminController::class, 'store'])->name('store');
    Route::get('/settings', [BuyTogetherAdminController::class, 'settings'])->name('settings');
    Route::put('/settings', [BuyTogetherAdminController::class, 'updateSettings'])->name('settings.update');
    Route::get('/{deal}/edit', [BuyTogetherAdminController::class, 'edit'])->name('edit');
    Route::put('/{deal}', [BuyTogetherAdminController::class, 'update'])->name('update');
    Route::delete('/{deal}', [BuyTogetherAdminController::class, 'destroy'])->name('destroy');
    Route::post('/{deal}/toggle', [BuyTogetherAdminController::class, 'toggle'])->name('toggle');
});
