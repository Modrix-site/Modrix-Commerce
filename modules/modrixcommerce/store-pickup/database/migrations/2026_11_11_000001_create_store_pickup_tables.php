<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pickup_branches', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('code', 60)->unique();
            $table->string('address_line1', 255);
            $table->string('address_line2', 255)->nullable();
            $table->string('city', 100);
            $table->string('region', 100)->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->string('country_code', 2)->default('AE');
            $table->string('phone', 50)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('pickup_hours', 255)->nullable();
            $table->text('instructions')->nullable();
            $table->string('prep_time', 100)->nullable();
            $table->decimal('fee', 19, 4)->default(0.0000);
            $table->boolean('is_active')->default(true);
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'order']);
        });

        Schema::create('order_pickups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('pickup_branch_id')->nullable()->constrained('pickup_branches')->nullOnDelete();
            $table->string('branch_name', 120);
            $table->text('branch_address');
            $table->string('branch_phone', 50)->nullable();
            $table->string('pickup_hours', 255)->nullable();
            $table->string('collector_name', 120)->nullable();
            $table->string('collector_phone', 50)->nullable();
            $table->date('pickup_date')->nullable();
            $table->string('pickup_time', 50)->nullable();
            $table->string('status', 30)->default('pending_prep'); // pending_prep, ready_for_pickup, picked_up, cancelled
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->text('instructions')->nullable();
            $table->text('customer_notes')->nullable();
            $table->timestamps();

            $table->unique('order_id');
            $table->index(['status', 'created_at']);
            $table->index('pickup_branch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_pickups');
        Schema::dropIfExists('pickup_branches');
    }
};
