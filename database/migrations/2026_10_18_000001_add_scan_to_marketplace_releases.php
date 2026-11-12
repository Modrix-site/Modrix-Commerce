<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_releases', function (Blueprint $table) {
            // Static scan findings shown to the reviewer (Phase 8.2).
            $table->json('scan')->nullable()->after('manifest');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_releases', function (Blueprint $table) {
            $table->dropColumn('scan');
        });
    }
};
