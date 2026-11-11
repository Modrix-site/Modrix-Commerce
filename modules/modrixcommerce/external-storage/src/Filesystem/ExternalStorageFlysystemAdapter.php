<?php

namespace EcoModular\ExternalStorage\Filesystem;

use EcoModular\ExternalStorage\Models\ExternalStorageFile;
use EcoModular\ExternalStorage\Services\ExternalStorageClient;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\UrlGeneration\PublicUrlGenerator;
use Throwable;

/**
 * Flysystem v3 adapter & public URL generator backed by ExternalStorageClient.
 * Allows Laravel's `Storage::disk('external_storage')` to write directly to
 * Wasabi, AWS S3, Cloudflare Storage, DigitalOcean Spaces, Backblaze B2, BunnyCDN, etc.
 */
class ExternalStorageFlysystemAdapter implements FilesystemAdapter, PublicUrlGenerator
{
    public function __construct(
        private readonly ExternalStorageClient $client,
    ) {}

    public function fileExists(string $path): bool
    {
        $remotePath = $this->client->normalizeRemotePath($path);

        try {
            $tracked = ExternalStorageFile::query()
                ->where(function ($q) use ($path, $remotePath) {
                    $q->where('remote_path', $remotePath)
                        ->orWhere('local_path', $path);
                })
                ->where('status', ExternalStorageFile::STATUS_SYNCED)
                ->exists();

            if ($tracked) {
                return true;
            }
        } catch (Throwable) {
            // Ignore DB lookup errors if migrations haven't run yet.
        }

        return $this->client->objectExists($path);
    }

    public function directoryExists(string $path): bool
    {
        return true;
    }

    public function write(string $path, string $contents, Config $config): void
    {
        try {
            $mime = (string) ($config->get('mimetype') ?? $this->guessMimeType($path, $contents));
            $this->client->putObject($path, $contents, $mime);
        } catch (Throwable $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $data = is_resource($contents) ? stream_get_contents($contents) : '';
        $this->write($path, is_string($data) ? $data : '', $config);
    }

    public function read(string $path): string
    {
        try {
            return $this->client->getObject($path);
        } catch (Throwable $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }
    }

    public function readStream(string $path)
    {
        $contents = $this->read($path);
        $stream = fopen('php://temp', 'r+b');
        if ($stream === false) {
            throw UnableToReadFile::fromLocation($path, 'Unable to open temporary stream.');
        }

        fwrite($stream, $contents);
        rewind($stream);

        return $stream;
    }

    public function delete(string $path): void
    {
        $this->client->deleteObject($path);
    }

    public function deleteDirectory(string $path): void
    {
        // Object storage uses virtual key prefixes.
    }

    public function createDirectory(string $path, Config $config): void
    {
        // Object storage uses virtual key prefixes.
    }

    public function setVisibility(string $path, string $visibility): void
    {
        // Visibility is handled on upload via provider ACL / bucket policy.
    }

    public function visibility(string $path): FileAttributes
    {
        return new FileAttributes($path, null, 'public');
    }

    public function mimeType(string $path): FileAttributes
    {
        return new FileAttributes($path, null, null, null, $this->guessMimeType($path));
    }

    public function lastModified(string $path): FileAttributes
    {
        return new FileAttributes($path, null, null, time());
    }

    public function fileSize(string $path): FileAttributes
    {
        $remotePath = $this->client->normalizeRemotePath($path);

        try {
            $record = ExternalStorageFile::query()
                ->where('remote_path', $remotePath)
                ->orWhere('local_path', $path)
                ->first();

            if ($record instanceof ExternalStorageFile) {
                return new FileAttributes($path, $record->file_size);
            }
        } catch (Throwable) {
        }

        return new FileAttributes($path, 0);
    }

    public function listContents(string $path, bool $deep): iterable
    {
        return [];
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $this->copy($source, $destination, $config);
        $this->delete($source);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $contents = $this->read($source);
        $this->write($destination, $contents, $config);
    }

    /**
     * Used by Laravel's FilesystemAdapter::url($path).
     */
    public function getUrl(string $path): string
    {
        return $this->publicUrl($path, new Config);
    }

    /**
     * Used by Flysystem v3 PublicUrlGenerator.
     */
    public function publicUrl(string $path, Config $config): string
    {
        $remotePath = $this->client->normalizeRemotePath($path);

        try {
            $record = ExternalStorageFile::query()
                ->where(function ($q) use ($path, $remotePath) {
                    $q->where('remote_path', $remotePath)
                        ->orWhere('local_path', $path);
                })
                ->where('status', ExternalStorageFile::STATUS_SYNCED)
                ->first();

            if ($record instanceof ExternalStorageFile) {
                return $record->publicUrl();
            }
        } catch (Throwable) {
        }

        return $this->client->publicUrl($path);
    }

    private function guessMimeType(string $path, string $contents = ''): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $map = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'avif' => 'image/avif',
            'svg' => 'image/svg+xml',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'mov' => 'video/quicktime',
            'm4v' => 'video/x-m4v',
            'ogg' => 'video/ogg',
            'pdf' => 'application/pdf',
        ];

        if (isset($map[$ext])) {
            return $map[$ext];
        }

        if ($contents !== '' && class_exists(\finfo::class)) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $detected = $finfo->buffer($contents);
            if (is_string($detected) && $detected !== '') {
                return $detected;
            }
        }

        return 'application/octet-stream';
    }
}
