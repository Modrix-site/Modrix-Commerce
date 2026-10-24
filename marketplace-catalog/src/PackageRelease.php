<?php

if (! class_exists(Signing::class)) {
    require_once __DIR__.'/Signing.php';
}

use App\Core\Marketplace\Signing;
use Composer\Semver\Semver;

/**
 * Builds a signed package release a merchant store can pull and install.
 * The archive is the uploaded ZIP when one exists, otherwise a zip of the
 * package directory with its manifest at the archive root.
 */
final class PackageRelease
{
    public function __construct(private readonly Store $store) {}

    /**
     * @param  array<string, mixed>  $license
     * @param  array<string, mixed>  $product
     * @return array<string, mixed>
     */
    public function update(array $license, array $product, ?string $installedVersion, ?string $platformVersion, string $secret): array
    {
        $release = $this->publish($product, $secret);
        if ($release === null) {
            return ['available' => false, 'reason' => 'No installable package is published for this license yet.'];
        }

        if (! $this->compatible($platformVersion, (string) $release['platform_version'])) {
            return ['available' => false, 'reason' => 'No compatible release is available under this license.'];
        }

        if ($installedVersion !== null && $installedVersion !== '' && version_compare((string) $release['version'], $installedVersion, '<=')) {
            return ['available' => false, 'reason' => 'You have the latest version.'];
        }

        $payload = $this->payload($release);
        if (is_string($product['changelog'] ?? null) && $product['changelog'] !== '') {
            $payload['changelog'] = $product['changelog'];
        }

        return ['available' => true, 'release' => $payload];
    }

    /**
     * @param  array<string, mixed>  $license
     * @param  array<string, mixed>  $product
     * @return array{url: string, expires_at: string, release: array<string, mixed>}
     */
    public function download(array $license, array $product, string $instance, int $releaseId, string $base, string $secret): array
    {
        $release = $this->publish($product, $secret);
        if ($release === null || (int) $release['id'] !== $releaseId) {
            throw new RuntimeException('That release is not available.', 404);
        }

        $token = bin2hex(random_bytes(32));
        $expires = date('c', time() + 600);
        $this->store->db->prepare('INSERT INTO download_tokens (token_hash, license_id, release_id, instance_id, expires_at) VALUES (?, ?, ?, ?, ?)')
            ->execute([hash('sha256', $token), (int) $license['id'], $releaseId, $instance, $expires]);

        return [
            'url' => rtrim($base, '/').'/marketplace/download/'.$token,
            'expires_at' => $expires,
            'release' => $this->payload($release),
        ];
    }

    /**
     * @return array{path: string, name: string}
     */
    public function redeem(string $token): array
    {
        $this->store->db->exec('BEGIN IMMEDIATE');
        try {
            $query = $this->store->db->prepare('SELECT * FROM download_tokens WHERE token_hash = ?');
            $query->execute([hash('sha256', $token)]);
            $row = $query->fetch(PDO::FETCH_ASSOC);
            if (! is_array($row) || $row['used_at'] !== null || strtotime((string) $row['expires_at']) < time()) {
                throw new RuntimeException('This download link is invalid, already used or expired.', 403);
            }
            $release = $this->store->db->prepare('SELECT * FROM package_releases WHERE id = ?');
            $release->execute([(int) $row['release_id']]);
            $package = $release->fetch(PDO::FETCH_ASSOC);
            if (! is_array($package) || ! is_file($package['path'])) {
                throw new RuntimeException('That release is not available.', 404);
            }
            $this->store->db->prepare('UPDATE download_tokens SET used_at = ? WHERE token_hash = ?')->execute([date('c'), $row['token_hash']]);
            $this->store->db->exec('COMMIT');

            return ['path' => $package['path'], 'name' => str_replace('/', '-', (string) $package['extension'])];
        } catch (Throwable $exception) {
            $this->store->db->exec('ROLLBACK');
            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $product
     * @return array<string, mixed>|null
     */
    private function publish(array $product, string $secret): ?array
    {
        $archive = $this->archive($product);
        if ($archive === null) {
            return null;
        }

        $sha = hash_file('sha256', $archive);
        $size = filesize($archive);
        if ($sha === false || $size === false) {
            return null;
        }

        $version = (string) $product['version'];
        $extension = (string) $product['extension'];
        $constraint = $this->installConstraint((string) ($product['platform_version'] ?? '^1.0'));
        $query = $this->store->db->prepare('SELECT * FROM package_releases WHERE extension = ? AND version = ?');
        $query->execute([$extension, $version]);
        $existing = $query->fetch(PDO::FETCH_ASSOC);
        if (is_array($existing) && hash_equals((string) $existing['sha256'], $sha) && (int) $existing['size'] === $size && is_file((string) $existing['path'])) {
            return $existing;
        }

        $publishedAt = date('c');
        $public = base64_decode(Signing::publicKeyFromSecret($secret), true);
        $metadata = [
            'schema' => 1,
            'product' => (string) $product['slug'],
            'extension' => $extension,
            'type' => (string) $product['type'],
            'version' => $version,
            'platform_version' => $constraint,
            'sha256' => $sha,
            'size' => $size,
            'published_at' => $publishedAt,
            'key_id' => Signing::keyId((string) $public),
        ];
        $signature = Signing::sign($metadata, $secret);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        if (is_array($existing)) {
            $this->store->db->prepare('UPDATE package_releases SET path = ?, sha256 = ?, size = ?, platform_version = ?, signed_metadata = ?, signature = ?, published_at = ? WHERE id = ?')
                ->execute([$archive, $sha, $size, $constraint, $encoded, $signature, $publishedAt, (int) $existing['id']]);
            $query->execute([$extension, $version]);

            return $query->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        $this->store->db->prepare('INSERT INTO package_releases (extension, version, path, sha256, size, platform_version, signed_metadata, signature, published_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$extension, $version, $archive, $sha, $size, $constraint, $encoded, $signature, $publishedAt]);
        $query->execute([$extension, $version]);

        return $query->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * @param  array<string, mixed>  $release
     * @return array<string, mixed>
     */
    private function payload(array $release): array
    {
        $metadata = json_decode((string) $release['signed_metadata'], true, flags: JSON_THROW_ON_ERROR);

        return [
            'id' => (int) $release['id'],
            'version' => $release['version'],
            'platform_version' => $release['platform_version'],
            'changelog' => null,
            'published_at' => $release['published_at'],
            'verification' => [
                'metadata' => $metadata,
                'signature' => $release['signature'],
            ],
        ];
    }

    /**
     * Saves one zip per package version under storage/sources/{type}/{extension}/{version}.zip
     * and records them in storage/sources/index.json.
     *
     * @param  list<array<string, mixed>>  $products
     * @return list<array<string, mixed>>
     */
    public function sync(array $products, string $secret): array
    {
        $saved = [];
        foreach ($products as $product) {
            if (($product['status'] ?? 'published') !== 'published') {
                continue;
            }
            $release = $this->publish($product, $secret);
            if ($release !== null) {
                $saved[] = $release;
            }
        }

        return $saved;
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private function archive(array $product): ?string
    {
        $target = $this->sourcePath($product);
        if ($target === null) {
            return null;
        }

        $directory = dirname($target);
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $filename = $product['package'] ?? '';
        if (is_string($filename) && preg_match('/^[a-f0-9]{32}\.zip$/D', $filename)) {
            $uploaded = catalogStorage().'/packages/'.$filename;
            if (is_file($uploaded)) {
                $current = is_file($target) ? hash_file('sha256', $target) : false;
                $next = hash_file('sha256', $uploaded);
                if ($current === false || $next === false || ! hash_equals($current, $next)) {
                    copy($uploaded, $target);
                }
                $this->remember($product, $target);

                return $target;
            }
        }

        $source = $product['directory'] ?? null;
        if (! is_string($source) || ! is_dir($source)) {
            return is_file($target) ? $target : null;
        }

        $stamp = $this->stamp($source);
        $marker = $target.'.stamp';
        if (is_file($target) && is_file($marker) && hash_equals($stamp, (string) file_get_contents($marker))) {
            $this->remember($product, $target);

            return $target;
        }

        $this->zip($source, $target);
        file_put_contents($marker, $stamp);
        $this->remember($product, $target);
        if (! class_exists(Catalog::class, false)) {
            require_once __DIR__.'/Catalog.php';
        }
        (new Catalog(getenv('CATALOG_ROOT') ?: dirname(__DIR__, 2)))->retain($product, true);

        return $target;
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private function sourcePath(array $product): ?string
    {
        $type = $product['type'] ?? '';
        $extension = str_replace('\\', '/', (string) ($product['extension'] ?? ''));
        $version = (string) ($product['version'] ?? '');
        if (! in_array($type, ['theme', 'module'], true) || ! preg_match('#^[a-z0-9-]+/[a-z0-9-]+$#', $extension) || ! preg_match('#^[0-9A-Za-z][0-9A-Za-z._-]*$#', $version)) {
            return null;
        }

        return catalogStorage().'/sources/'.$type.'/'.$extension.'/'.$version.'.zip';
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private function remember(array $product, string $path): void
    {
        $sha = hash_file('sha256', $path);
        $size = filesize($path);
        if ($sha === false || $size === false) {
            return;
        }

        $relative = 'sources/'.($product['type']).'/'.str_replace('\\', '/', (string) $product['extension']).'/'.$product['version'].'.zip';
        $indexPath = catalogStorage().'/sources/index.json';
        $index = ['packages' => []];
        if (is_file($indexPath)) {
            $decoded = json_decode((string) file_get_contents($indexPath), true);
            if (is_array($decoded['packages'] ?? null)) {
                $index['packages'] = $decoded['packages'];
            }
        }

        $index['packages'] = array_values(array_filter($index['packages'], fn ($row) => ! is_array($row) || ($row['type'] ?? '') !== $product['type'] || ($row['extension'] ?? '') !== $product['extension'] || ($row['version'] ?? '') !== $product['version']));
        $index['packages'][] = [
            'type' => $product['type'],
            'extension' => $product['extension'],
            'slug' => $product['slug'],
            'version' => $product['version'],
            'file' => $relative,
            'sha256' => $sha,
            'size' => $size,
        ];
        usort($index['packages'], fn (array $a, array $b) => [$a['type'], $a['extension'], $a['version']] <=> [$b['type'], $b['extension'], $b['version']]);
        file_put_contents($indexPath, json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    }

    private function stamp(string $directory): string
    {
        $parts = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile() || $this->skipped($directory, $file->getPathname())) {
                continue;
            }
            $parts[] = substr($file->getPathname(), strlen($directory)).'|'.$file->getSize().'|'.$file->getMTime();
        }
        sort($parts);

        return hash('sha256', implode("\n", $parts));
    }

    private function zip(string $directory, string $target): void
    {
        $zip = new ZipArchive;
        $temporary = $target.'.tmp';
        if (is_file($temporary)) {
            unlink($temporary);
        }
        if ($zip->open($temporary, ZipArchive::CREATE) !== true) {
            throw new RuntimeException('The package archive could not be created.', 503);
        }

        $root = rtrim(str_replace('\\', '/', $directory), '/').'/';
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile() || $this->skipped($directory, $file->getPathname())) {
                continue;
            }
            $name = substr(str_replace('\\', '/', $file->getPathname()), strlen($root));
            $zip->addFile($file->getPathname(), $name);
        }
        $zip->close();
        rename($temporary, $target);
    }

    private function skipped(string $directory, string $path): bool
    {
        $relative = substr(str_replace('\\', '/', $path), strlen(rtrim(str_replace('\\', '/', $directory), '/')) + 1);
        foreach (explode('/', $relative) as $part) {
            if (in_array($part, ['.git', 'node_modules', 'vendor', 'storage'], true)) {
                return true;
            }
        }

        return false;
    }

    private function installConstraint(string $constraint): string
    {
        return in_array($constraint, ['^1.0', '^1.0.0'], true) ? '>=1.0.0-rc.1 <2.0.0' : $constraint;
    }

    private function compatible(?string $platformVersion, string $constraint): bool
    {
        if ($platformVersion === null || $platformVersion === '') {
            return true;
        }

        if (class_exists(Semver::class)) {
            try {
                return Semver::satisfies($platformVersion, $constraint);
            } catch (Throwable) {
                return false;
            }
        }

        if ($constraint === '>=1.0.0-rc.1 <2.0.0' && preg_match('/^1\./', $platformVersion) === 1) {
            return true;
        }

        return $platformVersion === $constraint;
    }
}
