<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_scores', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45)->unique();
            $table->string('session_id', 100)->nullable()->index();
            $table->string('fingerprint_hash', 64)->nullable()->index();
            $table->unsignedTinyInteger('overall_score')->default(0)->index();
            $table->string('threat_level', 20)->default('low');
            $table->unsignedInteger('total_requests')->default(1);
            $table->unsignedInteger('violation_count')->default(0);
            $table->json('heuristic_factors')->nullable();
            $table->string('country_code', 3)->nullable();
            $table->timestamp('first_seen_at')->useCurrent();
            $table->timestamp('last_seen_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_scores');
    }
};
