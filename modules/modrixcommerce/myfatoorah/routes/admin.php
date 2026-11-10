<?php

/*
| Loaded under /admin/m/modrixcommerce/myfatoorah with admin middleware
| and the "admin.modrixcommerce.myfatoorah." name prefix.
*/

use EcoModular\MyFatoorah\Http\MyFatoorahAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware(['can:payments.manage', 'password.confirm'])->group(function () {
    Route::get('/', [MyFatoorahAdminController::class, 'index'])->name('index');
    Route::put('settings', [MyFatoorahAdminController::class, 'update'])->name('update');
    Route::post('test', [MyFatoorahAdminController::class, 'test'])->middleware('throttle:10,1')->name('test');
});
