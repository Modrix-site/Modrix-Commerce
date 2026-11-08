<?php

use EcoModular\ImageStudio\Http\Controllers\ImageStudioAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:image_studio.manage')->group(function () {
    Route::get('/', [ImageStudioAdminController::class, 'index'])->name('index');
    Route::post('process-all', [ImageStudioAdminController::class, 'processAll'])->name('process-all');
    Route::post('retry', [ImageStudioAdminController::class, 'retry'])->name('retry');
    Route::post('revert-all', [ImageStudioAdminController::class, 'revertAll'])->name('revert-all');
    Route::post('images/{image}/process', [ImageStudioAdminController::class, 'process'])->name('process');
    Route::delete('images/{image}', [ImageStudioAdminController::class, 'revert'])->name('revert');
    Route::put('removebg-key', [ImageStudioAdminController::class, 'removeBgKey'])->name('removebg-key');
});
