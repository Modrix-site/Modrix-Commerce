<?php

/*
| Loaded under /admin/m/modrixcommerce/ziina with admin middleware
| and the "admin.modrixcommerce.ziina." name prefix.
*/

use EcoModular\Ziina\Http\ZiinaAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware(['can:payments.manage', 'password.confirm'])->group(function () {
    Route::get('/', [ZiinaAdminController::class, 'index'])->name('index');
    Route::put('settings', [ZiinaAdminController::class, 'update'])->name('update');
    Route::post('test', [ZiinaAdminController::class, 'test'])->middleware('throttle:10,1')->name('test');
});
