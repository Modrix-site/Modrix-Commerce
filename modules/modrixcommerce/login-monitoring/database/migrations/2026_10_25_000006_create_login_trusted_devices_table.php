<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_trusted_devices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('device_fingerprint', 64)->index();
            $table->string('device_name', 100);
            $table->string('browser', 50)->nullable();
            $table->string('operating_system', 50)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('last_used_at')->useCurrent();
            $table->boolean('is_revoked')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'device_fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_trusted_devices');
    }
};
