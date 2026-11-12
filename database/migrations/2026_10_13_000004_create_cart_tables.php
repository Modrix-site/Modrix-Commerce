<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            // Random identifier kept in the server-side session for guest carts.
            $table->uuid('token')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->char('currency', 3);
            $table->string('coupon_code', 64)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'updated_at']);
            $table->index('updated_at');
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            // Last unit price shown to the customer, used only to announce price changes.
            $table->decimal('seen_unit_price', 19, 4)->nullable();
            $table->timestamps();
            $table->unique(['cart_id', 'variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
