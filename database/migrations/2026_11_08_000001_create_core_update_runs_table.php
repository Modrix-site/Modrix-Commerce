<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per core platform update attempt. The status is written as
     * each stage starts so the admin page can poll it.
     */
    public function up(): void
    {
        Schema::create('core_update_runs', function (Blueprint $table) {
            $table->id();
            $table->string('from_version', 64);
            $table->string('to_version', 64);
            $table->json('release'); // the signed release item (re-verified before install)
            $table->string('status', 16)->index();
            $table->string('message', 1000)->nullable();
            $table->string('backup_name', 255)->nullable();
            $table->string('code_backup_path', 500)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_update_runs');
    }
};
