<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_user_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 100)->unique();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('ip_address', 45)->index();
            $table->text('user_agent')->nullable();
            $table->string('device_type', 30)->default('desktop');
            $table->string('browser', 50)->nullable();
            $table->string('operating_system', 50)->nullable();
            $table->string('country_code', 3)->nullable();
            $table->string('city', 100)->nullable();
            $table->boolean('is_current')->default(true);
            $table->boolean('is_revoked')->default(false)->index();
            $table->timestamp('last_activity')->useCurrent()->index();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_user_sessions');
    }
};
