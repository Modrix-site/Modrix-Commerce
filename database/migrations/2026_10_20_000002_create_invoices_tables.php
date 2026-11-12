<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('number', 40)->unique();
            $table->unsignedBigInteger('sequence');
            $table->timestamp('issued_at');
            $table->string('currency', 3);
            // Frozen at issue time: later changes to the store, customer or catalog never alter an issued invoice.
            $table->json('seller');
            $table->json('buyer');
            $table->json('lines');
            $table->json('totals');
            $table->text('notes')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Gap-free sequences, advanced under a row lock in the same transaction as the document.
        Schema::create('document_counters', function (Blueprint $table) {
            $table->string('name', 32)->primary();
            $table->unsignedBigInteger('next');
        });

        DB::table('document_counters')->insert(['name' => 'invoice', 'next' => 1]);
    }

    public function down(): void
    {
        Schema::dropIfExists('document_counters');
        Schema::dropIfExists('invoices');
    }
};
