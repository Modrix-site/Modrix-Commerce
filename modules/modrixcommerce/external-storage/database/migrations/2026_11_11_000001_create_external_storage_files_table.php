<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_storage_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->unsignedBigInteger('product_image_id')->nullable()->unique();
            $table->unsignedBigInteger('media_id')->nullable()->index();
            $table->string('media_type', 24)->default('photo')->index(); // photo, video, document
            $table->string('title', 255)->nullable();
            $table->string('provider', 40)->default('wasabi')->index();
            $table->string('bucket', 191)->nullable();
            $table->string('remote_path', 500);
            $table->string('remote_url', 1000);
            $table->string('local_disk', 64)->nullable();
            $table->string('local_path', 500)->nullable();
            $table->string('filename', 255);
            $table->string('mime_type', 127)->default('application/octet-stream');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->boolean('local_deleted')->default(false);
            $table->string('status', 24)->default('synced')->index(); // synced, pending, failed
            $table->string('error_message', 500)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_storage_files');
    }
};
