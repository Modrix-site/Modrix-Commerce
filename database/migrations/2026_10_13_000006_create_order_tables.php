<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orders keep complete snapshots (items, prices, taxes, discounts, addresses)
 * so later catalog or customer changes never alter financial history.
 * Foreign keys to catalog/customer rows are nulled, never cascaded, on delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32)->nullable()->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email', 191)->index();
            $table->string('phone', 40)->nullable();
            $table->char('currency', 3);
            $table->string('status', 20)->index();
            $table->string('payment_status', 24)->index();
            $table->string('fulfillment_status', 20)->index();
            $table->decimal('subtotal', 19, 4);
            $table->decimal('discount_total', 19, 4)->default(0);
            $table->decimal('shipping_total', 19, 4)->default(0);
            $table->decimal('shipping_tax_total', 19, 4)->default(0);
            $table->decimal('tax_total', 19, 4)->default(0);
            $table->decimal('grand_total', 19, 4);
            $table->decimal('paid_total', 19, 4)->default(0);
            $table->decimal('refunded_total', 19, 4)->default(0);
            $table->boolean('prices_include_tax')->default(false);
            $table->string('coupon_code', 64)->nullable();
            $table->string('shipping_method_code', 64)->nullable();
            $table->string('shipping_method_name', 120)->nullable();
            $table->string('payment_method_code', 64);
            $table->string('payment_method_name', 120);
            $table->text('customer_note')->nullable();
            // Unique per checkout attempt: a repeated submission returns the same order.
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('placed_at')->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'placed_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('sku', 64)->index();
            $table->string('product_name', 191);
            $table->string('variant_name', 191)->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 19, 4);
            $table->decimal('subtotal', 19, 4);
            $table->decimal('discount_total', 19, 4)->default(0);
            $table->decimal('tax_rate', 8, 4)->default(0);
            $table->decimal('tax_total', 19, 4)->default(0);
            $table->decimal('total', 19, 4);
            $table->unsignedInteger('restocked_quantity')->default(0);
            $table->timestamps();
        });

        Schema::create('order_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16); // shipping | billing
            $table->string('first_name', 120);
            $table->string('last_name', 120);
            $table->string('company', 160)->nullable();
            $table->string('line1', 191);
            $table->string('line2', 191)->nullable();
            $table->string('city', 120);
            $table->string('region', 120)->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->char('country_code', 2);
            $table->string('phone', 40)->nullable();
            $table->unique(['order_id', 'type']);
        });

        Schema::create('order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32)->index();
            $table->text('message');
            $table->boolean('is_customer_visible')->default(false);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['order_id', 'created_at']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('method_code', 64);
            $table->string('provider', 32)->index();
            $table->string('provider_reference', 191)->nullable();
            $table->decimal('amount', 19, 4);
            $table->char('currency', 3);
            $table->string('status', 24)->index();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_reference']);
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 19, 4);
            $table->char('currency', 3);
            $table->string('reason', 500);
            $table->string('status', 24);
            $table->string('provider_reference', 191)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('refund_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('refund_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->boolean('restocked')->default(false);
        });

        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('carrier', 120)->nullable();
            $table->string('tracking_number', 120)->nullable()->index();
            $table->string('tracking_url', 500)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('shipped_at');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email', 191)->index();
            $table->decimal('amount', 19, 4);
            $table->char('currency', 3);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['coupon_id', 'order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('refund_items');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_events');
        Schema::dropIfExists('order_addresses');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
