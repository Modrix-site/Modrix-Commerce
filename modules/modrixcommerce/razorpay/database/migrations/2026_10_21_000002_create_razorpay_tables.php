<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('razorpay_payment_contexts', function (Blueprint $table) {
            $table->unsignedBigInteger('payment_id')->primary();
            $table->string('order_id', 64)->nullable()->unique();
            $table->string('receipt', 40)->unique();
            $table->string('key_id', 64);
            $table->boolean('test_mode');
            $table->string('secret_hash', 64);
        });
        Schema::create('razorpay_refund_requests', function (Blueprint $table) {
            $table->string('key_hash', 64)->primary();
            $table->unsignedBigInteger('payment_id');
            $table->string('amount', 30);
            $table->string('currency', 3);
            $table->string('status', 20);
            $table->string('result_reference', 64)->nullable();
            $table->string('result_status', 20)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('razorpay_refund_requests');
        Schema::dropIfExists('razorpay_payment_contexts');
    }
};
