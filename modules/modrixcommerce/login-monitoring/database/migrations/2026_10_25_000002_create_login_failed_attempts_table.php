<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_failed_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('email', 255)->index();
            $table->string('ip_address', 45)->index();
            $table->unsignedInteger('attempt_count')->default(1);
            $table->timestamp('last_attempt_at')->useCurrent();
            $table->boolean('is_locked_out')->default(false)->index();
            $table->timestamp('locked_until')->nullable()->index();
            $table->string('lockout_reason', 255)->nullable();
            $table->timestamps();

            $table->unique(['email', 'ip_address']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_failed_attempts');
    }
};
