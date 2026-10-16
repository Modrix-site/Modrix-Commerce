<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Delivered as downloads: no shipping step for all-digital carts.
            $table->boolean('is_digital')->default(false)->after('is_featured');
        });

        // Files customers download after paying; stored on the private disk.
        Schema::create('product_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('disk', 32);
            $table->string('path');
            $table->unsignedBigInteger('size');
            $table->string('mime', 120)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_files');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_digital');
        });
    }
};
