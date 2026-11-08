<?php

namespace App\Core\Updates;

use App\Core\Marketplace\Signing;
use App\Core\Marketplace\TrustedKeys;
use App\Core\Platform;
use Composer\Semver\Comparator;
use Throwable;

/**
 * Accepts a core release item from Modrix Cloud only when its signature,
 * kind, hashes and compatibility all check out.
 */
final class CoreReleaseVerifier
{
    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null verified metadata plus notes, published_at and id
     */
    public function verify(array $item, ?string $current = null): ?array
    {
        $verification = $item['verification'] ?? null;
        $metadata = is_array($verification) ? ($verification['metadata'] ?? null) : null;
        $signature = is_array($verification) ? ($verification['signature'] ?? null) : null;

        if (! is_array($metadata) || ! is_string($signature) || ! Signing::verify($metadata, $signature, TrustedKeys::all())) {
            return null;
        }

        $version = $metadata['version'] ?? null;
        $minPhp = $metadata['min_php'] ?? null;
        $sha256 = $metadata['sha256'] ?? null;
        $size = $metadata['size'] ?? null;

        if (($metadata['kind'] ?? null) !== 'core'
            || ! is_string($version) || ! is_string($minPhp)
            || ! is_string($sha256) || ! preg_match('/^[0-9a-f]{64}$/', $sha256)
            || ! is_int($size) || $size <= 0
            || ! isset($item['id'], $metadata['release_id']) || $metadata['release_id'] !== $item['id']
        ) {
            return null;
        }

        if (! $this->newer($version, $current ?? Platform::VERSION) || ! version_compare(PHP_VERSION, $minPhp, '>=')) {
            return null;
        }

        return $metadata + [
            'notes' => is_string($item['notes'] ?? null) ? $item['notes'] : '',
            'published_at' => is_string($item['published_at'] ?? null) ? $item['published_at'] : null,
            'id' => $item['id'],
        ];
    }

    public function newer(string $candidate, string $current): bool
    {
        try {
            return Comparator::greaterThan($candidate, $current);
        } catch (Throwable) {
            return false;
        }
    }
}
