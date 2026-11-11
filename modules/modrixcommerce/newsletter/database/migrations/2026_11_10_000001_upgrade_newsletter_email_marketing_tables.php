<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('newsletter_subscribers')) {
            Schema::table('newsletter_subscribers', function (Blueprint $table) {
                if (!Schema::hasColumn('newsletter_subscribers', 'name')) {
                    $table->string('name')->nullable()->after('email');
                }
                if (!Schema::hasColumn('newsletter_subscribers', 'groups')) {
                    $table->json('groups')->nullable()->after('source');
                }
            });
        }

        if (!Schema::hasTable('newsletter_groups')) {
            Schema::create('newsletter_groups', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->string('slug', 100)->unique();
                $table->text('description')->nullable();
                $table->string('color', 30)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('newsletter_campaigns')) {
            Schema::create('newsletter_campaigns', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('subject');
                $table->string('preview_text')->nullable();
                $table->string('target_type', 32)->default('all'); // all, active, customers, leads, group
                $table->string('target_group', 100)->nullable();
                $table->longText('content_html');
                $table->string('status', 32)->default('draft'); // draft, sent, failed
                $table->unsignedInteger('recipients_count')->default(0);
                $table->unsignedInteger('sent_count')->default(0);
                $table->unsignedInteger('failed_count')->default(0);
                $table->timestamp('sent_at')->nullable();
                $table->text('last_error')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_campaigns');
        Schema::dropIfExists('newsletter_groups');

        if (Schema::hasTable('newsletter_subscribers')) {
            Schema::table('newsletter_subscribers', function (Blueprint $table) {
                if (Schema::hasColumn('newsletter_subscribers', 'groups')) {
                    $table->dropColumn('groups');
                }
                if (Schema::hasColumn('newsletter_subscribers', 'name')) {
                    $table->dropColumn('name');
                }
            });
        }
    }
};
