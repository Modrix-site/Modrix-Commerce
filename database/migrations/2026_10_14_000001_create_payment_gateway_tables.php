<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Gateway payments: `provider_reference` is the session id, this is the charge/payment intent id.
            $table->string('provider_payment_id', 191)->nullable()->index();
            $table->string('checkout_url', 2048)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->index(['status', 'created_at']);
        });

        Schema::table('refunds', function (Blueprint $table) {
            $table->string('failure_reason', 500)->nullable();
            $table->index('provider_reference');
        });

        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->string('event_id', 191);
            $table->string('type', 120)->index();
            $table->json('payload');
            $table->string('status', 16)->index(); // received | processed | ignored | failed
            $table->unsignedInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('received_at')->index();
            $table->timestamp('processed_at')->nullable();
            $table->unique(['provider', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');

        Schema::table('refunds', function (Blueprint $table) {
            $table->dropIndex(['provider_reference']);
            $table->dropColumn('failure_reason');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            $table->dropIndex(['provider_payment_id']);
            $table->dropColumn(['provider_payment_id', 'checkout_url', 'expires_at', 'failure_reason']);
        });
    }
};
