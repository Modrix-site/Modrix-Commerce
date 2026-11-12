<?php

namespace App\Domains\Marketplace;

use App\Core\Audit\AuditLogger;
use App\Domains\Marketplace\Models\DownloadToken;
use App\Domains\Marketplace\Models\License;
use App\Domains\Marketplace\Models\LicenseEvent;
use App\Domains\Marketplace\Models\MarketplaceOrderItem;
use App\Domains\Marketplace\Models\MarketplaceProduct;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

/**
 * Issues licenses and changes their status. Every change is a license
 * event (the license audit history) and an audit-log entry.
 */
final class LicenseManager
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @return array{license: License, key: string} the plain key, shown to the buyer
     */
    public function issue(MarketplaceProduct $product, User $owner, ?MarketplaceOrderItem $item = null, ?User $actor = null): array
    {
        do {
            $key = License::generateKey();
        } while (License::query()->where('key_hash', License::hashKey($key))->exists());

        $license = new License;
        $license->forceFill([
            'product_id' => $product->id,
            'user_id' => $owner->id,
            'order_item_id' => $item?->id,
            'key_hash' => License::hashKey($key),
            'key_encrypted' => Crypt::encryptString($key),
            'key_hint' => substr($key, -5),
            'status' => License::ACTIVE,
            'activation_limit' => $product->activation_limit,
            'updates_until' => $product->updates_months > 0 ? now()->addMonths($product->updates_months) : null,
            'issued_at' => now(),
        ])->save();

        $item?->forceFill(['license_id' => $license->id])->save();
        $this->event($license, 'issued', $actor, details: ['order_item' => $item?->id]);

        return ['license' => $license, 'key' => $key];
    }

    /**
     * Moves a license to another status (revoke, suspend, refund, expire,
     * reinstate). Non-active licenses get no downloads, updates or activations.
     */
    public function changeStatus(License $license, string $status, ?string $reason, ?User $actor = null): License
    {
        if (! in_array($status, License::STATUSES, true)) {
            throw ValidationException::withMessages(['status' => 'Unknown license status.']);
        }

        $from = $license->status;
        $license->forceFill(['status' => $status, 'status_reason' => $reason])->save();

        if ($status !== License::ACTIVE) {
            // Pending downloads stop working immediately.
            DownloadToken::query()->where('license_id', $license->id)->whereNull('used_at')->update(['expires_at' => now()]);
        }

        $this->event($license, $status === License::ACTIVE ? 'reinstated' : $status, $actor, details: ['from' => $from, 'reason' => $reason]);

        return $license;
    }

    public function extendUpdates(License $license, int $months, ?User $actor = null): License
    {
        $base = $license->updates_until !== null && $license->updates_until->isFuture() ? $license->updates_until : now();
        $license->forceFill(['updates_until' => $base->addMonths($months)])->save();
        $this->event($license, 'updates_extended', $actor, details: ['months' => $months, 'until' => $license->updates_until?->toIso8601String()]);

        return $license;
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public function event(License $license, string $action, ?User $actor = null, ?string $instanceId = null, ?string $ip = null, array $details = []): void
    {
        LicenseEvent::query()->forceCreate([
            'license_id' => $license->id,
            'action' => $action,
            'actor_id' => $actor?->id,
            'instance_id' => $instanceId,
            'ip_address' => $ip,
            'details' => $details === [] ? null : $details,
        ]);

        if (in_array($action, ['issued', 'revoked', 'refunded', 'suspended', 'reinstated', 'expired', 'updates_extended'], true)) {
            $this->audit->record("license.{$action}", null, ['license_id' => $license->id, 'product_id' => $license->product_id, ...$details], $actor);
        }
    }
}
