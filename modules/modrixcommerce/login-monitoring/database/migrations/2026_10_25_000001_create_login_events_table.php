<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_uuid', 64)->unique();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('email', 255)->index();
            $table->string('ip_address', 45)->index();
            $table->string('status', 20)->default('success')->index(); // success, failed, blocked, mfa_required
            $table->string('failure_reason', 255)->nullable();
            $table->unsignedTinyInteger('risk_score')->default(0)->index();
            $table->string('threat_level', 20)->default('low'); // low, medium, high, critical
            $table->string('device_fingerprint', 64)->nullable()->index();
            $table->string('device_type', 30)->default('desktop');
            $table->string('browser', 50)->nullable();
            $table->string('operating_system', 50)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('country_code', 3)->nullable()->index();
            $table->string('city', 100)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_suspicious')->default(false)->index();
            $table->json('suspicious_reasons')->nullable();
            $table->string('session_id', 100)->nullable()->index();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['created_at', 'status']);
            $table->index(['email', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_events');
    }
};
