<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per person and event key, whatever the channels (bell, email),
     * so the same event never notifies anyone twice. The unique index makes
     * the check and the claim one atomic insert.
     */
    public function up(): void
    {
        Schema::create('notification_dedupes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('dedupe', 191);
            $table->timestamp('created_at')->useCurrent()->index();
            $table->unique(['user_id', 'dedupe']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_dedupes');
    }
};
