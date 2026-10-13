<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Third-party sellers on the marketplace server (Phase 8.4): applications,
 * publisher members, commission and payout accounting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_publishers', function (Blueprint $table) {
            $table->string('status', 16)->default('active')->after('is_platform'); // active | suspended
            $table->decimal('commission_percent', 5, 2)->default(0)->after('status'); // kept by the platform
            $table->string('legal_name', 160)->nullable()->after('commission_percent');
            $table->char('country', 2)->nullable()->after('legal_name');
            $table->string('website', 255)->nullable()->after('country');
            $table->string('payout_email', 191)->nullable()->after('website');
            $table->string('support_email', 191)->nullable()->after('payout_email');
            $table->timestamp('verified_at')->nullable()->after('support_email');
        });

        Schema::table('marketplace_products', function (Blueprint $table) {
            $table->string('support_email', 191)->nullable()->after('documentation_url');
            $table->string('support_url', 500)->nullable()->after('support_email');
        });

        Schema::create('marketplace_publisher_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publisher_id')->constrained('marketplace_publishers')->cascadeOnDelete();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete(); // one publisher per account
            $table->string('role', 16)->default('owner'); // owner | member
            $table->timestamps();
        });

        Schema::create('marketplace_seller_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('publisher_name', 120);
            $table->string('vendor_slug', 60); // package vendor prefix, e.g. "acme"
            $table->string('legal_name', 160);
            $table->char('country', 2);
            $table->string('website', 255)->nullable();
            $table->string('payout_email', 191);
            $table->string('support_email', 191);
            $table->text('description');
            $table->string('status', 16)->index(); // pending | approved | rejected
            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('publisher_id')->nullable()->constrained('marketplace_publishers')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('marketplace_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publisher_id')->constrained('marketplace_publishers')->restrictOnDelete();
            $table->char('currency', 3);
            $table->decimal('amount', 19, 4);
            $table->string('reference', 120); // bank or payment reference
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at');
            $table->timestamps();
        });

        // Append-only: every sale, refund and payout for a third-party publisher.
        Schema::create('marketplace_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publisher_id')->constrained('marketplace_publishers')->restrictOnDelete();
            $table->string('type', 16); // sale | refund | payout | adjustment
            $table->foreignId('order_item_id')->nullable()->constrained('marketplace_order_items')->restrictOnDelete();
            $table->foreignId('payout_id')->nullable()->constrained('marketplace_payouts')->restrictOnDelete();
            $table->char('currency', 3);
            $table->decimal('gross', 19, 4);      // signed
            $table->decimal('commission', 19, 4); // signed, kept by the platform
            $table->decimal('net', 19, 4);        // signed, owed to the publisher
            $table->string('description', 255);
            $table->timestamp('created_at');
            $table->index(['publisher_id', 'currency']);
            $table->unique(['order_item_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_ledger_entries');
        Schema::dropIfExists('marketplace_payouts');
        Schema::dropIfExists('marketplace_seller_applications');
        Schema::dropIfExists('marketplace_publisher_members');
        Schema::table('marketplace_products', function (Blueprint $table) {
            $table->dropColumn(['support_email', 'support_url']);
        });
        Schema::table('marketplace_publishers', function (Blueprint $table) {
            $table->dropColumn(['status', 'commission_percent', 'legal_name', 'country', 'website', 'payout_email', 'support_email', 'verified_at']);
        });
    }
};
