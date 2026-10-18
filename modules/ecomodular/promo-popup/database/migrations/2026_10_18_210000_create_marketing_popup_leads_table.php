<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_popup_leads', function (Blueprint $table) {
            $table->id();
            $table->string('email', 191)->unique();
            // The coupon shown after sign-up, kept so you know what was promised.
            $table->string('coupon_code', 64)->nullable();
            // Where the shopper was when the pop-up was answered (path only, no query string).
            $table->string('page', 191)->nullable();
            $table->text('consent_text');
            $table->timestamp('consented_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_popup_leads');
    }
};
