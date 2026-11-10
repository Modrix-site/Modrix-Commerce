<?php

/*
| Loaded under /admin/m/modrixcommerce/payby with admin middleware
| and the "admin.modrixcommerce.payby." name prefix.
*/

use EcoModular\PayBy\Http\PaybyAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware(['can:payments.manage', 'password.confirm'])->group(function () {
    Route::get('/', [PaybyAdminController::class, 'index'])->name('index');
    Route::put('settings', [PaybyAdminController::class, 'update'])->name('update');
    Route::post('test', [PaybyAdminController::class, 'test'])->middleware('throttle:10,1')->name('test');
});
