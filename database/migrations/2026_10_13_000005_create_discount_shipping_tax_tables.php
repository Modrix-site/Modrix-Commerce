<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('description', 255)->nullable();
            $table->string('type', 16); // percentage | fixed
            $table->decimal('value', 19, 4);
            $table->char('currency', 3)->nullable(); // required for fixed-amount coupons
            $table->decimal('min_subtotal', 19, 4)->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_limit_per_customer')->nullable();
            $table->unsignedInteger('times_used')->default(0);
            $table->string('applies_to', 16)->default('all'); // all | products | categories
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['starts_at', 'ends_at']);
        });

        Schema::create('coupon_products', function (Blueprint $table) {
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['coupon_id', 'product_id']);
        });

        Schema::create('coupon_categories', function (Blueprint $table) {
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['coupon_id', 'category_id']);
        });

        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 120);
            $table->string('description', 255)->nullable();
            $table->decimal('rate', 19, 4);
            $table->char('currency', 3);
            $table->decimal('free_over', 19, 4)->nullable();
            // ISO country codes this method ships to; null means everywhere.
            $table->json('countries')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->char('country_code', 2);
            $table->string('region', 120)->nullable();
            $table->decimal('rate', 8, 4); // percent, e.g. 12.0000
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['country_code', 'region']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('shipping_methods');
        Schema::dropIfExists('coupon_categories');
        Schema::dropIfExists('coupon_products');
        Schema::dropIfExists('coupons');
    }
};
