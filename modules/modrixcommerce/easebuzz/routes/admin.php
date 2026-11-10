<?php

/*
| Loaded under /admin/m/modrixcommerce/easebuzz with admin middleware
| and the "admin.modrixcommerce.easebuzz." name prefix.
*/

use EcoModular\Easebuzz\Http\EasebuzzAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware(['can:payments.manage', 'password.confirm'])->group(function () {
    Route::get('/', [EasebuzzAdminController::class, 'index'])->name('index');
    Route::put('settings', [EasebuzzAdminController::class, 'update'])->name('update');
    Route::post('test', [EasebuzzAdminController::class, 'test'])->middleware('throttle:10,1')->name('test');
});
