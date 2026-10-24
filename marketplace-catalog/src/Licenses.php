<?php

if (! class_exists(Signing::class)) {
    require_once __DIR__.'/Signing.php';
}
require_once __DIR__.'/PackageRelease.php';

use App\Core\Marketplace\Signing;

final class Licenses
{
    public function __construct(private readonly Store $store) {}

    /** A stable key per customer/package. Paid products require a completed purchase. */
    public function generate(int $customerId, array $product): array
    {
        $this->store->db->exec('BEGIN IMMEDIATE');
        try {
            $query = $this->store->db->prepare('SELECT * FROM licenses WHERE customer_id = ? AND extension = ?');
            $query->execute([$customerId, $product['extension']]);
            $license = $query->fetch(PDO::FETCH_ASSOC);
            if (! is_array($license)) {
                if ($product['premium'] && ! $this->store->owns($customerId, $product['extension'])) {
                    throw new RuntimeException('Purchase this paid package before generating a license.');
                }
                // Keep keys already issued with a paid order valid.
                $order = $this->store->db->prepare("SELECT license_key FROM orders WHERE customer_id = ? AND extension = ? AND status = 'paid' AND license_key IS NOT NULL ORDER BY id LIMIT 1");
                $order->execute([$customerId, $product['extension']]);
                $key = $order->fetchColumn() ?: $this->store->licenseKey();
                $this->store->db->prepare('INSERT INTO licenses (customer_id, extension, product_name, product_slug, type, license_key, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$customerId, $product['extension'], $product['name'], $product['slug'], $product['type'], $key, date('c')]);
                $query->execute([$customerId, $product['extension']]);
                $license = $query->fetch(PDO::FETCH_ASSOC);
            }
            $this->store->db->exec('COMMIT');

            return $license;
        } catch (Throwable $exception) {
            $this->store->db->exec('ROLLBACK');
            throw $exception;
        }
    }

    public function forCustomer(int $customerId): array
    {
        $query = $this->store->db->prepare('SELECT l.*, a.instance_id, a.domain FROM licenses l LEFT JOIN license_activations a ON a.license_id = l.id AND a.active = 1 WHERE l.customer_id = ? ORDER BY l.id DESC');
        $query->execute([$customerId]);

        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    public function release(int $customerId, int $licenseId): void
    {
        $query = $this->store->db->prepare('UPDATE license_activations SET active = 0 WHERE license_id IN (SELECT id FROM licenses WHERE id = ? AND customer_id = ?)');
        $query->execute([$licenseId, $customerId]);
    }

    public function publicKey(): string
    {
        return Signing::publicKeyFromSecret($this->secret());
    }

    /** Signs one package version so stores can install it on their next check. */
    public function publishPackage(array $product): void
    {
        (new PackageRelease($this->store))->sync([$product], $this->secret());
    }

    /** Writes the versioned source zips and storage/sources/index.json. */
    public function syncSources(Catalog $catalog): int
    {
        $products = $this->store->repository($catalog->products(), true);

        return count((new PackageRelease($this->store))->sync($products, $this->secret()));
    }

    public function api(string $action, array $input, string $base = ''): array
    {
        $key = is_string($input['key'] ?? null) ? strtoupper(trim($input['key'])) : '';
        $instance = is_string($input['instance_id'] ?? null) ? $input['instance_id'] : '';
        if (strlen($key) > 64 || ! preg_match('/^[A-Za-z0-9-]{16,64}$/D', $instance)) {
            throw new RuntimeException('A license key and a valid store instance identifier are required.', 422);
        }
        // Legacy paid keys become API licenses without needing another purchase.
        $query = $this->store->db->prepare('SELECT * FROM licenses WHERE license_key = ?');
        $query->execute([$key]);
        $license = $query->fetch(PDO::FETCH_ASSOC);
        if (! is_array($license)) {
            $order = $this->store->db->prepare("SELECT * FROM orders WHERE license_key = ? AND status = 'paid'");
            $order->execute([$key]);
            $row = $order->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                $catalog = new Catalog(getenv('CATALOG_ROOT') ?: dirname(__DIR__, 2));
                foreach ($this->store->repository($catalog->products()) as $p) {
                    if ($p['extension'] === $row['extension']) {
                        $license = $this->generate((int) $row['customer_id'], $p);
                        break;
                    }
                }
            }
        }
        if (! is_array($license)) {
            throw new RuntimeException('This license key is not valid.', 404);
        }
        $id = (int) $license['id'];
        if ($action === 'licenses/deactivate') {
            $this->store->db->prepare('UPDATE license_activations SET active = 0 WHERE license_id = ? AND instance_id = ?')->execute([$id, $instance]);

            return ['deactivated' => true];
        }
        if ($action === 'licenses/activate') {
            if ($license['status'] !== 'active') {
                throw new RuntimeException('This license is inactive.', 403);
            }
            $domain = is_string($input['domain'] ?? null) ? substr($input['domain'], 0, 191) : '';
            $this->store->db->exec('BEGIN IMMEDIATE');
            try {
                $query = $this->store->db->prepare('SELECT 1 FROM license_activations WHERE license_id = ? AND active = 1 AND instance_id <> ?');
                $query->execute([$id, $instance]);
                if ($query->fetchColumn()) {
                    throw new RuntimeException('This license is already active on another store. Release it from your marketplace account first.', 403);
                }
                $this->store->db->prepare('INSERT INTO license_activations (license_id, instance_id, domain, activated_at) VALUES (?, ?, ?, ?) ON CONFLICT(license_id, instance_id) DO UPDATE SET active = 1, domain = excluded.domain, activated_at = excluded.activated_at')
                    ->execute([$id, $instance, $domain, date('c')]);
                $this->store->db->exec('COMMIT');
            } catch (Throwable $exception) {
                $this->store->db->exec('ROLLBACK');
                throw $exception;
            }
        }
        $query = $this->store->db->prepare('SELECT 1 FROM license_activations WHERE license_id = ? AND instance_id = ? AND active = 1');
        $query->execute([$id, $instance]);
        $activated = (bool) $query->fetchColumn();
        if ($action === 'updates' || $action === 'downloads') {
            if (! $activated || $license['status'] !== 'active') {
                throw new RuntimeException('Activate this license on the store first.', 403);
            }
            $catalog = new Catalog(getenv('CATALOG_ROOT') ?: dirname(__DIR__, 2));
            $product = null;
            foreach ($this->store->repository($catalog->products()) as $candidate) {
                if ($candidate['extension'] === $license['extension'] && ($candidate['status'] ?? '') === 'published') {
                    $product = $candidate;
                    break;
                }
            }
            if ($product === null) {
                throw new RuntimeException('This package is not available.', 404);
            }
            $releases = new PackageRelease($this->store);

            return $action === 'downloads'
                ? $releases->download($license, $product, $instance, (int) ($input['release_id'] ?? 0), $base, $this->secret())
                : $releases->update($license, $product, is_string($input['installed_version'] ?? null) ? $input['installed_version'] : null, is_string($input['platform_version'] ?? null) ? $input['platform_version'] : null, $this->secret());
        }
        $secret = $this->secret();
        $public = base64_decode(Signing::publicKeyFromSecret($secret), true);
        $token = ['schema' => 1, 'license_id' => $id, 'product' => $license['product_slug'], 'extension' => $license['extension'], 'type' => $license['type'], 'status' => $license['status'], 'activated' => $activated, 'instance_id' => $instance, 'updates_until' => null, 'issued_at' => date('c'), 'valid_until' => date('c', time() + 30 * 86400), 'advisories' => [], 'key_id' => Signing::keyId($public)];

        return ['token' => $token, 'signature' => Signing::sign($token, $secret)];
    }

    private function secret(): string
    {
        $this->store->db->exec('BEGIN IMMEDIATE');
        try {
            $secret = $this->store->setting('license_signing_secret');
            if ($secret === '') {
                $secret = Signing::generateKeyPair()['secret'];
                $this->store->put('license_signing_secret', $secret);
            }
            $this->store->db->exec('COMMIT');

            return $secret;
        } catch (Throwable $exception) {
            $this->store->db->exec('ROLLBACK');
            throw $exception;
        }
    }
}
