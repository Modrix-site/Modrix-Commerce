<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_blocked_ips', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45)->unique();
            $table->unsignedTinyInteger('cidr_mask')->nullable();
            $table->string('reason', 255);
            $table->string('attack_type', 60)->default('manual_block');
            $table->string('threat_level', 20)->default('high');
            $table->boolean('auto_blocked')->default(false);
            $table->unsignedInteger('hit_count')->default(0);
            $table->timestamp('expires_at')->nullable()->index();
            $table->string('created_by', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_blocked_ips');
    }
};
