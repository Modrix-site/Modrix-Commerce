<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketplace client tables (every merchant store): licenses the merchant
 * activated and the history of installs, updates and rollbacks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_licenses', function (Blueprint $table) {
            $table->id();
            $table->string('product_slug', 120)->unique();
            $table->string('product_name', 160);
            $table->string('extension_name', 100);
            $table->string('type', 16);
            $table->text('key_encrypted');
            $table->string('key_hint', 8);
            $table->string('status', 16); // as reported by the marketplace
            $table->timestamp('updates_until')->nullable();
            $table->json('token')->nullable(); // last signed license token
            $table->timestamp('token_valid_until')->nullable();
            $table->json('available_release')->nullable(); // newest entitled release, from the last check
            $table->timestamp('last_checked_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamps();
        });

        // Append-only.
        Schema::create('marketplace_update_history', function (Blueprint $table) {
            $table->id();
            $table->string('extension_name', 100)->index();
            $table->string('type', 16);
            $table->string('action', 16); // install | update | rollback
            $table->string('from_version', 64)->nullable();
            $table->string('to_version', 64)->nullable();
            $table->boolean('success');
            $table->string('message', 1000)->nullable();
            $table->string('backup_path', 255)->nullable();
            $table->char('sha256', 64)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_update_history');
        Schema::dropIfExists('marketplace_licenses');
    }
};
