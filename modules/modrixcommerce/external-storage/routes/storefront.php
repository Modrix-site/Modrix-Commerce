<?php

use EcoModular\ExternalStorage\Http\Controllers\ExternalStorageStorefrontController;
use Illuminate\Support\Facades\Route;

Route::get('products/{product}/media', [ExternalStorageStorefrontController::class, 'productMedia'])->name('products.media');
