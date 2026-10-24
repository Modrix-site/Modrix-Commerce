<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Listing details the catalog shows (from each package's manifest). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->string('author', 120)->default('');
            $table->string('license_name', 80)->default('MIT');
            // Declared version and platform compatibility, shown before a release exists.
            $table->string('version', 32)->default('1.0.0');
            $table->string('platform_version', 80)->default('^1.0');
            $table->text('changelog')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['author', 'license_name', 'version', 'platform_version', 'changelog']);
        });
    }
};
