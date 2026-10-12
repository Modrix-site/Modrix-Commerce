<?php

namespace App\Domains\Marketplace;

use App\Core\Audit\AuditLogger;
use App\Core\Marketplace\PackageArchive;
use App\Core\Marketplace\PackageException;
use App\Domains\Marketplace\Models\MarketplaceProduct;
use App\Domains\Marketplace\Models\MarketplaceRelease;
use App\Models\User;
use Composer\Semver\Comparator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Release pipeline: upload → archive scan and manifest validation →
 * checksum → owner review → publish with signed metadata. Nothing is
 * offered to stores until it is published (spec 12.1: "Do not publish a
 * package until it passes required validation and review").
 */
final class ReleasePublisher
{
    public const DISK = 'local'; // storage/app/private: never web-accessible

    public function __construct(
        private readonly MarketplaceSigner $signer,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @throws ValidationException
     */
    public function upload(MarketplaceProduct $product, UploadedFile $file, ?string $changelog, User $actor): MarketplaceRelease
    {
        $maxBytes = (int) config('ecomodular.marketplace.server.max_package_mb', 50) * 1024 * 1024;

        if ((int) $file->getSize() > $maxBytes) {
            throw ValidationException::withMessages(['package' => 'The package is too large.']);
        }

        $path = (string) $file->getRealPath();

        try {
            ['dir' => $dir, 'manifest' => $manifest] = PackageArchive::extract($path, $product->type, $product->extension_name);
        } catch (PackageException $e) {
            throw ValidationException::withMessages(['package' => $e->getMessage()]);
        }

        File::deleteDirectory($dir);

        if ($product->releases()->where('version', $manifest->version)->exists()) {
            throw ValidationException::withMessages(['package' => "Version {$manifest->version} already exists."]);
        }

        $latest = $product->releases()->whereIn('status', [MarketplaceRelease::PUBLISHED, MarketplaceRelease::PENDING_REVIEW])->pluck('version')
            ->reduce(fn (?string $max, string $v) => $max === null || Comparator::greaterThan($v, $max) ? $v : $max);

        if ($latest !== null && ! Comparator::greaterThan($manifest->version, $latest)) {
            throw ValidationException::withMessages(['package' => "The version must be higher than {$latest}."]);
        }

        $stored = "marketplace/releases/{$product->id}/".Str::random(40).'.zip';
        Storage::disk(self::DISK)->put($stored, (string) file_get_contents($path));

        $release = new MarketplaceRelease;
        $release->forceFill([
            'product_id' => $product->id,
            'version' => $manifest->version,
            'platform_version' => $manifest->platformVersion,
            'changelog' => $changelog,
            'status' => MarketplaceRelease::PENDING_REVIEW,
            'archive_path' => $stored,
            'sha256' => hash_file('sha256', $path),
            'size' => (int) filesize($path),
            'manifest' => $manifest->toArray(),
        ])->save();

        $this->audit->record('marketplace.release_uploaded', null, ['product' => $product->slug, 'version' => $release->version, 'sha256' => $release->sha256], $actor);

        return $release;
    }

    /**
     * Approve (sign and publish) or reject a release awaiting review.
     *
     * @throws ValidationException
     */
    public function review(MarketplaceRelease $release, bool $approve, ?string $notes, User $actor): MarketplaceRelease
    {
        if ($release->status !== MarketplaceRelease::PENDING_REVIEW) {
            throw ValidationException::withMessages(['release' => 'Only releases awaiting review can be reviewed.']);
        }

        if ($approve) {
            // The archive is re-hashed: what is signed is exactly what will be served.
            $path = Storage::disk(self::DISK)->path($release->archive_path);

            if (! is_file($path) || ! hash_equals($release->sha256, (string) hash_file('sha256', $path))) {
                throw ValidationException::withMessages(['release' => 'The stored package does not match its checksum.']);
            }

            $publishedAt = now();
            $signed = $this->signer->sign([
                'schema' => 1,
                'product' => $release->product->slug,
                'extension' => $release->product->extension_name,
                'type' => $release->product->type,
                'version' => $release->version,
                'platform_version' => $release->platform_version,
                'sha256' => $release->sha256,
                'size' => $release->size,
                'published_at' => $publishedAt->toIso8601String(),
            ]);

            $release->forceFill([
                'status' => MarketplaceRelease::PUBLISHED,
                'signed_metadata' => $signed['metadata'],
                'signature' => $signed['signature'],
                'published_at' => $publishedAt,
            ]);
        } else {
            $release->status = MarketplaceRelease::REJECTED;
        }

        $release->forceFill(['review_notes' => $notes, 'reviewed_by' => $actor->id, 'reviewed_at' => now()])->save();
        $this->audit->record($approve ? 'marketplace.release_published' : 'marketplace.release_rejected', null, ['product' => $release->product->slug, 'version' => $release->version], $actor);

        return $release;
    }

    /**
     * Stops offering a release (e.g. a broken build). Already downloaded copies are unaffected.
     */
    public function withdraw(MarketplaceRelease $release, User $actor): void
    {
        $release->forceFill(['status' => MarketplaceRelease::WITHDRAWN])->save();
        $this->audit->record('marketplace.release_withdrawn', null, ['product' => $release->product->slug, 'version' => $release->version], $actor);
    }
}
