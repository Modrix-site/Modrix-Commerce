<?php

namespace EcoModular\CloudConnector;

use App\Core\Audit\AuditLogger;
use App\Core\Extensions\ModuleRepository;
use App\Core\Extensions\PackageRoots;
use App\Core\Marketplace\Client\StoreLicense;
use App\Core\Marketplace\Client\UpdateHistory;
use App\Core\Marketplace\PackageArchive;
use App\Core\Marketplace\PackageException;
use App\Core\Marketplace\Signing;
use App\Core\Platform;
use App\Core\Themes\ThemeRepository;
use App\Models\User;
use Composer\Semver\Semver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

/**
 * Installs or updates a licensed package on this store (ADR-034):
 *
 *  1. the release metadata must carry a valid signature from a trusted key
 *     and describe the licensed product, and be compatible with this platform;
 *  2. the download (short-lived, authorized link) must have exactly the
 *     signed size and SHA-256;
 *  3. the archive is unpacked defensively and its manifest validated; its
 *     name and version must match the signed metadata;
 *  4. the current files are backed up, then replaced by a rename;
 *  5. the module or theme registry is resynced. Nothing is installed into
 *     the database or enabled: the admin does that through the usual
 *     lifecycle (a module becomes "discovered" or "update pending").
 *
 * Any failure before step 4 leaves the store untouched; a failure during
 * step 4 restores the previous files. Every attempt is recorded.
 */
final class PackageInstaller
{
    public function __construct(
        private readonly CloudClient $client,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @throws CloudError|PackageException
     */
    public function install(StoreLicense $license, ?User $actor): UpdateHistory
    {
        $lock = Cache::lock('ecomodular:marketplace:install', 300);

        if (! $lock->block(10)) {
            throw new CloudError('Another marketplace installation is in progress.');
        }

        $currentVersion = $this->installedVersion($license);
        $work = storage_path('app/private/marketplace/tmp/'.Str::random(32));
        $extracted = null;

        try {
            File::ensureDirectoryExists($work);
            $key = $license->key();
            $this->repairType($license, $key);
            $update = $this->client->update($key, $currentVersion);

            if (! ($update['available'] ?? false) || ! is_array($update['release'] ?? null)) {
                throw new CloudError((string) ($update['reason'] ?? 'No update is available.'));
            }

            $release = $update['release'];
            $metadata = $this->verifiedMetadata($license, $release);

            $download = $this->client->requestDownload($key, (int) $release['id']);
            $zip = $work.'/package.zip';
            $this->client->download($download['url'], $zip, (int) $metadata['size']);

            if (! hash_equals((string) $metadata['sha256'], (string) hash_file('sha256', $zip))) {
                throw new PackageException('The downloaded package does not match its signed checksum.');
            }

            ['dir' => $extracted, 'manifest' => $manifest] = PackageArchive::extract($zip, $license->type, $license->extension_name);

            if ($manifest->version !== $metadata['version']) {
                throw new PackageException('The package version does not match its signed metadata.');
            }

            $backup = $this->replace($license, $extracted);
            $extracted = null;
            $this->resync($license);

            $entry = $this->record($license, $currentVersion === null ? 'install' : 'update', $currentVersion, $manifest->version, true, null, $backup, (string) $metadata['sha256'], $actor);
            $license->forceFill(['available_release' => null])->save();
            $this->audit->record('marketplace.package_installed', null, ['extension' => $license->extension_name, 'from' => $currentVersion, 'to' => $manifest->version], $actor);

            return $entry;
        } catch (CloudError|PackageException $e) {
            $this->record($license, $currentVersion === null ? 'install' : 'update', $currentVersion, null, false, $e->getMessage(), null, null, $actor);

            throw $e;
        } finally {
            if ($extracted !== null) {
                File::deleteDirectory($extracted);
            }
            File::deleteDirectory($work);
            $lock->release();
        }
    }

    /**
     * Restores the files saved before an install or update. For modules
     * whose update ran migrations, restore the database backup as well (see
     * docs/extensions/recovery.md): files alone do not undo schema changes.
     *
     * @throws PackageException
     */
    public function rollback(UpdateHistory $entry, User $actor): UpdateHistory
    {
        if (! $entry->success || $entry->backup_path === null || ! is_file($entry->backup_path)) {
            throw new PackageException('This entry has no backup to restore.');
        }

        $license = StoreLicense::query()->where('extension_name', $entry->extension_name)->where('type', $entry->type)->firstOrFail();
        $current = $this->installedVersion($license);

        ['dir' => $extracted, 'manifest' => $manifest] = PackageArchive::extract($entry->backup_path, $entry->type, $entry->extension_name);

        try {
            $this->replace($license, $extracted, backup: false);
        } catch (Throwable $e) {
            File::deleteDirectory($extracted);

            throw $e;
        }

        $this->resync($license);
        $this->audit->record('marketplace.package_rolled_back', null, ['extension' => $entry->extension_name, 'to' => $manifest->version], $actor);

        return $this->record($license, 'rollback', $current, $manifest->version, true, 'Previous files restored.', null, null, $actor);
    }

    /**
     * @param  array<string, mixed>  $release
     * @return array<string, mixed>
     *
     * @throws PackageException
     */
    private function verifiedMetadata(StoreLicense $license, array $release): array
    {
        $metadata = $release['verification']['metadata'] ?? null;
        $signature = $release['verification']['signature'] ?? null;

        if (! is_array($metadata) || ! is_string($signature) || ! Signing::verify($metadata, $signature, $this->client->trustedKeys())) {
            throw new PackageException('The release is not signed by a trusted marketplace key.');
        }

        if (($metadata['extension'] ?? null) !== $license->extension_name || ($metadata['type'] ?? null) !== $license->type || ($metadata['product'] ?? null) !== $license->product_slug) {
            throw new PackageException('The signed release is for a different product.');
        }

        if (! is_string($metadata['sha256'] ?? null) || ! preg_match('/^[0-9a-f]{64}$/', $metadata['sha256']) || ! is_int($metadata['size'] ?? null) || ! is_string($metadata['version'] ?? null)) {
            throw new PackageException('The signed release metadata is incomplete.');
        }

        try {
            $compatible = Semver::satisfies(Platform::VERSION, (string) ($metadata['platform_version'] ?? ''));
        } catch (Throwable) {
            $compatible = false;
        }

        if (! $compatible) {
            throw new PackageException("This release requires platform {$metadata['platform_version']}; this store runs ".Platform::VERSION.'.');
        }

        return $metadata;
    }

    /**
     * Swaps the package directory, keeping a zip backup of the current files.
     */
    private function replace(StoreLicense $license, string $extracted, bool $backup = true): ?string
    {
        // Installs and updates always land in the install root (under storage/),
        // which survives redeploys; a copy shipped with the code stays where it is.
        $current = $this->targetDir($license);
        $target = PackageRoots::installDir($license->type === 'theme' ? PackageRoots::THEMES : PackageRoots::MODULES, $license->extension_name);
        $backupPath = null;

        if ($backup && is_dir($current)) {
            $version = $this->installedVersion($license) ?? 'unknown';
            $backupPath = storage_path('app/private/marketplace/backups/'.str_replace('/', '-', $license->extension_name)."-{$version}-".now()->format('YmdHis').'.zip');
            File::ensureDirectoryExists(dirname($backupPath));
            PackageArchive::create($current, $backupPath);
        }

        File::ensureDirectoryExists(dirname($target));
        $old = null;

        if (is_dir($target)) {
            $old = $target.'.old-'.Str::random(8);
            if (! $this->moveDirectory($target, $old)) {
                throw new PackageException('The current package could not be backed up; the previous files were kept.');
            }
        }

        if (! $this->moveDirectory($extracted, $target)) {
            if ($old !== null) {
                $this->moveDirectory($old, $target);
            }

            throw new PackageException('The package could not be moved into place; the previous files were kept.');
        }

        if ($old !== null) {
            File::deleteDirectory($old);
        }

        PackageRoots::forget();

        return $backupPath;
    }

    /**
     * Windows scanners can hold newly written files open for several seconds.
     * Keep the move atomic and retry for up to about 10 seconds.
     */
    private function moveDirectory(string $source, string $target): bool
    {
        $attempts = PHP_OS_FAMILY === 'Windows' ? 15 : 1;

        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            if (File::moveDirectory($source, $target)) {
                return true;
            }

            if (! is_dir($source) || file_exists($target) || $attempt + 1 === $attempts) {
                return false;
            }

            usleep(min(1_000_000, 100_000 * ($attempt + 1)));
        }

        return false;
    }

    private function targetDir(StoreLicense $license): string
    {
        return $license->type === 'theme'
            ? ThemeRepository::packageDir($license->extension_name)
            : ModuleRepository::packageDir($license->extension_name);
    }

    private function installedVersion(StoreLicense $license): ?string
    {
        $file = $this->targetDir($license).'/'.($license->type === 'theme' ? 'theme.json' : 'module.json');
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return is_array($data) && is_string($data['version'] ?? null) ? $data['version'] : null;
    }

    private function resync(StoreLicense $license): void
    {
        $license->type === 'theme' ? app(ThemeRepository::class)->sync() : app(ModuleRepository::class)->sync();
    }

    private function record(StoreLicense $license, string $action, ?string $from, ?string $to, bool $success, ?string $message, ?string $backup, ?string $sha256, ?User $actor): UpdateHistory
    {
        return UpdateHistory::query()->forceCreate([
            'extension_name' => $license->extension_name,
            'type' => $license->type,
            'action' => $action,
            'from_version' => $from,
            'to_version' => $to,
            'success' => $success,
            'message' => $message === null ? null : Str::limit($message, 1000),
            'backup_path' => $backup,
            'sha256' => $sha256,
            'actor_id' => $actor?->id,
        ]);
    }

    /**
     * Licenses recorded from older Modrix Cloud tokens hold the license kind
     * ("free"/"paid") as their type, so no signed release could match them.
     * Re-reads the type from a fresh signed token before installing.
     */
    private function repairType(StoreLicense $license, string $key): void
    {
        if (StoreLicenses::packageType($license->type) !== null) {
            return;
        }

        $type = StoreLicenses::packageType($this->client->check($key)['token']['type'] ?? null);

        if ($type !== null) {
            $license->forceFill(['type' => $type])->save();
        }
    }
}
