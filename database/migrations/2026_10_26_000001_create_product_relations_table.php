<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Merchant-picked related products and upsells, in order.
        Schema::create('product_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('related_product_id')->constrained('products')->cascadeOnDelete();
            $table->string('type', 16);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['product_id', 'related_product_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_relations');
    }
};
