<?php

namespace App\Domains\Marketplace;

use App\Core\Marketplace\Signing;
use RuntimeException;

/**
 * Signs release metadata and license tokens with the marketplace server's
 * Ed25519 key (`ECOMODULAR_MARKETPLACE_SIGNING_KEY`). Generate one with
 * `php artisan ecomodular:marketplace:keygen`.
 */
final class MarketplaceSigner
{
    public function configured(): bool
    {
        return is_string(config('ecomodular.marketplace.server.signing_key')) && config('ecomodular.marketplace.server.signing_key') !== '';
    }

    public function keyId(): string
    {
        return Signing::keyId((string) base64_decode(Signing::publicKeyFromSecret($this->secret()), true));
    }

    public function publicKey(): string
    {
        return Signing::publicKeyFromSecret($this->secret());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{metadata: array<string, mixed>, signature: string}
     */
    public function sign(array $data): array
    {
        $metadata = [...$data, 'key_id' => $this->keyId()];

        return ['metadata' => $metadata, 'signature' => Signing::sign($metadata, $this->secret())];
    }

    private function secret(): string
    {
        if (! $this->configured()) {
            throw new RuntimeException('The marketplace signing key is not configured (ECOMODULAR_MARKETPLACE_SIGNING_KEY).');
        }

        return (string) config('ecomodular.marketplace.server.signing_key');
    }
}
