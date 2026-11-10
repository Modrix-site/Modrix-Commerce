<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_uuid', 64)->unique();
            $table->string('ip_address', 45)->index();
            $table->text('user_agent')->nullable();
            $table->string('request_path', 500);
            $table->string('request_method', 10)->default('GET');
            $table->string('bot_type', 60)->index();
            $table->unsignedTinyInteger('risk_score')->default(0)->index();
            $table->string('threat_level', 20)->default('medium');
            $table->string('action_taken', 30)->default('monitor');
            $table->json('reasons');
            $table->json('request_headers')->nullable();
            $table->json('request_payload')->nullable();
            $table->string('session_id', 100)->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('country_code', 3)->nullable()->index();
            $table->string('city', 100)->nullable();
            $table->string('asn', 100)->nullable();
            $table->boolean('is_resolved')->default(false)->index();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolved_by', 100)->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->index(['created_at', 'threat_level']);
            $table->index(['ip_address', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_events');
    }
};
