<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abandoned_carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->nullable()->constrained('carts')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email', 191)->index();
            $table->string('customer_name', 120)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('recovery_token', 64)->unique();
            $table->char('currency', 3);
            $table->decimal('subtotal', 19, 4);
            $table->unsignedInteger('items_count')->default(0);
            $table->json('items_snapshot');
            $table->string('status', 32)->default('abandoned'); // abandoned | in_recovery | recovered | lost | unsubscribed
            $table->string('applied_coupon_code', 64)->nullable();
            $table->timestamp('abandoned_at')->index();
            $table->timestamp('recovered_at')->nullable();
            $table->foreignId('recovered_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->decimal('recovery_revenue', 19, 4)->nullable();
            $table->unsignedInteger('emails_sent_count')->default(0);
            $table->timestamp('last_email_sent_at')->nullable();
            $table->timestamp('next_email_scheduled_at')->nullable()->index();
            $table->timestamp('clicked_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['status', 'abandoned_at']);
        });

        Schema::create('abandoned_cart_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('abandoned_cart_id')->constrained('abandoned_carts')->cascadeOnDelete();
            $table->unsignedTinyInteger('step'); // 1, 2, 3
            $table->string('recipient_email', 191);
            $table->string('subject', 255);
            $table->string('discount_code', 64)->nullable();
            $table->string('status', 20)->default('sent'); // sent | failed
            $table->timestamp('sent_at')->index();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('abandoned_cart_unsubscribes', function (Blueprint $table) {
            $table->id();
            $table->string('email', 191)->unique();
            $table->string('reason', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abandoned_cart_emails');
        Schema::dropIfExists('abandoned_carts');
        Schema::dropIfExists('abandoned_cart_unsubscribes');
    }
};
