<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_attributes', function (Blueprint $table) {
            $table->string('display_type', 20)->default('buttons');
        });
        Schema::table('product_attribute_values', function (Blueprint $table) {
            $table->string('color', 7)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('product_attribute_values', fn (Blueprint $table) => $table->dropColumn('color'));
        Schema::table('product_attributes', fn (Blueprint $table) => $table->dropColumn('display_type'));
    }
};
