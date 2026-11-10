<?php

/*
| Loaded under /admin/m/modrixcommerce/instamojo with admin middleware
| and the "admin.modrixcommerce.instamojo." name prefix.
*/

use EcoModular\Instamojo\Http\InstamojoAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware(['can:payments.manage', 'password.confirm'])->group(function () {
    Route::get('/', [InstamojoAdminController::class, 'index'])->name('index');
    Route::put('settings', [InstamojoAdminController::class, 'update'])->name('update');
    Route::post('test', [InstamojoAdminController::class, 'test'])->middleware('throttle:10,1')->name('test');
});
