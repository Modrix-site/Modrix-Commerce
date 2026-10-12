<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marketplace server tables (used only when the marketplace server role is
 * enabled). Kept apart from merchant orders and payments (spec 12.4).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Version 1 has one publisher, the platform owner; vendors come later (spec 12.5).
        Schema::create('marketplace_publishers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 120)->unique();
            $table->boolean('is_platform')->default(false);
            $table->timestamps();
        });

        Schema::create('marketplace_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publisher_id')->constrained('marketplace_publishers')->restrictOnDelete();
            $table->string('slug', 120)->unique();
            $table->string('extension_name', 100)->index(); // vendor/name of the package
            $table->string('type', 16); // module | theme
            $table->string('name', 160);
            $table->string('summary', 500)->nullable();
            $table->text('description')->nullable();
            $table->string('documentation_url', 500)->nullable();
            $table->decimal('price', 19, 4)->default(0);
            $table->char('currency', 3);
            $table->string('status', 16)->index(); // draft | published | retired
            $table->unsignedSmallInteger('updates_months')->default(12);
            $table->unsignedInteger('activation_limit')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['type', 'extension_name']);
        });

        Schema::create('marketplace_releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('marketplace_products')->restrictOnDelete();
            $table->string('version', 64);
            $table->string('platform_version', 100);
            $table->text('changelog')->nullable();
            $table->string('status', 16)->index(); // pending_review | published | rejected | withdrawn
            $table->string('archive_path', 255);
            $table->char('sha256', 64);
            $table->unsignedBigInteger('size');
            $table->json('manifest');
            $table->json('signed_metadata')->nullable();
            $table->string('signature', 200)->nullable();
            $table->string('review_notes', 1000)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['product_id', 'version']);
        });

        Schema::create('marketplace_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32)->nullable()->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('email', 191);
            $table->string('status', 16)->index(); // pending | paid | refunded | cancelled
            $table->char('currency', 3);
            $table->decimal('total', 19, 4);
            $table->string('stripe_session_id', 191)->nullable()->unique();
            $table->string('stripe_payment_intent', 191)->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('marketplace_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('marketplace_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('marketplace_products')->restrictOnDelete();
            $table->string('product_name', 160);
            $table->string('product_type', 16);
            $table->decimal('unit_price', 19, 4);
            $table->unsignedBigInteger('license_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('marketplace_products')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('marketplace_order_items')->nullOnDelete();
            $table->char('key_hash', 64)->unique();
            $table->text('key_encrypted');
            $table->string('key_hint', 8);
            $table->string('status', 16)->index(); // active | expired | revoked | refunded | suspended
            $table->unsignedInteger('activation_limit')->nullable();
            $table->timestamp('updates_until')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('status_reason', 500)->nullable();
            $table->timestamp('issued_at');
            $table->timestamps();
        });

        Schema::create('license_activations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained()->cascadeOnDelete();
            $table->string('instance_id', 64);
            $table->string('domain', 191)->nullable();
            $table->string('platform_version', 32)->nullable();
            $table->timestamp('activated_at');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->index(['license_id', 'instance_id']);
        });

        // Append-only license audit trail.
        Schema::create('license_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained()->cascadeOnDelete();
            $table->string('action', 32)->index();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('instance_id', 64)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('download_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained()->cascadeOnDelete();
            $table->foreignId('release_id')->constrained('marketplace_releases')->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->string('instance_id', 64)->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        DB::table('marketplace_publishers')->insert([
            'name' => 'Eleydo', 'slug' => 'eleydo', 'is_platform' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        foreach (['download_tokens', 'license_events', 'license_activations', 'licenses', 'marketplace_order_items', 'marketplace_orders', 'marketplace_releases', 'marketplace_products', 'marketplace_publishers'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
