<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cross_sell_offers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('heading', 120)->nullable();
            $table->string('type', 16); // upsell | cross_sell
            $table->string('trigger_type', 16); // any | products | categories
            $table->json('trigger_ids');
            $table->string('suggestion_mode', 16); // manual | automatic
            $table->json('product_ids');
            $table->boolean('show_on_product')->default(true);
            $table->boolean('show_in_popup')->default(true);
            $table->boolean('show_in_cart')->default(true);
            $table->boolean('show_at_checkout')->default(false);
            $table->string('discount_type', 16)->nullable(); // percent | fixed
            $table->decimal('discount_value', 19, 4)->nullable();
            $table->integer('priority')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('views')->default(0);
            $table->unsignedBigInteger('adds')->default(0);
            $table->timestamps();
            $table->index(['is_active', 'priority']);
        });

        // Which cart lines were added from which offer (for offer prices and stats).
        Schema::create('cross_sell_cart_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('variant_id');
            $table->foreignId('offer_id')->constrained('cross_sell_offers')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['cart_id', 'variant_id']);
        });

        Schema::create('cross_sell_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained('cross_sell_offers')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->unsignedInteger('quantity');
            $table->decimal('revenue', 19, 4);
            $table->char('currency', 3);
            $table->timestamp('created_at')->nullable();
            $table->unique(['order_id', 'offer_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cross_sell_conversions');
        Schema::dropIfExists('cross_sell_cart_lines');
        Schema::dropIfExists('cross_sell_offers');
    }
};
