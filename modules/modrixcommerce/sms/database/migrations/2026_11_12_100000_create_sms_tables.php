<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One mobile number per customer account. `verified_at` null means the
        // customer registered but has not entered the code yet (they are held on
        // the verification page). `pending_phone` is a new number awaiting its code.
        Schema::create('sms_phones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('phone', 20)->index();
            $table->timestamp('verified_at')->nullable();
            $table->string('pending_phone', 20)->nullable();
            $table->boolean('order_updates')->default(true);
            $table->timestamps();
        });

        Schema::create('sms_verification_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('phone', 20);
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('order_id')->nullable();
            // order_placed, order_paid, … ; null for verification codes and tests.
            $table->string('event', 40)->nullable();
            $table->string('purpose', 20);
            $table->string('to', 20);
            $table->string('body', 1000);
            $table->string('provider', 40);
            // pending, sent, failed
            $table->string('status', 20);
            $table->string('provider_message_id', 120)->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamps();
            // An order update is texted at most once, even if its event repeats.
            $table->unique(['order_id', 'event']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_messages');
        Schema::dropIfExists('sms_verification_codes');
        Schema::dropIfExists('sms_phones');
    }
};
