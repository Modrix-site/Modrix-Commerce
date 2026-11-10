<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_mfa_configurations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->boolean('mfa_enabled')->default(false)->index();
            $table->string('mfa_type', 30)->default('authenticator'); // authenticator, email_otp, sms_otp
            $table->string('secret_key', 255)->nullable();
            $table->json('recovery_codes')->nullable();
            $table->string('phone_number', 30)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_mfa_configurations');
    }
};
