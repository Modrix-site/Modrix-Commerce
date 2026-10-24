<?php

namespace Marketplace\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Marketplace\Models\Package;
use Marketplace\Models\Submission;
use Marketplace\Models\User;

/**
 * Publishes reviewed submissions: a new package becomes a published listing
 * with its first release; an update adds a release to the existing package.
 */
class Submissions
{
    public function __construct(private readonly Releases $releases, private readonly Uploads $uploads) {}

    public function approve(Submission $submission, User $reviewer): Package
    {
        return DB::transaction(function () use ($submission, $reviewer) {
            $data = $submission->data;
            $zip = $this->uploads->path((string) $submission->zip_path);

            if ($submission->kind === 'update') {
                $package = Package::query()->where('identifier', $submission->identifier)->lockForUpdate()->firstOrFail();
                $platform = (string) ($data['platform_version'] ?? $package->platform_version);
                $package->fill(['version' => $data['version'], 'platform_version' => $platform, 'changelog' => $data['changelog'] ?? null])->save();
            } else {
                $premium = ($data['tier'] ?? 'free') === 'premium';
                $platform = (string) $data['platform_version'];
                $package = Package::query()->create([
                    'identifier' => $submission->identifier,
                    'type' => $data['type'],
                    'slug' => self::slugFor($submission->identifier),
                    'name' => $data['name'],
                    'summary' => $data['summary'],
                    'author' => $data['author'] ?? '',
                    'license_name' => $data['license_name'] ?? 'MIT',
                    'version' => $data['version'],
                    'platform_version' => $platform,
                    'demo_url' => $data['demo_url'] ?? null,
                    'is_free' => ! $premium,
                    'price' => $premium ? $data['price'] : 0,
                    'currency' => (string) config('marketplace.currency'),
                    'status' => 'published',
                    'publisher_id' => $submission->user_id,
                ]);
            }

            $this->releases->publish($package, $zip, (string) $data['version'], $platform);
            $submission->forceFill(['status' => 'approved', 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'review_note' => null])->save();

            return $package;
        });
    }

    public function reject(Submission $submission, User $reviewer, string $note): void
    {
        $submission->forceFill(['status' => 'rejected', 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'review_note' => $note])->save();
    }

    /** A free URL slug from vendor/name, e.g. acme/reviews → acme-reviews. */
    public static function slugFor(string $identifier): string
    {
        $base = Str::slug(str_replace('/', '-', $identifier)) ?: 'package';
        $slug = $base;

        for ($i = 2; Package::query()->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
