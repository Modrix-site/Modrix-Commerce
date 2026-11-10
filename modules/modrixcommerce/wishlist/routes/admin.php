<?php

use EcoModular\Wishlist\Http\WishlistAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:wishlist.manage')->group(function () {
    Route::get('/', [WishlistAdminController::class, 'index'])->name('index');
    Route::get('/settings', [WishlistAdminController::class, 'settings'])->name('settings');
    Route::put('/settings', [WishlistAdminController::class, 'updateSettings'])->name('settings.update');
});
