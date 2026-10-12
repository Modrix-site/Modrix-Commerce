<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extensions', function (Blueprint $table) {
            // Themes render the manifest the merchant applied, not whatever is on disk (ADR-031).
            $table->json('applied_manifest')->nullable();
            $table->timestamp('applied_at')->nullable();
            // A module and a theme may share a vendor/name.
            $table->dropUnique(['name']);
            $table->unique(['type', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('extensions', function (Blueprint $table) {
            $table->dropUnique(['type', 'name']);
            $table->unique('name');
            $table->dropColumn(['applied_manifest', 'applied_at']);
        });
    }
};
