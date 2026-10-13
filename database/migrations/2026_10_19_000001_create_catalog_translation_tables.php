<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Storefront translations of products and categories. The base row holds the
 * store's default language; a missing or empty translated field falls back
 * to it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 8);
            $table->string('name', 191)->nullable();
            $table->string('short_description', 500)->nullable();
            $table->text('description')->nullable();
            $table->string('meta_title', 191)->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'locale']);
            $table->index(['locale', 'name']);
        });

        Schema::create('category_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 8);
            $table->string('name', 160)->nullable();
            $table->text('description')->nullable();
            $table->string('meta_title', 191)->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->timestamps();
            $table->unique(['category_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_translations');
        Schema::dropIfExists('product_translations');
    }
};
