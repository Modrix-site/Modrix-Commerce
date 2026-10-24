<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Licenses, activations, orders, download tokens and webhook events. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->string('key', 64)->unique();
            $table->string('type', 16); // free | paid
            $table->string('status', 16)->default('active'); // active | revoked | refunded
            $table->timestamps();
            // One stable key per customer and package.
            $table->unique(['user_id', 'package_id']);
        });

        Schema::create('license_activations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained()->cascadeOnDelete();
            $table->string('instance_id', 128);
            $table->string('domain', 255)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('activated_at')->useCurrent(); // Explicit default: no implicit ON UPDATE on older MySQL.
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();
            $table->unique(['license_id', 'instance_id']);
            $table->index(['license_id', 'active']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3);
            $table->string('stripe_session', 191)->nullable()->unique();
            $table->string('stripe_payment_intent', 191)->nullable()->index();
            $table->string('status', 16)->default('pending'); // pending | paid | expired | refunded
            $table->foreignId('license_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('download_tokens', function (Blueprint $table) {
            $table->char('token_hash', 64)->primary();
            $table->foreignId('license_id')->constrained()->cascadeOnDelete();
            $table->foreignId('release_id')->constrained('package_releases')->cascadeOnDelete();
            $table->string('instance_id', 128);
            $table->timestamp('expires_at')->useCurrent()->index(); // Explicit default: no implicit ON UPDATE.
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->string('event_id', 191);
            $table->string('type', 120);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'event_id']);
        });
    }

    public function down(): void
    {
        foreach (['webhook_events', 'download_tokens', 'orders', 'license_activations', 'licenses'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
