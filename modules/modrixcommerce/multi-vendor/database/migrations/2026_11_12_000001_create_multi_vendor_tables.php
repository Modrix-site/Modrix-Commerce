<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_vendors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('store_name', 191);
            $table->string('slug', 191)->unique();
            $table->string('tagline', 255)->nullable();
            $table->text('description')->nullable();
            $table->string('email', 191);
            $table->string('phone', 64)->nullable();
            $table->string('logo_url', 500)->nullable();
            $table->string('banner_url', 500)->nullable();
            $table->string('status', 32)->default('pending')->index(); // pending, approved, suspended, rejected

            // Business Verification (KYC / KYB)
            $table->string('verification_status', 32)->default('unverified')->index(); // unverified, pending_review, verified, rejected, more_info_required
            $table->string('business_type', 40)->default('company'); // individual, sole_proprietorship, llc, corporation, partnership
            $table->string('legal_business_name', 191)->nullable();
            $table->string('tax_id', 120)->nullable();
            $table->string('trade_license_number', 120)->nullable();
            $table->string('identity_document_ref', 191)->nullable();
            $table->string('business_address', 255)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('country_code', 8)->default('AE');
            $table->text('verification_notes')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();

            // Commission & Payout Preferences
            $table->decimal('commission_rate', 6, 2)->nullable();
            $table->string('payout_method', 40)->default('bank_transfer'); // bank_transfer, paypal, stripe_connect, wise, manual
            $table->boolean('auto_payout_enabled')->default(true);
            $table->string('bank_name', 191)->nullable();
            $table->string('bank_account_name', 191)->nullable();
            $table->string('bank_account_number', 191)->nullable();
            $table->string('bank_swift_code', 64)->nullable();
            $table->string('payout_email', 191)->nullable();

            // Balances & Metrics
            $table->boolean('is_featured')->default(false);
            $table->decimal('rating', 3, 2)->default(5.00);
            $table->decimal('total_sales_amount', 14, 4)->default(0);
            $table->decimal('total_commission_amount', 14, 4)->default(0);
            $table->decimal('available_balance', 14, 4)->default(0);
            $table->decimal('paid_out_total', 14, 4)->default(0);
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('marketplace_vendor_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_id')->index();
            $table->unsignedBigInteger('product_id')->unique();
            $table->string('vendor_sku', 120)->nullable();
            $table->string('approval_status', 32)->default('approved')->index(); // pending, approved, rejected
            $table->decimal('commission_override', 6, 2)->nullable();
            $table->string('rejection_note', 500)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('marketplace_vendor_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_id')->index();
            $table->unsignedBigInteger('order_id')->index();
            $table->unsignedBigInteger('order_item_id')->unique();
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->string('order_number', 64)->nullable();
            $table->string('product_name', 255);
            $table->unsignedInteger('quantity')->default(1);
            $table->string('currency', 8)->default('USD');
            $table->decimal('gross_amount', 14, 4)->default(0);
            $table->decimal('commission_rate', 6, 2)->default(10.00);
            $table->decimal('commission_amount', 14, 4)->default(0);
            $table->decimal('vendor_earning', 14, 4)->default(0);
            $table->string('fulfillment_status', 32)->default('unfulfilled'); // unfulfilled, processing, shipped, delivered, cancelled
            $table->string('payout_status', 32)->default('cleared')->index(); // pending, cleared, paid_out, refunded
            $table->unsignedBigInteger('payout_id')->nullable()->index();
            $table->string('tracking_number', 120)->nullable();
            $table->timestamps();
        });

        Schema::create('marketplace_vendor_payouts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_id')->index();
            $table->string('reference', 64)->unique();
            $table->string('currency', 8)->default('USD');
            $table->decimal('amount', 14, 4);
            $table->decimal('fee_amount', 14, 4)->default(0);
            $table->decimal('net_amount', 14, 4);
            $table->string('payout_method', 40)->default('bank_transfer');
            $table->string('destination_summary', 255)->nullable();
            $table->string('status', 32)->default('pending')->index(); // pending, approved, processing, paid, rejected
            $table->boolean('requested_by_vendor')->default(true);
            $table->string('transaction_reference', 191)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_vendor_payouts');
        Schema::dropIfExists('marketplace_vendor_orders');
        Schema::dropIfExists('marketplace_vendor_products');
        Schema::dropIfExists('marketplace_vendors');
    }
};
