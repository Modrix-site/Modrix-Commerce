<?php

use EcoModular\ProductAddons\Http\AddonCartController;
use Illuminate\Support\Facades\Route;

Route::post('/toggle', [AddonCartController::class, 'toggle'])->name('toggle');
