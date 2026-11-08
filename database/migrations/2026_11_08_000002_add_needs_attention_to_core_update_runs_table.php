<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Set on a failed run whose automatic rollback could not undo every
     * change: the store needs manual recovery from the run's backups.
     */
    public function up(): void
    {
        Schema::table('core_update_runs', function (Blueprint $table) {
            $table->boolean('needs_attention')->default(false)->after('code_backup_path');
        });
    }

    public function down(): void
    {
        Schema::table('core_update_runs', function (Blueprint $table) {
            $table->dropColumn('needs_attention');
        });
    }
};
