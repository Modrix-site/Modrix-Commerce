<?php

use EcoModular\AiAssistant\Http\AiAssistantController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:settings.manage')->group(function () {
    Route::get('/', [AiAssistantController::class, 'index'])->name('index');
    Route::put('settings', [AiAssistantController::class, 'update'])->name('settings');
    Route::post('test', [AiAssistantController::class, 'test'])->middleware('throttle:6,1')->name('test');
    Route::post('models', [AiAssistantController::class, 'models'])->middleware('throttle:6,1')->name('models');
});
Route::post('draft', [AiAssistantController::class, 'draft'])
    ->middleware(['can:products.update', 'throttle:6,1'])->name('draft');
