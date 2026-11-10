<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_captcha_challenges', function (Blueprint $table) {
            $table->id();
            $table->string('challenge_token', 64)->unique();
            $table->string('ip_address', 45)->index();
            $table->text('user_agent')->nullable();
            $table->string('session_id', 100)->nullable();
            $table->string('captcha_type', 30)->default('custom_interactive');
            $table->string('status', 20)->default('pending')->index();
            $table->text('target_url')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('solved_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_captcha_challenges');
    }
};
