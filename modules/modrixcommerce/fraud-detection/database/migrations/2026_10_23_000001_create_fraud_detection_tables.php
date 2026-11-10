<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Fraud Scores ────────────────────────────────────────────
        Schema::create('fraud_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->unsignedTinyInteger('total_score')->default(0); // 0-100
            $table->string('risk_level', 16)->default('low'); // low | medium | high
            $table->string('review_status', 24)->default('pending'); // pending | approved | rejected | under_review
            $table->json('indicators'); // array of {key, label, score, details}
            $table->string('ip_address', 45)->nullable();
            $table->string('customer_email', 191)->nullable()->index();
            $table->char('customer_country', 2)->nullable();
            $table->char('payment_country', 2)->nullable();
            $table->boolean('is_vpn')->default(false);
            $table->boolean('is_proxy')->default(false);
            $table->boolean('is_disposable_email')->default(false);
            $table->boolean('address_mismatch')->default(false);
            $table->unsignedInteger('failed_payment_attempts')->default(0);
            $table->decimal('order_total', 19, 4)->default(0);
            $table->char('currency', 3)->default('USD');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->index(['risk_level', 'review_status']);
            $table->index(['total_score']);
            $table->index(['created_at']);
            $table->index('ip_address');
        });

        // ── Fraud Rules ─────────────────────────────────────────────
        Schema::create('fraud_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('type', 32); // threshold | pattern | country | custom
            $table->string('field', 64); // order_total | email_domain | ip_address | country | etc.
            $table->string('operator', 20); // gt | lt | eq | contains | in | not_in | matches
            $table->text('value'); // comparison value or JSON array
            $table->unsignedTinyInteger('score_impact')->default(10); // 0-100
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('priority')->default(100);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'priority']);
        });

        // ── Fraud Reviews / Investigation Notes ─────────────────────
        Schema::create('fraud_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fraud_score_id')->constrained('fraud_scores')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('action', 24); // approved | rejected | under_review | note | escalated
            $table->text('comment')->nullable();
            $table->string('previous_status', 24)->nullable();
            $table->string('new_status', 24)->nullable();
            $table->timestamps();

            $table->index(['fraud_score_id', 'created_at']);
        });

        // ── Blacklisted IPs ─────────────────────────────────────────
        Schema::create('fraud_blacklisted_ips', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45);
            $table->string('reason', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique('ip_address');
            $table->index(['is_active', 'expires_at']);
        });

        // ── Blacklisted Email Domains ───────────────────────────────
        Schema::create('fraud_blacklisted_emails', function (Blueprint $table) {
            $table->id();
            $table->string('domain', 191);
            $table->string('reason', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('domain');
            $table->index('is_active');
        });

        // ── Fraud Audit Log (extension-specific) ────────────────────
        Schema::create('fraud_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 64); // score.created | review.approved | rule.created | blacklist.added | etc.
            $table->string('entity_type', 48)->nullable(); // fraud_score | fraud_rule | blacklisted_ip | etc.
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('metadata')->nullable(); // flexible contextual data
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
            $table->index('action');
            $table->index('created_at');
        });

        // ── Customer Trust Scores ───────────────────────────────────
        Schema::create('fraud_customer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->unsignedTinyInteger('trust_score')->default(50); // 0-100
            $table->unsignedInteger('total_orders')->default(0);
            $table->unsignedInteger('flagged_orders')->default(0);
            $table->unsignedInteger('rejected_orders')->default(0);
            $table->unsignedInteger('approved_orders')->default(0);
            $table->decimal('total_revenue', 19, 4)->default(0);
            $table->boolean('is_repeat_offender')->default(false);
            $table->timestamp('last_order_at')->nullable();
            $table->json('risk_history')->nullable(); // array of {date, score, order_id}
            $table->timestamps();

            $table->unique('customer_id');
            $table->index('trust_score');
            $table->index('is_repeat_offender');
        });

        // ── Country Risk Configuration ──────────────────────────────
        Schema::create('fraud_country_risks', function (Blueprint $table) {
            $table->id();
            $table->char('country_code', 2)->unique();
            $table->string('country_name', 100);
            $table->string('risk_level', 16)->default('low'); // low | medium | high | blocked
            $table->unsignedTinyInteger('score_modifier')->default(0); // additional score to add
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ── Notification Log ────────────────────────────────────────
        Schema::create('fraud_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fraud_score_id')->constrained('fraud_scores')->cascadeOnDelete();
            $table->string('channel', 20); // email | slack | admin
            $table->string('recipient', 255)->nullable();
            $table->string('status', 16)->default('sent'); // sent | failed
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['fraud_score_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fraud_notifications');
        Schema::dropIfExists('fraud_country_risks');
        Schema::dropIfExists('fraud_customer_profiles');
        Schema::dropIfExists('fraud_audit_logs');
        Schema::dropIfExists('fraud_blacklisted_emails');
        Schema::dropIfExists('fraud_blacklisted_ips');
        Schema::dropIfExists('fraud_reviews');
        Schema::dropIfExists('fraud_rules');
        Schema::dropIfExists('fraud_scores');
    }
};
