<?php

namespace App\Console\Commands;

use App\Core\Marketplace\Signing;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Creates an Ed25519 key pair for signing marketplace releases. The secret
 * goes into the marketplace server's environment only; the public key is
 * what stores trust (ECOMODULAR_MARKETPLACE_TRUSTED_KEYS).
 */
#[Signature('ecomodular:marketplace:keygen')]
#[Description('Generate a marketplace release signing key pair')]
class MarketplaceKeygenCommand extends Command
{
    public function handle(): int
    {
        $keys = Signing::generateKeyPair();

        $this->components->warn('Store the secret key only in the marketplace server environment. Never commit it.');
        $this->line('ECOMODULAR_MARKETPLACE_SIGNING_KEY='.$keys['secret']);
        $this->newLine();
        $this->components->info('Stores trust releases signed with this public key:');
        $this->line('ECOMODULAR_MARKETPLACE_TRUSTED_KEYS='.$keys['public']);
        $this->line('Key id: '.Signing::keyId((string) base64_decode($keys['public'], true)));

        return self::SUCCESS;
    }
}
