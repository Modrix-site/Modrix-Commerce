<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What the merchant did with each package found on disk (ADR-028).
        Schema::create('extensions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique(); // vendor/name
            $table->string('type', 16)->default('module');
            $table->string('display_name', 120);
            $table->string('description', 500)->nullable();
            $table->string('state', 20)->index();
            $table->string('version', 64)->nullable(); // installed version; null when not installed
            $table->string('available_version', 64)->nullable(); // version of the files on disk
            $table->string('path', 255); // relative to the modules directory
            $table->json('manifest')->nullable(); // last validated manifest from disk
            $table->json('errors')->nullable(); // validation errors when invalid
            $table->boolean('enabled_before_update')->default(false);
            $table->text('failure')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('enabled_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
        });

        // Append-only lifecycle log.
        Schema::create('extension_events', function (Blueprint $table) {
            $table->id();
            $table->string('extension', 100)->index();
            $table->string('action', 32)->index();
            $table->string('from_state', 20)->nullable();
            $table->string('to_state', 20)->nullable();
            $table->string('version', 64)->nullable();
            $table->boolean('success')->default(true);
            $table->text('message')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extension_events');
        Schema::dropIfExists('extensions');
    }
};
