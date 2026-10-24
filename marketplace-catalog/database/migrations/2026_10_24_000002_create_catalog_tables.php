<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Settings, categories, packages, releases and publisher submissions. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value');
            // Secrets are stored encrypted (Crypt::encryptString).
            $table->boolean('secret')->default(false);
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            // Fixed after creation so links keep working.
            $table->string('slug', 64)->unique();
            $table->string('name', 120);
            $table->string('description', 255)->default('');
            $table->string('type', 16)->default('all'); // theme | module | all
            $table->unsignedInteger('position')->default(0);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('identifier', 128)->unique(); // vendor/name
            $table->string('type', 16); // theme | module
            $table->string('slug', 128)->unique();
            $table->string('name', 160);
            $table->string('summary', 255)->default('');
            $table->text('description')->nullable();
            $table->foreignId('publisher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('price', 12, 2)->default(0);
            $table->char('currency', 3)->default('USD');
            $table->boolean('is_free')->default(true);
            $table->string('status', 16)->default('draft'); // draft | published | hidden
            $table->boolean('featured')->default(false);
            $table->string('demo_url', 500)->nullable();
            $table->string('repository_url', 500)->nullable();
            $table->string('thumbnail_path', 255)->nullable();
            $table->json('features')->nullable();
            $table->timestamps();
            $table->index(['status', 'type']);
        });

        Schema::create('category_package', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->primary(['category_id', 'package_id']);
        });

        Schema::create('package_releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->string('version', 32);
            $table->string('path', 255);
            $table->char('sha256', 64);
            $table->unsignedBigInteger('size');
            $table->string('platform_version', 64)->default('*');
            $table->json('signed_metadata');
            $table->text('signature');
            $table->timestamp('published_at')->useCurrent(); // Explicit default: MySQL 5.7/MariaDB 10.3 would otherwise add ON UPDATE.
            $table->timestamps();
            $table->unique(['package_id', 'version']);
        });

        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('identifier', 128);
            $table->string('kind', 16)->default('new'); // new | update
            $table->json('data');
            $table->string('zip_path', 255)->nullable();
            $table->string('status', 16)->default('pending'); // pending | approved | rejected
            $table->text('review_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['submissions', 'package_releases', 'category_package', 'packages', 'categories', 'settings'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
