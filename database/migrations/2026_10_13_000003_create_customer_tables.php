<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            // Null for guest customers. One customer profile per user account.
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            // Not unique: guest checkouts may reuse an email until the owner verifies an account.
            $table->string('email', 191)->index();
            $table->string('first_name', 120)->nullable();
            $table->string('last_name', 120)->nullable();
            $table->string('phone', 40)->nullable();
            $table->boolean('accepts_marketing')->default(false);
            $table->timestamps();
        });

        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('first_name', 120);
            $table->string('last_name', 120);
            $table->string('company', 160)->nullable();
            $table->string('line1', 191);
            $table->string('line2', 191)->nullable();
            $table->string('city', 120);
            $table->string('region', 120)->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->char('country_code', 2);
            $table->string('phone', 40)->nullable();
            $table->boolean('is_default_shipping')->default(false);
            $table->boolean('is_default_billing')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('customers');
    }
};
