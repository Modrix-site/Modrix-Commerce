<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Security advisories for marketplace products (server role, Phase 8.3).
        Schema::create('marketplace_advisories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('marketplace_products')->cascadeOnDelete();
            $table->string('severity', 16); // low | medium | high | critical
            $table->string('title', 160);
            $table->text('body');
            $table->string('affected_versions', 120); // Composer constraint, e.g. "<1.2.3"
            $table->string('fixed_version', 40)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at');
            $table->timestamps();
            $table->index(['product_id', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_advisories');
    }
};
