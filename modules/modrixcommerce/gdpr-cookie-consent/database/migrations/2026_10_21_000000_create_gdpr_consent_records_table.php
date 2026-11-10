<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gdpr_consent_records', function (Blueprint $table) {
            $table->id();
            // A random token set as a cookie so repeated choices from one
            // browser are linked without storing the visitor's IP address.
            $table->string('visitor_token', 64)->index();
            // decide | accept | reject | customize
            $table->string('decision', 16);
            // Whether analytics and marketing cookies were each allowed.
            $table->boolean('analytics_allowed')->default(false);
            $table->boolean('marketing_allowed')->default(false);
            // The country code used to decide whether the banner applied (null = not detected).
            $table->string('country_code', 2)->nullable();
            // The consent text shown to the visitor at the time of the choice.
            $table->text('consent_text');
            $table->string('page', 191)->nullable();
            $table->string('user_agent', 191)->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gdpr_consent_records');
    }
};
