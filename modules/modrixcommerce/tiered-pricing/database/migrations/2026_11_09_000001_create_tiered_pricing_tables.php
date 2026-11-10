<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tiered_pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->string('applies_to', 20)->default('product'); // product | category | all
            $table->json('target_ids'); // array of product_ids or category_ids
            $table->string('discount_type', 20)->default('percent'); // percent | fixed_per_unit
            $table->json('tiers'); // array of {min_qty, max_qty, discount}
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiered_pricing_rules');
    }
};
