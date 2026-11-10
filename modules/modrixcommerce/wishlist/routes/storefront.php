<?php

use EcoModular\Wishlist\Http\WishlistStorefrontController;
use Illuminate\Support\Facades\Route;

Route::get('/wishlist', [WishlistStorefrontController::class, 'index'])->name('index');
Route::post('/wishlist/toggle', [WishlistStorefrontController::class, 'toggle'])->name('toggle');
Route::post('/wishlist/items/{item}/move-to-cart', [WishlistStorefrontController::class, 'moveToCart'])->name('move_to_cart');
Route::delete('/wishlist/items/{item}', [WishlistStorefrontController::class, 'destroy'])->name('destroy');
Route::get('/wishlist/shared/{shareToken}', [WishlistStorefrontController::class, 'share'])->name('share');
