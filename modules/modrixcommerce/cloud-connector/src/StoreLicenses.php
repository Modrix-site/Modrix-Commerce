<?php

namespace EcoModular\CloudConnector;

use App\Core\Audit\AuditLogger;
use App\Core\Marketplace\Client\StoreLicense;
use App\Core\Marketplace\PackageException;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

/**
 * Licenses activated on this store: activate, check (status, update
 * availability), deactivate. A failed check never changes anything the
 * store relies on; it is recorded and shown in the admin.
 */
final class StoreLicenses
{
    public function __construct(
        private readonly CloudClient $client,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @throws CloudError
     */
    public function activate(string $key, User $actor): StoreLicense
    {
        $key = strtoupper(trim($key));
        $signed = $this->client->activate($key);
        $token = $signed['token'];

        $license = StoreLicense::query()->where('product_slug', $token['product'])->first() ?? new StoreLicense;
        $license->forceFill([
            'product_slug' => (string) $token['product'],
            'product_name' => (string) $token['product'],
            'extension_name' => (string) $token['extension'],
            // Older Modrix Cloud tokens carried the license kind (free/paid) here instead of the package type.
            'type' => self::packageType($token['type'] ?? null) ?? $license->type ?? 'module',
            'key_encrypted' => Crypt::encryptString($key),
            'key_hint' => substr($key, -5),
        ]);
        $this->applyToken($license, $signed);

        try {
            foreach ($this->client->catalog() as $product) {
                if (($product['slug'] ?? null) === $license->product_slug) {
                    $license->product_name = (string) $product['name'];
                    $license->type = self::packageType($token['type'] ?? null) ?? self::packageType($product['type'] ?? null) ?? $license->type;
                }
            }
        } catch (CloudError) {
            // The display name is cosmetic; the license is already active.
        }

        $license->save();
        $this->refreshUpdate($license);
        $this->installAvailable($license, $actor);
        $this->audit->record('marketplace.license_activated', null, ['product' => $license->product_slug, 'hint' => $license->key_hint], $actor);

        return $license;
    }

    /**
     * One-click install from the catalog: the connected account's license
     * for the package (issued by Modrix Cloud for a free package), activated
     * here through the signed flow. The key never passes through the browser.
     *
     * @param  bool  $moveHere  free the license from the store it is active on first
     *
     * @throws CloudError
     */
    public function activateFromAccount(string $identifier, User $actor, bool $moveHere = false): StoreLicense
    {
        $license = $this->client->claim($identifier);

        if (($license['in_use_elsewhere'] ?? false) === true) {
            if (! $moveHere) {
                $where = is_string($license['active_domain'] ?? null) && $license['active_domain'] !== '' ? " on {$license['active_domain']}" : ' on another store';

                throw new CloudError("Your license for this package is active{$where}. Choose Move here to use it on this store instead.", errorCode: 'license_in_use');
            }

            $this->client->release($identifier);
        }

        return $this->activate((string) $license['key'], $actor);
    }

    /**
     * Adds the connected account's licenses that are not on this store yet.
     * Licenses active on another store are left alone (they need Move here).
     *
     * @return array{added: list<string>, elsewhere: list<string>}
     *
     * @throws CloudError
     */
    public function sync(User $actor): array
    {
        $result = ['added' => [], 'elsewhere' => []];
        $present = StoreLicense::query()->pluck('extension_name')->all();

        foreach ($this->client->accountLicenses() as $license) {
            $identifier = (string) ($license['identifier'] ?? '');

            if ($identifier === '' || ($license['status'] ?? null) !== 'active' || in_array($identifier, $present, true)) {
                continue;
            }

            if (($license['in_use_elsewhere'] ?? false) === true) {
                $result['elsewhere'][] = (string) ($license['name'] ?? $identifier);

                continue;
            }

            $this->activate((string) $license['key'], $actor);
            $result['added'][] = (string) ($license['name'] ?? $identifier);
        }

        return $result;
    }

    /**
     * Refreshes status and update availability. Network failures are recorded, not thrown.
     */
    public function check(StoreLicense $license, ?User $actor = null): StoreLicense
    {
        try {
            $this->applyToken($license, $this->client->check($license->key()));
            $license->last_error = null;
            $license->save();
            $this->refreshUpdate($license);
            $this->installAvailable($license, $actor);
        } catch (CloudError $e) {
            $license->forceFill(['last_error' => $e->getMessage()])->save();
        }

        return $license;
    }

    /**
     * @throws CloudError
     */
    public function deactivate(StoreLicense $license, User $actor): void
    {
        $this->client->deactivate($license->key());
        $this->audit->record('marketplace.license_deactivated', null, ['product' => $license->product_slug], $actor);
        $license->delete();
    }

    /**
     * @param  array{token: array<string, mixed>, signature: string}  $signed
     */
    private function applyToken(StoreLicense $license, array $signed): void
    {
        $token = $signed['token'];

        // Keeps the package identity in step with the signed token, which also
        // repairs licenses recorded from tokens that carried "free"/"paid" as type.
        if (($type = self::packageType($token['type'] ?? null)) !== null) {
            $license->type = $type;
        }

        $license->forceFill([
            'status' => (string) $token['status'],
            'updates_until' => is_string($token['updates_until'] ?? null) ? Carbon::parse($token['updates_until']) : null,
            'token' => $signed,
            'token_valid_until' => Carbon::parse((string) $token['valid_until']),
            'last_checked_at' => now(),
        ]);
    }

    /** "module" or "theme"; null for anything else (such as a license kind). */
    public static function packageType(mixed $type): ?string
    {
        return in_array($type, ['module', 'theme'], true) ? $type : null;
    }

    private function refreshUpdate(StoreLicense $license): void
    {
        if ($license->status !== 'active') {
            $license->forceFill(['available_release' => null])->save();

            return;
        }

        try {
            $update = $this->client->update($license->key(), $this->installedVersion($license));
            $license->forceFill(['available_release' => ($update['available'] ?? false) ? ($update['release'] ?? null) : null])->save();
        } catch (CloudError $e) {
            $license->forceFill(['last_error' => $e->getMessage()])->save();
        }
    }

    /**
     * A newer signed release is pulled from the marketplace and installed
     * on this store. The same version is left untouched.
     */
    private function installAvailable(StoreLicense $license, ?User $actor): void
    {
        $available = $license->available_release['version'] ?? null;
        $installed = $this->installedVersion($license);
        if (! is_string($available) || $available === '' || $available === $installed) {
            return;
        }

        try {
            app(PackageInstaller::class)->install($license, $actor);
            $license->refresh();
        } catch (CloudError|PackageException $e) {
            $license->forceFill(['last_error' => $e->getMessage()])->save();
        }
    }

    /**
     * Checks every license on this store; newer signed releases are
     * downloaded, verified and put in place (as a daily check does).
     *
     * @return array{updated: list<string>, failed: list<string>}
     */
    public function checkAll(?User $actor = null): array
    {
        $result = ['updated' => [], 'failed' => []];

        foreach (StoreLicense::query()->orderBy('product_name')->get() as $license) {
            $before = $license->installedVersion();
            $license = $this->check($license, $actor);
            $after = $license->installedVersion();

            if ($after !== null && $after !== $before) {
                $result['updated'][] = "{$license->product_name} v{$after}";
            } elseif ($license->last_error !== null) {
                $result['failed'][] = $license->product_name;
            }
        }

        return $result;
    }

    public function installedVersion(StoreLicense $license): ?string
    {
        return $license->installedVersion();
    }
}
