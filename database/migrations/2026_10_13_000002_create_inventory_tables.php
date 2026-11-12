<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_locations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 160);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('inventory_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('inventory_locations')->restrictOnDelete();
            // Signed: may go below zero only for variants that allow backorders.
            $table->integer('on_hand')->default(0);
            $table->timestamps();
            $table->unique(['variant_id', 'location_id']);
            $table->index(['location_id', 'on_hand']);
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            // Movement history is financial-adjacent: block hard deletes of variants that have history.
            $table->foreignId('variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('inventory_locations')->restrictOnDelete();
            $table->integer('quantity_change');
            $table->integer('quantity_after');
            $table->string('reason', 32)->index();
            $table->nullableMorphs('reference');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['variant_id', 'created_at']);
        });

        DB::table('inventory_locations')->insert([
            'code' => 'main',
            'name' => 'Main warehouse',
            'is_default' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_stocks');
        Schema::dropIfExists('inventory_locations');
    }
};
