<?php

/*
| Admin routes. Registered in bootstrap/app.php under the "admin." name prefix
| and the web, installed, auth, verified and staff middleware.
| Every route below additionally declares the permission it requires.
| Mutations of users and roles require a recent password confirmation.
*/

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\StoreSettingsController;
use App\Http\Controllers\Admin\SystemStatusController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');

Route::middleware('can:users.view')->group(function () {
    Route::get('users', [UserController::class, 'index'])->name('users.index');
});

Route::middleware(['can:users.manage', 'password.confirm'])->group(function () {
    Route::get('users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('users/{user}/roles', [UserController::class, 'updateRoles'])->name('users.roles.update');
    Route::put('users/{user}/status', [UserController::class, 'updateStatus'])->name('users.status.update');
});

Route::middleware(['can:roles.manage', 'password.confirm'])->group(function () {
    Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
    Route::get('roles/create', [RoleController::class, 'create'])->name('roles.create');
    Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
    Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
    Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
});

Route::middleware('can:settings.manage')->group(function () {
    Route::get('settings/store', [StoreSettingsController::class, 'edit'])->name('settings.store.edit');
    Route::put('settings/store', [StoreSettingsController::class, 'update'])->name('settings.store.update');
});

Route::get('audit-logs', AuditLogController::class)->middleware('can:audit.view')->name('audit-logs.index');
Route::get('system', SystemStatusController::class)->middleware('can:system.view')->name('system.status');
