<?php

use EcoModular\BotProtection\Http\Controllers\BotChallengeController;
use Illuminate\Support\Facades\Route;

// Challenge and Verification routes (prefixed by ModuleLoader with m/modrixcommerce/bot-protection)
Route::get('challenge/{token}', [BotChallengeController::class, 'show'])->name('challenge.show');
Route::post('verify', [BotChallengeController::class, 'verify'])->name('challenge.verify');
Route::get('bot-trap', [BotChallengeController::class, 'honeypotTrap'])->name('honeypot.trap');
