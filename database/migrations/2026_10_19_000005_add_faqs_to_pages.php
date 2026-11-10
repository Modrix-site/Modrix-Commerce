<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->string('kind', 16)->default('page');
            $table->json('faqs')->nullable();
        });

        if (DB::table('pages')->where('slug', 'faqs')->exists()) {
            return;
        }

        DB::table('pages')->insert([
            'title' => 'FAQs',
            'slug' => 'faqs',
            'kind' => 'faq',
            'content' => '<p>Answers to the questions shoppers ask most often.</p>',
            'faqs' => json_encode([
                ['question' => 'How long does delivery take?', 'answer' => '<p>Most orders leave the warehouse within two working days. The time after that depends on the address and the shipping method chosen at checkout.</p>'],
                ['question' => 'How can I return an item?', 'answer' => '<p>After an order is delivered, open it in your account and request a return. Include the reason and the store will reply with the next step.</p>'],
                ['question' => 'Which payment methods do you accept?', 'answer' => '<p>The methods available for your country are shown at checkout, before the order is placed.</p>'],
                ['question' => 'How do I track my order?', 'answer' => '<p>Open Your orders in your account. Tracking details appear on the order once a shipment is created.</p>'],
            ]),
            'status' => 'published',
            'meta_title' => 'FAQs',
            'meta_description' => 'Delivery, returns, payment and order questions.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn(['kind', 'faqs']);
        });
    }
};
