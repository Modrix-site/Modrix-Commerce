<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_proof_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 40)->default('recent_purchase')->index();
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->string('product_name');
            $table->string('product_image_url')->nullable();
            $table->string('product_url')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_city')->nullable();
            $table->string('customer_country', 10)->nullable();
            $table->text('message')->nullable();
            $table->timestamp('occurred_at')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_simulated')->default(false)->index();
            $table->unsignedInteger('impressions_count')->default(0);
            $table->unsignedInteger('clicks_count')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'occurred_at']);
        });

        Schema::create('social_proof_live_viewers', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->index();
            $table->string('session_id', 64);
            $table->timestamp('last_seen_at')->index();

            $table->primary(['product_id', 'session_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_proof_live_viewers');
        Schema::dropIfExists('social_proof_events');
    }
};
