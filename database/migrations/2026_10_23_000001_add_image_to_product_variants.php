<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->foreignId('image_id')->nullable()->constrained('product_images')->nullOnDelete();
        });

        // Preserve any images previously associated through the gallery.
        foreach (DB::table('product_images')->whereNotNull('variant_id')->orderBy('position')->orderBy('id')->cursor() as $image) {
            DB::table('product_variants')->where('id', $image->variant_id)->where('product_id', $image->product_id)->whereNull('image_id')->update(['image_id' => $image->id]);
        }
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('image_id');
        });
    }
};
