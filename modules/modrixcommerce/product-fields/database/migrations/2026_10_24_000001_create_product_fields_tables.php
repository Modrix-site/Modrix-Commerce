<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_fields', function (Blueprint $table) {
            $table->id();
            // Stable machine name for themes and exports, from the first label.
            $table->string('key', 64)->unique();
            $table->string('label', 120);
            $table->string('type', 16); // text | textarea | number | boolean | date | select | url
            $table->json('options'); // dropdown choices
            $table->string('help', 255)->nullable();
            $table->boolean('required')->default(false);
            $table->boolean('show_on_storefront')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('product_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_field_id')->constrained()->cascadeOnDelete();
            $table->text('value');
            $table->timestamps();
            $table->unique(['product_id', 'product_field_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_field_values');
        Schema::dropIfExists('product_fields');
    }
};
