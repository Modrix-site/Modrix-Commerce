<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_memos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('refund_id')->unique()->constrained('refunds')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('number', 40)->unique();
            $table->unsignedBigInteger('sequence');
            $table->char('currency', 3);
            $table->decimal('subtotal', 19, 4)->default(0);
            $table->decimal('tax_total', 19, 4)->default(0);
            $table->decimal('shipping_refund', 19, 4)->default(0);
            $table->decimal('adjustment_amount', 19, 4)->default(0);
            $table->decimal('grand_total', 19, 4);
            $table->json('seller');
            $table->json('buyer');
            $table->json('lines');
            $table->json('totals');
            $table->text('notes')->nullable();
            $table->timestamp('issued_at');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['order_id', 'issued_at']);
        });

        Schema::create('refund_payment_tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('refund_id')->unique()->constrained('refunds')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('channel', 40);
            $table->string('payout_status', 24)->index();
            $table->string('reference', 191)->nullable();
            $table->string('destination', 191)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        if (Schema::hasTable('document_counters')) {
            DB::table('document_counters')->insertOrIgnore([
                'name' => 'credit_memo',
                'next' => 1,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_payment_tracks');
        Schema::dropIfExists('credit_memos');
    }
};
