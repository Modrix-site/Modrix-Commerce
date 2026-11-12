<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_translations', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 8);
            $table->char('source_hash', 64);
            $table->text('source');
            $table->text('value');
            $table->string('origin', 16);
            $table->timestamps();
            $table->unique(['locale', 'source_hash', 'origin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_translations');
    }
};
