<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The store's own wording for storefront texts, per language (overrides shipped translations).
        Schema::create('interface_translations', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 8);
            $table->string('key', 500);
            $table->text('value');
            $table->timestamps();
            $table->unique(['locale', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interface_translations');
    }
};
