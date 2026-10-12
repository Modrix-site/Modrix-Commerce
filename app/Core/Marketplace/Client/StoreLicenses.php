<?php

namespace App\Core\Marketplace\Client;

use App\Core\Audit\AuditLogger;
use App\Core\Extensions\ModuleRepository;
use App\Core\Themes\ThemeRepository;
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
        private readonly MarketplaceClient $client,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @throws MarketplaceError
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
            'type' => (string) $token['type'],
            'key_encrypted' => Crypt::encryptString($key),
            'key_hint' => substr($key, -5),
        ]);
        $this->applyToken($license, $signed);

        try {
            foreach ($this->client->catalog() as $product) {
                if (($product['slug'] ?? null) === $license->product_slug) {
                    $license->product_name = (string) $product['name'];
                }
            }
        } catch (MarketplaceError) {
            // The display name is cosmetic; the license is already active.
        }

        $license->save();
        $this->refreshUpdate($license);
        $this->audit->record('marketplace.license_activated', null, ['product' => $license->product_slug, 'hint' => $license->key_hint], $actor);

        return $license;
    }

    /**
     * Refreshes status and update availability. Network failures are recorded, not thrown.
     */
    public function check(StoreLicense $license): StoreLicense
    {
        try {
            $this->applyToken($license, $this->client->check($license->key()));
            $license->last_error = null;
            $license->save();
            $this->refreshUpdate($license);
        } catch (MarketplaceError $e) {
            $license->forceFill(['last_error' => $e->getMessage()])->save();
        }

        return $license;
    }

    /**
     * @throws MarketplaceError
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

        $license->forceFill([
            'status' => (string) $token['status'],
            'updates_until' => is_string($token['updates_until'] ?? null) ? Carbon::parse($token['updates_until']) : null,
            'token' => $signed,
            'token_valid_until' => Carbon::parse((string) $token['valid_until']),
            'last_checked_at' => now(),
        ]);
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
        } catch (MarketplaceError $e) {
            $license->forceFill(['last_error' => $e->getMessage()])->save();
        }
    }

    public function installedVersion(StoreLicense $license): ?string
    {
        $dir = $license->type === 'theme'
            ? ThemeRepository::packageDir($license->extension_name)
            : ModuleRepository::packageDir($license->extension_name);
        $file = $dir.'/'.($license->type === 'theme' ? 'theme.json' : 'module.json');
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return is_array($data) && is_string($data['version'] ?? null) ? $data['version'] : null;
    }
}
