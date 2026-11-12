<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Media library: one row per stored image (library uploads, product images
 * and theme uploads). Existing product images and theme uploads are
 * registered so the library starts complete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('disk', 32);
            $table->string('path', 255);
            $table->string('collection', 16)->index(); // library | products | themes
            $table->string('filename', 191);
            $table->string('mime', 64);
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alt', 191)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['disk', 'path']);
            $table->index('created_at');
        });

        $now = now();
        $mime = fn (string $path) => match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        foreach (DB::table('product_images')->orderBy('id')->get() as $image) {
            DB::table('media')->insertOrIgnore([
                'disk' => $image->disk, 'path' => $image->path, 'collection' => 'products',
                'filename' => basename($image->path), 'mime' => $mime($image->path), 'size' => 0,
                'width' => $image->width, 'height' => $image->height, 'alt' => $image->alt,
                'created_at' => $image->created_at ?? $now, 'updated_at' => $now,
            ]);
        }

        $uploads = DB::table('settings')->where('key', 'theme.uploads')->value('value');
        $disk = (string) config('ecomodular.media_disk', 'public');
        foreach ((array) json_decode((string) $uploads, true) as $upload) {
            if (is_array($upload) && is_string($upload['value'] ?? null) && str_starts_with($upload['value'], 'media:')) {
                $path = substr($upload['value'], 6);
                DB::table('media')->insertOrIgnore([
                    'disk' => $disk, 'path' => $path, 'collection' => 'themes',
                    'filename' => is_string($upload['name'] ?? null) ? mb_substr($upload['name'], 0, 191) : basename($path),
                    'mime' => $mime($path), 'size' => 0, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
