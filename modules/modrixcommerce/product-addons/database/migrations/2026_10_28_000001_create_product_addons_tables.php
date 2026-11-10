<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_addons')) {
            Schema::create('product_addons', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('type', 32)->default('warranty');
                $table->string('badge', 64)->nullable();
                $table->string('description', 500)->nullable();
                $table->decimal('price', 12, 4)->default(0);
                $table->string('target_type', 32)->default('all');
                $table->json('target_ids')->nullable();
                $table->integer('priority')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['is_active', 'priority']);
            });
        }

        if (! Schema::hasTable('product_addon_cart_items')) {
            Schema::create('product_addon_cart_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('cart_id')->constrained('carts')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('addon_id')->constrained('product_addons')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['cart_id', 'product_id', 'addon_id']);
            });
        }

        if (! Schema::hasTable('product_addon_orders')) {
            Schema::create('product_addon_orders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('addon_id')->nullable()->constrained('product_addons')->nullOnDelete();
                $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
                $table->string('addon_name');
                $table->string('addon_type', 32)->default('warranty');
                $table->unsignedInteger('quantity')->default(1);
                $table->decimal('unit_price', 12, 4)->default(0);
                $table->decimal('total_price', 12, 4)->default(0);
                $table->string('currency', 3);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_addon_orders');
        Schema::dropIfExists('product_addon_cart_items');
        Schema::dropIfExists('product_addons');
    }
};
