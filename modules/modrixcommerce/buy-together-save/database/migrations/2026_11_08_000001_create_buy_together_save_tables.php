<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buy_together_deals', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->string('badge_text', 100)->nullable();
            $table->text('description')->nullable();
            $table->string('trigger_type', 20)->default('product'); // product | category | all
            $table->json('trigger_ids'); // array of product_ids or category_ids
            $table->json('bundle_product_ids'); // array of companion product_ids
            $table->string('discount_type', 20)->default('percent'); // percent | fixed
            $table->decimal('discount_value', 19, 4)->default(10.0000);
            $table->unsignedInteger('min_items_required')->default(2);
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedBigInteger('views_count')->default(0);
            $table->unsignedBigInteger('conversions_count')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'priority']);
        });

        Schema::create('buy_together_cart_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('variant_id');
            $table->foreignId('deal_id')->nullable()->constrained('buy_together_deals')->nullOnDelete();
            $table->string('bundle_group_id', 50);
            $table->boolean('is_trigger')->default(false);
            $table->timestamps();

            $table->index(['cart_id', 'variant_id']);
            $table->index(['cart_id', 'bundle_group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buy_together_cart_lines');
        Schema::dropIfExists('buy_together_deals');
    }
};
