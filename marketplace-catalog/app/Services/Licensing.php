<?php

namespace Marketplace\Services;

use Illuminate\Database\UniqueConstraintViolationException;
use Marketplace\Models\License;
use Marketplace\Models\Package;
use Marketplace\Models\User;

/**
 * Issues license keys and the signed status tokens stores verify.
 */
class Licensing
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function __construct(private readonly SigningKey $key) {}

    /**
     * One stable key per customer and package: issuing again returns it.
     */
    public function issue(User $user, Package $package, string $type): License
    {
        $existing = License::query()->where('user_id', $user->id)->where('package_id', $package->id)->first();

        if ($existing instanceof License) {
            return $existing;
        }

        try {
            return License::query()->create([
                'user_id' => $user->id,
                'package_id' => $package->id,
                'key' => $this->newKey(),
                'type' => $type,
                'status' => 'active',
            ]);
        } catch (UniqueConstraintViolationException) {
            // A concurrent request issued it first.
            return License::query()->where('user_id', $user->id)->where('package_id', $package->id)->firstOrFail();
        }
    }

    /**
     * The signed status of a license for one store installation.
     *
     * @return array{token: array<string, mixed>, signature: string}
     */
    public function token(License $license, string $instanceId): array
    {
        $license->loadMissing('package');
        $issued = now();
        $activated = $license->activations()->where('instance_id', $instanceId)->where('active', true)->exists();

        $signed = $this->key->sign([
            'schema' => 1,
            'license_id' => $license->id,
            'product' => $license->package->slug,
            'extension' => $license->package->identifier,
            'type' => $license->type,
            'status' => $license->status,
            'activated' => $activated,
            'instance_id' => $instanceId,
            'updates_until' => null,
            'issued_at' => $issued->toIso8601String(),
            'valid_until' => $issued->copy()->addDays((int) config('marketplace.token_days', 30))->toIso8601String(),
            'advisories' => [],
        ]);

        return ['token' => $signed['data'], 'signature' => $signed['signature']];
    }

    /** MRX- followed by four groups of five base32 characters. */
    private function newKey(): string
    {
        do {
            $groups = [];

            for ($g = 0; $g < 4; $g++) {
                $group = '';

                for ($i = 0; $i < 5; $i++) {
                    $group .= self::ALPHABET[random_int(0, 31)];
                }

                $groups[] = $group;
            }

            $key = 'MRX-'.implode('-', $groups);
        } while (License::query()->where('key', $key)->exists());

        return $key;
    }
}
