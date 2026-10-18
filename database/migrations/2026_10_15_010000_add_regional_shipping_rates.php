<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_methods', function (Blueprint $table) {
            $table->json('regional_rates')->nullable();
            $table->boolean('regions_only')->default(false);
            $table->string('delivery_estimate')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('shipping_methods', fn (Blueprint $table) => $table->dropColumn(['regional_rates', 'regions_only', 'delivery_estimate']));
    }
};
