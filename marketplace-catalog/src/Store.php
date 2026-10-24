<?php

/**
 * Catalog accounts, settings, prices and purchases.
 * Stored beside the catalog, not in the store database.
 */
final class Store
{
    public PDO $db;

    public function __construct(string $directory)
    {
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $this->db = new PDO('sqlite:'.$directory.'/catalog.sqlite');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->exec('CREATE TABLE IF NOT EXISTS admins (
            id INTEGER PRIMARY KEY,
            email TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            created_at TEXT NOT NULL
        )');
        $this->db->exec('CREATE TABLE IF NOT EXISTS customers (
            id INTEGER PRIMARY KEY,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            created_at TEXT NOT NULL
        )');
        $this->db->exec('CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL
        )');
        $this->db->exec('CREATE TABLE IF NOT EXISTS offers (
            extension TEXT PRIMARY KEY,
            tier TEXT NOT NULL,
            price TEXT NOT NULL
        )');
        $this->db->exec('CREATE TABLE IF NOT EXISTS orders (
            id INTEGER PRIMARY KEY,
            customer_id INTEGER NOT NULL,
            extension TEXT NOT NULL,
            product_name TEXT NOT NULL,
            amount TEXT NOT NULL,
            currency TEXT NOT NULL,
            stripe_session TEXT NOT NULL UNIQUE,
            status TEXT NOT NULL,
            license_key TEXT,
            created_at TEXT NOT NULL
        )');
        $this->db->exec('CREATE TABLE IF NOT EXISTS listings (
            extension TEXT PRIMARY KEY,
            data TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )');
        $this->db->exec('CREATE TABLE IF NOT EXISTS categories (
            slug TEXT PRIMARY KEY, name TEXT NOT NULL, description TEXT NOT NULL DEFAULT \'\',
            type TEXT NOT NULL DEFAULT \'all\', position INTEGER NOT NULL DEFAULT 0, enabled INTEGER NOT NULL DEFAULT 1
        )');
        if ($this->setting('categories_initialized') !== '1') {
            $seed = $this->db->prepare('INSERT OR IGNORE INTO categories (slug, name, description, type, position) VALUES (?, ?, ?, ?, ?)');
            foreach ([['electronics', 'Electronics', 'Themes for technology, devices and electronics stores.', 'theme'], ['fashion', 'Fashion & lifestyle', 'Style-led themes for clothing and lifestyle brands.', 'theme'], ['home-living', 'Home & living', 'Themes for furniture, interiors and everyday essentials.', 'theme'], ['payments', 'Payments', 'Payment gateways and checkout integrations.', 'module'], ['marketing', 'Marketing & sales', 'Extensions for discovery, engagement and conversions.', 'module'], ['operations', 'Store operations', 'Tools for shipping, content and store management.', 'module']] as $position => $category) {
                $seed->execute([...$category, $position]);
            }
            $this->put('categories_initialized', '1');
        }
        $this->db->exec('CREATE TABLE IF NOT EXISTS submissions (
            id INTEGER PRIMARY KEY,
            customer_id INTEGER NOT NULL,
            extension TEXT NOT NULL,
            data TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT \'pending\',
            review_note TEXT NOT NULL DEFAULT \'\',
            reviewed_by INTEGER,
            created_at TEXT NOT NULL,
            reviewed_at TEXT
        )');
        $this->db->exec('CREATE TABLE IF NOT EXISTS licenses (
            id INTEGER PRIMARY KEY,
            customer_id INTEGER NOT NULL,
            extension TEXT NOT NULL,
            product_name TEXT NOT NULL,
            product_slug TEXT NOT NULL,
            type TEXT NOT NULL,
            license_key TEXT NOT NULL UNIQUE,
            status TEXT NOT NULL DEFAULT \'active\',
            created_at TEXT NOT NULL,
            UNIQUE(customer_id, extension)
        )');
        $this->db->exec('CREATE TABLE IF NOT EXISTS package_releases (
            id INTEGER PRIMARY KEY,
            extension TEXT NOT NULL,
            version TEXT NOT NULL,
            path TEXT NOT NULL,
            sha256 TEXT NOT NULL,
            size INTEGER NOT NULL,
            platform_version TEXT NOT NULL,
            signed_metadata TEXT NOT NULL,
            signature TEXT NOT NULL,
            published_at TEXT NOT NULL,
            UNIQUE(extension, version)
        )');
        $this->db->exec('CREATE TABLE IF NOT EXISTS download_tokens (
            token_hash TEXT PRIMARY KEY,
            license_id INTEGER NOT NULL,
            release_id INTEGER NOT NULL,
            instance_id TEXT NOT NULL,
            expires_at TEXT NOT NULL,
            used_at TEXT
        )');
        $this->db->exec('CREATE TABLE IF NOT EXISTS license_activations (
            license_id INTEGER NOT NULL,
            instance_id TEXT NOT NULL,
            domain TEXT NOT NULL DEFAULT \'\',
            active INTEGER NOT NULL DEFAULT 1,
            activated_at TEXT NOT NULL,
            PRIMARY KEY(license_id, instance_id)
        )');
    }

    public function hasAdmin(): bool
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM admins')->fetchColumn() > 0;
    }

    public function createAdmin(string $email, string $password): void
    {
        $this->db->prepare('INSERT INTO admins (email, password_hash, created_at) VALUES (?, ?, ?)')
            ->execute([$email, password_hash($password, PASSWORD_DEFAULT), date('c')]);
    }

    public function admin(string $email): ?array
    {
        $statement = $this->db->prepare('SELECT * FROM admins WHERE email = ?');
        $statement->execute([$email]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function createCustomer(string $name, string $email, string $password): void
    {
        $this->db->prepare('INSERT INTO customers (name, email, password_hash, created_at) VALUES (?, ?, ?, ?)')
            ->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), date('c')]);
    }

    public function customerByEmail(string $email): ?array
    {
        $statement = $this->db->prepare('SELECT * FROM customers WHERE email = ?');
        $statement->execute([$email]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function customer(int $id): ?array
    {
        $statement = $this->db->prepare('SELECT * FROM customers WHERE id = ?');
        $statement->execute([$id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function setting(string $key, string $default = ''): string
    {
        $statement = $this->db->prepare('SELECT value FROM settings WHERE key = ?');
        $statement->execute([$key]);
        $value = $statement->fetchColumn();

        return is_string($value) ? $value : $default;
    }

    public function put(string $key, string $value): void
    {
        $this->db->prepare('INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value')
            ->execute([$key, $value]);
    }

    public function currency(): string
    {
        $currency = strtoupper($this->setting('currency', 'USD'));

        return in_array($currency, ['AED', 'USD', 'EUR', 'GBP', 'SAR'], true) ? $currency : 'USD';
    }

    public function catalogName(): string
    {
        $name = $this->setting('catalog_name', 'Modrix Marketplace');

        return $name !== '' ? $name : 'Modrix Marketplace';
    }

    /**
     * @param  list<array<string, mixed>>  $products
     * @return list<array<string, mixed>>
     */
    public function priced(array $products): array
    {
        $offers = [];
        foreach ($this->db->query('SELECT extension, tier, price FROM offers') as $row) {
            $offers[$row['extension']] = $row;
        }

        foreach ($products as &$product) {
            $offer = $offers[$product['extension']] ?? null;
            $premium = is_array($offer) && $offer['tier'] === 'premium';
            $product['premium'] = $premium;
            $product['price'] = $premium ? (string) $offer['price'] : '0.00';
            $product['price_label'] = $premium ? $this->money((string) $offer['price']) : 'Free';
        }

        return $products;
    }

    /** Merge repository metadata without changing the installed source packages. */
    public function repository(array $products, bool $publishedOnly = false): array
    {
        $indexed = array_column($products, null, 'extension');
        foreach ($this->db->query('SELECT extension, data, updated_at FROM listings') as $row) {
            $data = json_decode($row['data'], true, flags: JSON_THROW_ON_ERROR);
            $data['updated_at'] = $row['updated_at'];
            $indexed[$row['extension']] = array_replace($indexed[$row['extension']] ?? [], $data);
        }
        $categories = $this->categories();
        foreach ($indexed as &$product) {
            $product += ['status' => 'published', 'repository_url' => '', 'thumbnail' => '', 'source' => 'local', 'demo_url' => '', 'featured' => false, 'features' => [], 'categories' => $this->suggestedCategories($product), 'updated_at' => ''];
            $availableCategories = array_column(array_filter($categories, fn ($c) => $c['type'] === 'all' || $c['type'] === $product['type']), null, 'slug');
            $product['category_labels'] = array_intersect_key($availableCategories, array_flip($product['categories']));
        }
        unset($product);
        if ($publishedOnly) {
            $indexed = array_filter($indexed, fn (array $product) => $product['status'] === 'published');
        }
        $products = $this->priced(array_values($indexed));
        usort($products, fn (array $a, array $b) => strcasecmp($a['name'], $b['name']));

        return $products;
    }

    public function saveListing(array $data): void
    {
        $this->db->prepare('INSERT INTO listings (extension, data, updated_at) VALUES (?, ?, ?) ON CONFLICT(extension) DO UPDATE SET data = excluded.data, updated_at = excluded.updated_at')
            ->execute([$data['extension'], json_encode($data, JSON_THROW_ON_ERROR), date('c')]);
    }

    public function categories(bool $all = false): array
    {
        return $this->db->query('SELECT * FROM categories'.($all ? '' : ' WHERE enabled = 1').' ORDER BY position, name')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveCategory(array $input): void
    {
        $slug = trim((string) ($input['slug'] ?? ''));
        $name = trim((string) ($input['name'] ?? ''));
        $type = (string) ($input['type'] ?? 'all');
        $description = trim((string) ($input['description'] ?? ''));
        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) || strlen($slug) > 80 || $name === '' || mb_strlen($name) > 80 || mb_strlen($description) > 500 || ! in_array($type, ['all', 'theme', 'module'], true)) {
            throw new RuntimeException('Use a unique lowercase slug, a category name up to 80 characters, a description up to 500 characters and a valid package type.');
        }
        $existing = (string) ($input['original_slug'] ?? '');
        $query = $this->db->prepare('SELECT 1 FROM categories WHERE slug = ?');
        $query->execute([$slug]);
        $found = (bool) $query->fetchColumn();
        if (($existing === '' && $found) || ($existing !== '' && ($existing !== $slug || ! $found))) {
            throw new RuntimeException('That category slug already exists or is unavailable. Slugs stay fixed after creation.');
        }
        $this->db->prepare('INSERT INTO categories (slug, name, description, type, position, enabled) VALUES (?, ?, ?, ?, ?, ?) ON CONFLICT(slug) DO UPDATE SET name = excluded.name, description = excluded.description, type = excluded.type, position = excluded.position, enabled = excluded.enabled')
            ->execute([$slug, $name, $description, $type, max(0, min(9999, (int) ($input['position'] ?? 0))), ! empty($input['enabled']) ? 1 : 0]);
    }

    private function suggestedCategories(array $product): array
    {
        $text = strtolower(($product['name'] ?? '').' '.($product['summary'] ?? ''));
        if ($product['type'] === 'theme') {
            return preg_match('/electronic|technology|laptop|gadget/', $text) ? ['electronics'] : (preg_match('/fashion|clothing|apparel|jewel|luxury/', $text) ? ['fashion'] : (preg_match('/furniture|interior|home goods|plants/', $text) ? ['home-living'] : []));
        }

        return preg_match('/sale|marketing|review|newsletter|cross.sell|label|upsell/', strtolower($product['name'])) ? ['marketing'] : (preg_match('/payment gateway|accept cards|hosted payment/', $text) || preg_match('/paypal|stripe|authorize|checkout\.com|telr|tap payments/', strtolower($product['name'])) ? ['payments'] : ['operations']);
    }

    public function submit(int $customerId, array $data, array $existing): int
    {
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            $pending = $this->db->prepare("SELECT 1 FROM submissions WHERE replace(extension, '/', '-') = ? AND status = 'pending'");
            $pending->execute([$data['slug']]);
            if ($pending->fetchColumn() || in_array($data['slug'], array_column($existing, 'slug'), true)) {
                throw new RuntimeException('This package already exists or is awaiting review. Choose a unique package identifier.');
            }
            $this->db->prepare('INSERT INTO submissions (customer_id, extension, data, created_at) VALUES (?, ?, ?, ?)')
                ->execute([$customerId, $data['extension'], json_encode($data, JSON_THROW_ON_ERROR), date('c')]);
            $id = (int) $this->db->lastInsertId();
            $this->db->exec('COMMIT');

            return $id;
        } catch (Throwable $exception) {
            $this->db->exec('ROLLBACK');
            throw $exception;
        }
    }

    /**
     * A newer version of a package that is already in the marketplace.
     *
     * @param  list<array<string, mixed>>  $existing
     */
    public function submitUpdate(int $customerId, array $data, array $existing): int
    {
        $current = null;
        foreach ($existing as $product) {
            if ($product['slug'] === $data['slug']) {
                $current = $product;
                break;
            }
        }
        if ($current === null) {
            throw new RuntimeException('Choose a theme or extension that is already in the marketplace.');
        }
        if (version_compare((string) $data['version'], (string) $current['version'], '<=')) {
            throw new RuntimeException('The new version must be greater than v'.$current['version'].'.');
        }
        if (($data['package'] ?? '') === '') {
            throw new RuntimeException('Upload the new package ZIP.');
        }
        $data['kind'] = 'update';

        $this->db->exec('BEGIN IMMEDIATE');
        try {
            $pending = $this->db->prepare("SELECT 1 FROM submissions WHERE replace(extension, '/', '-') = ? AND status = 'pending'");
            $pending->execute([$data['slug']]);
            if ($pending->fetchColumn()) {
                throw new RuntimeException('This package already has an update waiting for review.');
            }
            $this->db->prepare('INSERT INTO submissions (customer_id, extension, data, created_at) VALUES (?, ?, ?, ?)')
                ->execute([$customerId, $data['extension'], json_encode($data, JSON_THROW_ON_ERROR), date('c')]);
            $id = (int) $this->db->lastInsertId();
            $this->db->exec('COMMIT');

            return $id;
        } catch (Throwable $exception) {
            $this->db->exec('ROLLBACK');
            throw $exception;
        }
    }

    public function submissions(?int $customerId = null): array
    {
        $query = $this->db->prepare('SELECT s.*, c.name AS submitter, c.email FROM submissions s JOIN customers c ON c.id = s.customer_id'.($customerId === null ? '' : ' WHERE s.customer_id = ?').' ORDER BY s.id DESC');
        $query->execute($customerId === null ? [] : [$customerId]);

        return array_map(function (array $row) {
            $row['product'] = json_decode($row['data'], true, flags: JSON_THROW_ON_ERROR);
            unset($row['data']);

            return $row;
        }, $query->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @param  list<array<string, mixed>>  $existing
     * @return array<string, mixed>|null the published package when an update is approved
     */
    public function review(int $id, string $decision, string $note, int $adminId, array $existing): ?array
    {
        if (! in_array($decision, ['approved', 'rejected'], true) || ($decision === 'rejected' && trim($note) === '')) {
            throw new RuntimeException('Choose a decision and include a reason when rejecting a submission.');
        }
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            $query = $this->db->prepare("SELECT * FROM submissions WHERE id = ? AND status = 'pending'");
            $query->execute([$id]);
            $row = $query->fetch(PDO::FETCH_ASSOC);
            if (! is_array($row)) {
                throw new RuntimeException('This submission has already been reviewed or does not exist.');
            }
            $data = json_decode($row['data'], true, flags: JSON_THROW_ON_ERROR);
            $published = null;
            if ($decision === 'approved' && ($data['kind'] ?? '') === 'update') {
                $published = $this->applyUpdate($data, $existing);
            } elseif ($decision === 'approved') {
                if (in_array($data['slug'], array_column($existing, 'slug'), true)) {
                    throw new RuntimeException('A repository package already uses this identifier.');
                }
                $data['status'] = 'published';
                $data['source'] = 'submission';
                $this->saveListing($data);
                $this->saveOffers([['extension' => $data['extension'], 'tier' => $data['tier'], 'price' => $data['price']]]);
            }
            $this->db->prepare('UPDATE submissions SET status = ?, review_note = ?, reviewed_by = ?, reviewed_at = ? WHERE id = ?')
                ->execute([$decision, $note, $adminId, date('c'), $id]);
            $this->db->exec('COMMIT');

            return $published;
        } catch (Throwable $exception) {
            $this->db->exec('ROLLBACK');
            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $existing
     * @return array<string, mixed>
     */
    public function applyUpdate(array $data, array $existing): array
    {
        $current = null;
        foreach ($existing as $product) {
            if ($product['extension'] === $data['extension']) {
                $current = $product;
                break;
            }
        }
        if ($current === null) {
            throw new RuntimeException('This package is no longer in the marketplace.');
        }
        if (version_compare((string) $data['version'], (string) $current['version'], '<=')) {
            throw new RuntimeException('The new version must be greater than v'.$current['version'].'.');
        }
        if (($data['package'] ?? '') === '') {
            throw new RuntimeException('The update does not include a package ZIP.');
        }

        $listing = [
            'extension' => $current['extension'],
            'slug' => $current['slug'],
            'name' => $current['name'],
            'summary' => $current['summary'],
            'type' => $current['type'],
            'version' => $data['version'],
            'platform_version' => $data['platform_version'] ?? $current['platform_version'],
            'author' => $current['author'] ?? '',
            'license' => $current['license'] ?? 'MIT',
            'repository_url' => $current['repository_url'] ?? '',
            'demo_url' => $data['demo_url'] ?? $current['demo_url'] ?? '',
            'categories' => $current['categories'] ?? [],
            'featured' => $current['featured'] ?? false,
            'features' => $current['features'] ?? [],
            'thumbnail' => $current['thumbnail'] ?? '',
            'package' => $data['package'],
            'status' => 'published',
            'source' => $current['source'] ?? 'local',
            'changelog' => (string) ($data['changelog'] ?? ''),
            'tier' => ($current['premium'] ?? false) ? 'premium' : 'free',
            'price' => (string) ($current['price'] ?? '0.00'),
        ];
        $this->saveListing($listing);

        return array_replace($current, $listing);
    }

    public function saveOffers(array $rows): void
    {
        $statement = $this->db->prepare('INSERT INTO offers (extension, tier, price) VALUES (?, ?, ?) ON CONFLICT(extension) DO UPDATE SET tier = excluded.tier, price = excluded.price');
        foreach ($rows as $row) {
            $statement->execute([$row['extension'], $row['tier'], $row['price']]);
        }
    }

    public function money(string $amount): string
    {
        return $this->currency().' '.number_format((float) $amount, 2);
    }

    public function owns(int $customerId, string $extension): bool
    {
        $statement = $this->db->prepare('SELECT 1 FROM orders WHERE customer_id = ? AND extension = ? AND status = ?');
        $statement->execute([$customerId, $extension, 'paid']);

        return (bool) $statement->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function purchases(int $customerId): array
    {
        $statement = $this->db->prepare('SELECT * FROM orders WHERE customer_id = ? AND status = ? ORDER BY id DESC');
        $statement->execute([$customerId, 'paid']);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function fulfill(int $customerId, string $extension, string $name, string $amount, string $currency, string $session): array
    {
        $existing = $this->db->prepare('SELECT * FROM orders WHERE stripe_session = ?');
        $existing->execute([$session]);
        $row = $existing->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            return $row;
        }

        $key = $this->licenseKey();
        $this->db->prepare('INSERT INTO orders (customer_id, extension, product_name, amount, currency, stripe_session, status, license_key, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$customerId, $extension, $name, $amount, $currency, $session, 'paid', $key, date('c')]);

        return [
            'customer_id' => $customerId,
            'extension' => $extension,
            'product_name' => $name,
            'amount' => $amount,
            'currency' => $currency,
            'license_key' => $key,
            'status' => 'paid',
        ];
    }

    public function licenseKey(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $groups = [];
        for ($group = 0; $group < 4; $group++) {
            $part = '';
            for ($i = 0; $i < 5; $i++) {
                $part .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $groups[] = $part;
        }

        return 'EMK-'.implode('-', $groups);
    }
}
