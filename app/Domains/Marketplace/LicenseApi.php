<?php

namespace App\Domains\Marketplace;

use App\Domains\Marketplace\Models\DownloadToken;
use App\Domains\Marketplace\Models\License;
use App\Domains\Marketplace\Models\LicenseActivation;
use App\Domains\Marketplace\Models\MarketplaceAdvisory;
use App\Domains\Marketplace\Models\MarketplaceProduct;
use App\Domains\Marketplace\Models\MarketplaceRelease;
use Composer\Semver\Comparator;
use Composer\Semver\Semver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The licensing service stores talk to (server side). Every answer about a
 * license is a signed token, so a store can verify it offline and keep
 * working through outages until the token expires plus a grace period.
 *
 * Refusals are LicenseApiException with a stable error code.
 */
final class LicenseApi
{
    public function __construct(
        private readonly LicenseManager $licenses,
        private readonly MarketplaceSigner $signer,
    ) {}

    /**
     * @return array{token: array<string, mixed>, signature: string}
     */
    public function activate(string $key, string $instanceId, ?string $domain, ?string $platformVersion, ?string $ip): array
    {
        $license = $this->usableLicense($key);

        return DB::transaction(function () use ($license, $instanceId, $domain, $platformVersion, $ip) {
            License::query()->whereKey($license->id)->lockForUpdate()->first();

            $existing = $license->activations()->where('instance_id', $instanceId)->whereNull('deactivated_at')->first();

            if ($existing === null) {
                $active = $license->activations()->whereNull('deactivated_at')->count();

                if ($license->activation_limit !== null && $active >= $license->activation_limit) {
                    throw new LicenseApiException('activation_limit', "This license is already active on {$active} of {$license->activation_limit} allowed installations. Deactivate one first.");
                }

                $existing = new LicenseActivation;
                $existing->forceFill(['license_id' => $license->id, 'instance_id' => $instanceId, 'activated_at' => now()]);
                $this->licenses->event($license, 'activated', null, $instanceId, $ip, array_filter(['domain' => $domain]));
            }

            $existing->forceFill(['domain' => $domain, 'platform_version' => $platformVersion, 'last_seen_at' => now()])->save();

            return $this->token($license, $instanceId);
        });
    }

    public function deactivate(string $key, string $instanceId, ?string $ip): void
    {
        $license = $this->license($key);
        $updated = $license->activations()->where('instance_id', $instanceId)->whereNull('deactivated_at')->update(['deactivated_at' => now()]);

        if ($updated > 0) {
            $this->licenses->event($license, 'deactivated', null, $instanceId, $ip);
        }
    }

    /**
     * Current status as a signed token. Answers for non-active licenses too,
     * so stores learn about refunds and revocations.
     *
     * @return array{token: array<string, mixed>, signature: string}
     */
    public function check(string $key, string $instanceId): array
    {
        $license = $this->license($key);
        $this->activation($license, $instanceId)?->forceFill(['last_seen_at' => now()])->save();

        return $this->token($license, $instanceId);
    }

    /**
     * Latest published, compatible release the license is entitled to.
     *
     * @return array{available: bool, release?: array<string, mixed>, reason?: string}
     */
    public function update(string $key, string $instanceId, ?string $installedVersion, string $platformVersion): array
    {
        $license = $this->usableLicense($key);
        $this->requireActivation($license, $instanceId);

        $release = $this->entitledRelease($license, $platformVersion);

        if ($release === null) {
            return ['available' => false, 'reason' => 'No compatible release is available under this license.'];
        }

        if ($installedVersion !== null && ! Comparator::greaterThan($release->version, $installedVersion)) {
            return ['available' => false, 'reason' => 'You have the latest version.'];
        }

        return ['available' => true, 'release' => $this->releasePayload($release)];
    }

    /**
     * Issues a short-lived, single-use download link for a release.
     *
     * @return array{url: string, expires_at: string, release: array<string, mixed>}
     */
    public function requestDownload(string $key, string $instanceId, int $releaseId, ?string $ip): array
    {
        $license = $this->usableLicense($key);
        $this->requireActivation($license, $instanceId);

        $release = MarketplaceRelease::query()->whereKey($releaseId)->where('product_id', $license->product_id)->first();

        if ($release === null || $release->status !== MarketplaceRelease::PUBLISHED) {
            throw new LicenseApiException('release_not_found', 'That release is not available.');
        }

        if (! $license->coversRelease($release)) {
            throw new LicenseApiException('not_entitled', 'Your update entitlement ended before this release. Renew to download it.');
        }

        $token = Str::random(64);
        $expires = now()->addMinutes(max(1, (int) config('ecomodular.marketplace.server.download_minutes', 10)));

        DownloadToken::query()->forceCreate([
            'license_id' => $license->id,
            'release_id' => $release->id,
            'token_hash' => hash('sha256', $token),
            'instance_id' => $instanceId,
            'expires_at' => $expires,
            'ip_address' => $ip,
        ]);
        $this->licenses->event($license, 'download_issued', null, $instanceId, $ip, ['version' => $release->version]);

        return [
            'url' => route('marketplace.download', $token),
            'expires_at' => $expires->toIso8601String(),
            'release' => $this->releasePayload($release),
        ];
    }

    /**
     * Redeems a download token. Single use; the license must still be usable.
     */
    public function redeem(string $token, ?string $ip): MarketplaceRelease
    {
        return DB::transaction(function () use ($token, $ip) {
            /** @var DownloadToken|null $record */
            $record = DownloadToken::query()->where('token_hash', hash('sha256', $token))->lockForUpdate()->first();

            if ($record === null || $record->used_at !== null || $record->expires_at->isPast()) {
                throw new LicenseApiException('download_expired', 'This download link is invalid, already used or expired.');
            }

            if (! $record->license->isUsable() || $record->release->status !== MarketplaceRelease::PUBLISHED) {
                throw new LicenseApiException('license_inactive', 'This license can no longer download packages.');
            }

            $record->forceFill(['used_at' => now()])->save();
            $this->licenses->event($record->license, 'downloaded', null, $record->instance_id, $ip, ['version' => $record->release->version]);

            return $record->release;
        });
    }

    /**
     * Download for a signed-in customer in the portal (no store instance).
     */
    public function portalDownload(License $license, ?string $ip): string
    {
        if (! $license->isUsable()) {
            throw new LicenseApiException('license_inactive', 'This license can no longer download packages.');
        }

        $release = $this->entitledRelease($license, null);

        if ($release === null) {
            throw new LicenseApiException('release_not_found', 'No release is available under this license.');
        }

        $token = Str::random(64);
        DownloadToken::query()->forceCreate([
            'license_id' => $license->id,
            'release_id' => $release->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes(max(1, (int) config('ecomodular.marketplace.server.download_minutes', 10))),
            'ip_address' => $ip,
        ]);
        $this->licenses->event($license, 'download_issued', null, null, $ip, ['version' => $release->version, 'via' => 'portal']);

        return route('marketplace.download', $token);
    }

    /**
     * @return array<string, mixed>
     */
    public function releasePayload(MarketplaceRelease $release): array
    {
        return [
            'id' => $release->id,
            'version' => $release->version,
            'platform_version' => $release->platform_version,
            'changelog' => $release->changelog,
            'published_at' => $release->published_at?->toIso8601String(),
            'verification' => $release->verification(),
        ];
    }

    public function entitledRelease(License $license, ?string $platformVersion): ?MarketplaceRelease
    {
        $releases = MarketplaceRelease::query()
            ->where('product_id', $license->product_id)
            ->where('status', MarketplaceRelease::PUBLISHED)
            ->get()
            ->filter(fn (MarketplaceRelease $r) => $license->coversRelease($r))
            ->filter(fn (MarketplaceRelease $r) => $platformVersion === null || $this->compatible($r, $platformVersion));

        return $releases->sort(fn (MarketplaceRelease $a, MarketplaceRelease $b) => Comparator::compare($b->version, '>', $a->version) ? 1 : -1)->first();
    }

    /**
     * @return array{token: array<string, mixed>, signature: string}
     */
    private function token(License $license, string $instanceId): array
    {
        $product = $license->product;
        $activation = $this->activation($license, $instanceId);
        $signed = $this->signer->sign([
            'schema' => 1,
            'license_id' => $license->id,
            'product' => $product->slug,
            'extension' => $product->extension_name,
            'type' => $product->type,
            'status' => $license->isUsable() ? License::ACTIVE : ($license->status === License::ACTIVE ? License::EXPIRED : $license->status),
            'activated' => $activation !== null,
            'instance_id' => $instanceId,
            'updates_until' => $license->updates_until?->toIso8601String(),
            'issued_at' => now()->toIso8601String(),
            'valid_until' => now()->addDays(max(1, (int) config('ecomodular.marketplace.server.token_days', 30)))->toIso8601String(),
            // Security advisories travel inside the signed token, for every license status.
            'advisories' => $product->advisories()->where('published_at', '<=', now())->orderByDesc('published_at')->limit(20)->get()
                ->map(fn (MarketplaceAdvisory $a) => $a->payload())->all(),
        ]);

        return ['token' => $signed['metadata'], 'signature' => $signed['signature']];
    }

    private function license(string $key): License
    {
        $license = License::findByKey($key);

        if ($license === null) {
            throw new LicenseApiException('invalid_key', 'This license key is not valid.');
        }

        return $license;
    }

    private function usableLicense(string $key): License
    {
        $license = $this->license($key);

        if (! $license->isUsable()) {
            throw new LicenseApiException('license_inactive', "This license is {$license->status}.");
        }

        if ($license->product->status === MarketplaceProduct::DRAFT) {
            throw new LicenseApiException('product_unavailable', 'This product is not available.');
        }

        return $license;
    }

    private function activation(License $license, string $instanceId): ?LicenseActivation
    {
        return $license->activations()->where('instance_id', $instanceId)->whereNull('deactivated_at')->first();
    }

    private function requireActivation(License $license, string $instanceId): void
    {
        if ($this->activation($license, $instanceId) === null) {
            throw new LicenseApiException('not_activated', 'Activate this license on this store first.');
        }
    }

    private function compatible(MarketplaceRelease $release, string $platformVersion): bool
    {
        try {
            return Semver::satisfies($platformVersion, $release->platform_version);
        } catch (\Throwable) {
            return false;
        }
    }
}
