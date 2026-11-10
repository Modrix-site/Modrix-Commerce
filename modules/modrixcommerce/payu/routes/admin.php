<?php

/*
| Loaded under /admin/m/modrixcommerce/payu with admin middleware
| and the "admin.modrixcommerce.payu." name prefix.
*/

use EcoModular\PayU\Http\PayuAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware(['can:payments.manage', 'password.confirm'])->group(function () {
    Route::get('/', [PayuAdminController::class, 'index'])->name('index');
    Route::put('settings', [PayuAdminController::class, 'update'])->name('update');
    Route::post('test', [PayuAdminController::class, 'test'])->middleware('throttle:10,1')->name('test');
});
