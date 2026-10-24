<?php

namespace Marketplace\Services;

use Composer\Semver\Comparator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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

    /**
     * @throws ValidationException on `id` when the submission was already
     *                             reviewed or no longer fits the catalog
     */
    public function approve(Submission $submission, User $reviewer): Package
    {
        return DB::transaction(function () use ($submission, $reviewer) {
            // Locked and re-read: a double-clicked approve sees the first decision.
            $submission = Submission::query()->whereKey($submission->id)->lockForUpdate()->firstOrFail();
            $fail = fn (string $message) => throw ValidationException::withMessages(['id' => $message]);

            if ($submission->status !== 'pending') {
                $fail('This submission was already reviewed.');
            }

            $data = $submission->data;
            $zip = $this->uploads->path((string) $submission->zip_path);

            if ($submission->kind === 'update') {
                $package = Package::query()->where('identifier', $submission->identifier)->lockForUpdate()->firstOrFail();
                $current = $package->latestRelease()?->version ?? $package->version;

                if (! Comparator::greaterThan((string) $data['version'], (string) $current)) {
                    $fail("Version {$data['version']} is not newer than the published {$current}. Reject it and ask for a new version.");
                }

                $platform = (string) ($data['platform_version'] ?? $package->platform_version);
                $package->fill(['version' => $data['version'], 'platform_version' => $platform, 'changelog' => $data['changelog'] ?? null])->save();
            } else {
                if (Package::query()->where('identifier', $submission->identifier)->exists()) {
                    $fail("{$submission->identifier} is already listed. Reject this submission or ask for an update instead.");
                }

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
