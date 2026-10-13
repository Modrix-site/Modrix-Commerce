<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->text('description')->nullable();
            $table->foreignId('logo_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('brand_id')->nullable()->after('type')->constrained()->nullOnDelete();
            $table->boolean('is_featured')->default(false)->after('status')->index();
        });

        // Tags are stored normalised (trimmed, single spaces, lowercase).
        Schema::create('product_tags', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('tag', 60)->index();
            $table->primary(['product_id', 'tag']);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            // Admin-only, in the store currency.
            $table->decimal('cost', 19, 4)->nullable()->after('weight');
            // In the store dimension unit.
            $table->decimal('length', 10, 2)->nullable()->after('cost');
            $table->decimal('width', 10, 2)->nullable()->after('length');
            $table->decimal('height', 10, 2)->nullable()->after('width');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn(['cost', 'length', 'width', 'height']);
        });
        Schema::dropIfExists('product_tags');
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['is_featured']);
            $table->dropConstrainedForeignId('brand_id');
            $table->dropColumn('is_featured');
        });
        Schema::dropIfExists('brands');
    }
};
