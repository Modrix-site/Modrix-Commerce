<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_traffic_logs', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45)->index();
            $table->string('request_uri', 500);
            $table->string('method', 10)->default('GET');
            $table->text('user_agent')->nullable();
            $table->unsignedSmallInteger('status_code')->default(200);
            $table->unsignedInteger('response_time_ms')->default(0);
            $table->unsignedTinyInteger('bot_score')->default(0)->index();
            $table->boolean('is_bot')->default(false)->index();
            $table->string('classification', 30)->default('human');
            $table->string('action', 30)->default('allow');
            $table->string('country_code', 3)->nullable()->index();
            $table->string('referer', 500)->nullable();
            $table->timestamps();

            $table->index(['created_at', 'is_bot']);
            $table->index(['ip_address', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_traffic_logs');
    }
};
