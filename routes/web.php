<?php

use App\Core\Settings\StoreSettings;
use App\Http\Controllers\DashboardRedirectController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::middleware('installed')->group(function () {
    Route::get('/', fn (StoreSettings $store) => Inertia::render('welcome', [
        'storeName' => $store->name(),
        'canRegister' => Features::enabled(Features::registration()),
    ]))->name('home');

    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('dashboard', DashboardRedirectController::class)->name('dashboard');
    });
});

require __DIR__.'/settings.php';
