<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_slots', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('cutoff_hours')->default(2);
            $table->unsignedInteger('max_orders')->default(20);
            $table->decimal('fee', 19, 4)->default(0.0000);
            $table->boolean('is_active')->default(true);
            $table->json('days_of_week'); // [0,1,2,3,4,5,6] (0 = Sunday, 6 = Saturday)
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'order']);
        });

        Schema::create('delivery_blackout_dates', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('reason', 150)->nullable();
            $table->timestamps();
        });

        Schema::create('order_delivery_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('delivery_slot_id')->nullable()->constrained('delivery_slots')->nullOnDelete();
            $table->date('delivery_date');
            $table->string('slot_name', 100);
            $table->text('delivery_instructions')->nullable();
            $table->timestamps();

            $table->unique('order_id');
            $table->index(['delivery_date', 'delivery_slot_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_delivery_schedules');
        Schema::dropIfExists('delivery_blackout_dates');
        Schema::dropIfExists('delivery_slots');
    }
};
