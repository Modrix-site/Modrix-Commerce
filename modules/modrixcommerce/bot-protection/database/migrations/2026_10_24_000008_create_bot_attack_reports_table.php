<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_attack_reports', function (Blueprint $table) {
            $table->id();
            $table->date('report_date')->unique();
            $table->unsignedInteger('total_requests')->default(0);
            $table->unsignedInteger('human_requests')->default(0);
            $table->unsignedInteger('bot_requests')->default(0);
            $table->unsignedInteger('blocked_requests')->default(0);
            $table->unsignedInteger('challenged_requests')->default(0);
            $table->unsignedInteger('solved_challenges')->default(0);
            $table->unsignedInteger('failed_challenges')->default(0);
            $table->unsignedInteger('scraping_attempts')->default(0);
            $table->unsignedInteger('credential_stuffing_attempts')->default(0);
            $table->unsignedInteger('checkout_abuse_attempts')->default(0);
            $table->decimal('avg_bot_score', 5, 2)->default(0);
            $table->json('top_threat_types')->nullable();
            $table->json('top_attacking_ips')->nullable();
            $table->json('top_target_paths')->nullable();
            $table->json('top_countries')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_attack_reports');
    }
};
