<?php

/*
| Loaded under /admin/m/modrixcommerce/phonepe with admin middleware
| and the "admin.modrixcommerce.phonepe." name prefix.
*/

use EcoModular\PhonePe\Http\PhonePeAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware(['can:payments.manage', 'password.confirm'])->group(function () {
    Route::get('/', [PhonePeAdminController::class, 'index'])->name('index');
    Route::put('settings', [PhonePeAdminController::class, 'update'])->name('update');
    Route::post('test', [PhonePeAdminController::class, 'test'])->middleware('throttle:10,1')->name('test');
});
