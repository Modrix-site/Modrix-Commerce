<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_security_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('incident_uuid', 64)->unique();
            $table->string('incident_type', 60)->index(); // brute_force, credential_stuffing, impossible_travel, unfamiliar_device, vpn_proxy_login
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('email', 255)->nullable()->index();
            $table->string('ip_address', 45)->index();
            $table->string('severity', 20)->default('medium'); // low, medium, high, critical
            $table->string('status', 20)->default('open')->index(); // open, investigating, resolved, dismissed
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->string('resolved_by', 100)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->index(['created_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_security_incidents');
    }
};
