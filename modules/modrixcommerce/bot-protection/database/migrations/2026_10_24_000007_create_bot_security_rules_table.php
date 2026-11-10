<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_security_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('rule_type', 40); // rate_limit, path_pattern, user_agent, country, asn, behavioral, honeypot
            $table->json('conditions');
            $table->string('action', 20)->default('block'); // block, challenge, throttle, monitor
            $table->integer('priority')->default(10);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('hits_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_security_rules');
    }
};
