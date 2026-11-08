<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('image_studio_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_image_id')->unique()->constrained('product_images')->cascadeOnDelete();
            $table->string('status', 16)->index();
            $table->string('disk');
            $table->string('path')->nullable();
            $table->string('driver', 32)->nullable();
            $table->string('error', 500)->nullable();
            $table->unsignedInteger('bytes_before')->nullable();
            $table->unsignedInteger('bytes_after')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('image_studio_variants');
    }
};
