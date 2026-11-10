<?php

use EcoModular\SocialProofNotifications\Http\Controllers\SocialProofAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:social_proof.manage')->group(function () {
    Route::get('/', [SocialProofAdminController::class, 'index'])->name('index');
    Route::post('/', [SocialProofAdminController::class, 'store'])->name('store');
    Route::post('{event}/toggle', [SocialProofAdminController::class, 'toggle'])->name('toggle');
    Route::delete('{event}', [SocialProofAdminController::class, 'destroy'])->name('destroy');
    Route::post('seed-sample', [SocialProofAdminController::class, 'seedSample'])->name('seed-sample');
    Route::post('clear-all', [SocialProofAdminController::class, 'clearAll'])->name('clear-all');
});
