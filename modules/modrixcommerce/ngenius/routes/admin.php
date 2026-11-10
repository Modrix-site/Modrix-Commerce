<?php

/*
| Loaded under /admin/m/modrixcommerce/ngenius with admin middleware
| and the "admin.modrixcommerce.ngenius." name prefix.
*/

use EcoModular\NGenius\Http\NGeniusAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware(['can:payments.manage', 'password.confirm'])->group(function () {
    Route::get('/', [NGeniusAdminController::class, 'index'])->name('index');
    Route::put('settings', [NGeniusAdminController::class, 'update'])->name('update');
    Route::post('test', [NGeniusAdminController::class, 'test'])->middleware('throttle:10,1')->name('test');
});
