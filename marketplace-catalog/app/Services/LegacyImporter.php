<?php

namespace Marketplace\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Marketplace\Models\Category;
use Marketplace\Models\License;
use Marketplace\Models\LicenseActivation;
use Marketplace\Models\Order;
use Marketplace\Models\Package;
use Marketplace\Models\PackageRelease;
use Marketplace\Models\Setting;
use Marketplace\Models\Submission;
use Marketplace\Models\User;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Copies the original catalog app's SQLite database (storage/catalog.sqlite)
 * into this app. Safe to run again: rows that already exist are matched by
 * their natural keys (email, identifier, license key, Stripe session, ...).
 */
class LegacyImporter
{
    /** Settings whose values are secrets (stored encrypted here). */
    private const SECRET_SETTINGS = ['stripe_secret', 'stripe_webhook_secret', 'smtp_password'];

    /** Set after the first import; later runs keep settings changed in the new admin. */
    private const IMPORTED_MARKER = 'legacy_imported_at';

    private PDO $db;

    private string $dir;

    /** @var array<int, int> legacy customer id => user id */
    private array $customers = [];

    /** @var array<int, int> legacy admin id => user id */
    private array $admins = [];

    /** @var array<string, Package> identifier => package */
    private array $packages = [];

    /** @var array<int, int> legacy license id => license id */
    private array $licenses = [];

    /** @var list<string> */
    public array $warnings = [];

    public function __construct(private readonly Releases $releases) {}

    /**
     * @return array<string, int> rows processed per kind
     */
    public function import(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("The legacy database {$path} does not exist.");
        }

        $this->db = new PDO('sqlite:'.$path, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->dir = dirname($path);
        $this->warnings = [];
        $first = Setting::get(self::IMPORTED_MARKER) === null;
        $counts = ['settings' => $this->settings($first),'users' => $this->users(), 'categories' => $this->categories()];
        $counts['packages'] = $this->packages();
        $counts['licenses'] = $this->licenses();
        $counts['activations'] = $this->activations();
        $counts['orders'] = $this->orders();
        $counts['submissions'] = $this->submissions();
        $counts['releases'] = $this->releaseFiles();
        Setting::put(self::IMPORTED_MARKER, now()->toIso8601String());

        return $counts;
    }

    /**
     * The first import copies every setting (including the signing key, so
     * stores keep trusting this server). Later runs only add settings that do
     * not exist yet, so values changed in the new admin are kept.
     */
    private function settings(bool $first): int
    {
        $n = 0;

        foreach ($this->rows('SELECT key, value FROM settings') as $row) {
            $key = (string) $row['key'];
            $value = (string) $row['value'];

            if ($key === 'categories_initialized') {
                continue;
            }

            $n++;
            $target = $key === 'license_signing_secret' ? SigningKey::SETTING : $key;

            if (! $first && Setting::query()->whereKey($target)->exists()) {
                continue;
            }

            if ($key === 'logo') {
                // A public path in the old app; copied into private storage, served at /branding/logo.
                $logo = $this->copyPrivate($value, 'branding');
                $logo === null ? $this->warnings[] = "Logo {$value} skipped: the file was not found." : Setting::put('logo', $logo);
            } elseif ($key === 'license_signing_secret') {
                // The same key, so stores keep trusting every answer and release.
                Setting::put(SigningKey::SETTING, $value, secret: true);
                app()->forgetScopedInstances();
            } else {
                Setting::put($key, $value, secret: in_array($key, self::SECRET_SETTINGS, true) || str_contains($key, 'password') || str_contains($key, 'secret'));
            }
        }

        return $n;
    }

    private function users(): int
    {
        $n = 0;

        foreach ($this->rows('SELECT * FROM customers') as $row) {
            $this->customers[(int) $row['id']] = $this->user((string) $row['email'], (string) $row['name'], (string) $row['password_hash'], (string) $row['created_at'], false);
            $n++;
        }

        foreach ($this->rows('SELECT * FROM admins') as $row) {
            $this->admins[(int) $row['id']] = $this->user((string) $row['email'], 'Administrator', (string) $row['password_hash'], (string) $row['created_at'], true);
            $n++;
        }

        return $n;
    }

    /**
     * Keeps the existing password hash (bcrypt), so people sign in as before.
     * An account created here with a different password (someone registering
     * the email before the import) is left alone: no merge, no promotion.
     */
    private function user(string $email, string $name, string $hash, string $created, bool $admin): ?int
    {
        $email = Str::lower($email);

        if (strlen($email) > 191) {
            $this->warnings[] = "Account {$email} skipped: emails longer than 191 characters are not supported.";

            return null;
        }

        $existing = User::query()->where('email', $email)->first();

        if ($existing instanceof User) {
            if (! hash_equals((string) $existing->getAuthPassword(), $hash)) {
                $this->warnings[] = "Account {$email} skipped: an account with this email was created here with a different password. Its licenses and orders were not attached; check it before merging by hand.";

                return null;
            }

            if ($admin && ! $existing->is_admin) {
                $existing->forceFill(['is_admin' => true])->save();
            }

            return $existing->id;
        }

        return (int) DB::table('users')->insertGetId([
            'name' => $name,
            'email' => $email,
            'password' => $hash,
            'is_admin' => $admin,
            'created_at' => $this->date($created),
            'updated_at' => now(),
        ]);
    }

    private function categories(): int
    {
        $n = 0;

        foreach ($this->rows('SELECT * FROM categories') as $row) {
            $slug = self::categorySlug((string) $row['slug']);

            if ($slug !== (string) $row['slug']) {
                $this->warnings[] = "Category {$row['slug']} renamed to {$slug}: slugs are limited to 64 characters.";
            }

            Category::query()->firstOrCreate(['slug' => $slug], [
                'name' => Str::limit((string) $row['name'], 120, ''),
                'description' => Str::limit((string) $row['description'], 255, ''),
                'type' => (string) $row['type'],
                'position' => (int) $row['position'],
                'enabled' => (bool) $row['enabled'],
            ]);
            $n++;
        }

        return $n;
    }

    /**
     * Packages from listings, prices, releases, licenses and orders: the
     * original app knew some packages only from the files it scanned.
     */
    private function packages(): int
    {
        $info = [];
        $currency = (string) (Setting::get('currency') ?: config('marketplace.currency'));

        foreach ($this->rows('SELECT extension, data FROM listings') as $row) {
            $info[(string) $row['extension']]['listing'] = json_decode((string) $row['data'], true) ?: [];
        }

        foreach ($this->rows('SELECT * FROM offers') as $row) {
            $info[(string) $row['extension']]['offer'] = $row;
        }

        foreach ($this->rows('SELECT extension, product_name, product_slug, type FROM licenses') as $row) {
            $info[(string) $row['extension']]['license'] ??= $row;
        }

        foreach ($this->rows('SELECT extension, product_name FROM orders') as $row) {
            $info[(string) $row['extension']]['order'] ??= $row;
        }

        foreach ($this->rows('SELECT extension, path FROM package_releases') as $row) {
            $info[(string) $row['extension']]['release'] ??= $row;
        }

        foreach ($info as $identifier => $sources) {
            $listing = $sources['listing'] ?? [];
            $offer = $sources['offer'] ?? null;
            $paid = $offer !== null && $offer['tier'] !== 'free' && (float) $offer['price'] > 0;
            $type = $listing['type'] ?? $this->typeFromPath((string) ($sources['release']['path'] ?? '')) ?? 'module';

            $package = Package::query()->firstOrCreate(['identifier' => $identifier], [
                'type' => in_array($type, ['theme', 'module'], true) ? $type : 'module',
                'slug' => $this->uniqueSlug((string) ($listing['slug'] ?? $sources['license']['product_slug'] ?? str_replace('/', '-', $identifier))),
                'name' => Str::limit((string) ($listing['name'] ?? $sources['license']['product_name'] ?? $sources['order']['product_name'] ?? $identifier), 160, ''),
                'summary' => Str::limit((string) ($listing['summary'] ?? ''), 250, ''),
                'description' => $listing['description'] ?? null,
                'price' => $paid ? number_format((float) $offer['price'], 2, '.', '') : '0.00',
                'currency' => $currency,
                'is_free' => ! $paid,
                'status' => in_array($listing['status'] ?? null, ['published', 'hidden', 'draft'], true) ? $listing['status'] : (isset($sources['release']) ? 'published' : 'draft'),
                'featured' => (bool) ($listing['featured'] ?? false),
                'demo_url' => $this->url($identifier, 'demo URL', (string) ($listing['demo_url'] ?? '')),
                'repository_url' => $this->url($identifier, 'repository URL', (string) ($listing['repository_url'] ?? '')),
                'thumbnail_path' => $this->copyUpload((string) ($listing['thumbnail'] ?? '')),
                'features' => array_values(array_filter((array) ($listing['features'] ?? []), 'is_string')),
                'author' => Str::limit((string) ($listing['author'] ?? ''), 120, ''),
                'license_name' => Str::limit((string) (($listing['license'] ?? '') ?: 'MIT'), 64, ''),
                'version' => Str::limit((string) (($listing['version'] ?? '') ?: '1.0.0'), 32, ''),
                'platform_version' => Str::limit((string) (($listing['platform_version'] ?? '') ?: '^1.0'), 64, ''),
                'changelog' => ($listing['changelog'] ?? '') ?: null,
            ]);

            if (! empty($listing['categories'])) {
                $slugs = array_map(fn ($s) => self::categorySlug((string) $s), (array) $listing['categories']);
                $ids = Category::query()->whereIn('slug', $slugs)->pluck('id');
                $package->categories()->syncWithoutDetaching($ids);
            }

            $this->packages[$identifier] = $package;
        }

        return count($info);
    }

    private function licenses(): int
    {
        $n = 0;

        foreach ($this->rows('SELECT * FROM licenses') as $row) {
            $userId = $this->customers[(int) $row['customer_id']] ?? null;
            $package = $this->packages[(string) $row['extension']] ?? null;

            if ($userId === null || $package === null) {
                $this->warnings[] = "License {$row['license_key']} skipped: unknown customer or package.";

                continue;
            }

            $license = License::query()->where('key', (string) $row['license_key'])->first()
                ?? License::query()->where('user_id', $userId)->where('package_id', $package->id)->first()
                ?? License::query()->create([
                    'user_id' => $userId,
                    'package_id' => $package->id,
                    'key' => (string) $row['license_key'],
                    'type' => $row['type'] === 'paid' ? 'paid' : 'free',
                    'status' => in_array($row['status'], ['active', 'revoked', 'refunded'], true) ? $row['status'] : 'active',
                ]);
            $this->licenses[(int) $row['id']] = $license->id;
            $n++;
        }

        return $n;
    }

    private function activations(): int
    {
        $n = 0;

        foreach ($this->rows('SELECT * FROM license_activations') as $row) {
            $licenseId = $this->licenses[(int) $row['license_id']] ?? null;

            if ($licenseId === null) {
                continue;
            }

            LicenseActivation::query()->firstOrCreate(
                ['license_id' => $licenseId, 'instance_id' => (string) $row['instance_id']],
                ['domain' => ((string) $row['domain']) ?: null, 'active' => (bool) $row['active'], 'activated_at' => $this->date((string) $row['activated_at'])],
            );
            $n++;
        }

        return $n;
    }

    private function orders(): int
    {
        $n = 0;

        foreach ($this->rows('SELECT * FROM orders') as $row) {
            $userId = $this->customers[(int) $row['customer_id']] ?? null;
            $package = $this->packages[(string) $row['extension']] ?? null;

            if ($userId === null || $package === null) {
                continue;
            }

            Order::query()->firstOrCreate(['stripe_session' => (string) $row['stripe_session']], [
                'user_id' => $userId,
                'package_id' => $package->id,
                'amount' => (string) $row['amount'],
                'currency' => strtoupper((string) $row['currency']),
                'status' => in_array($row['status'], ['pending', 'paid', 'expired', 'refunded'], true) ? $row['status'] : 'pending',
                'license_id' => $row['license_key'] ? License::query()->where('key', (string) $row['license_key'])->value('id') : null,
                'paid_at' => $row['status'] === 'paid' ? $this->date((string) $row['created_at']) : null,
                'created_at' => $this->date((string) $row['created_at']),
            ]);
            $n++;
        }

        return $n;
    }

    private function submissions(): int
    {
        $n = 0;

        foreach ($this->rows('SELECT * FROM submissions') as $row) {
            $userId = $this->customers[(int) $row['customer_id']] ?? null;

            if ($userId === null) {
                continue;
            }

            $created = $this->date((string) $row['created_at']);
            $exists = Submission::query()->where('user_id', $userId)->where('identifier', (string) $row['extension'])->where('created_at', $created)->exists();

            if (! $exists) {
                $data = json_decode((string) $row['data'], true) ?: [];
                $submission = new Submission;
                $submission->forceFill([
                    'user_id' => $userId,
                    'identifier' => (string) $row['extension'],
                    'kind' => ($data['kind'] ?? '') === 'update' ? 'update' : 'new',
                    'data' => $data,
                    'zip_path' => $this->copyPrivate((string) ($data['package'] ?? ''), 'submissions'),
                    'status' => in_array($row['status'], ['pending', 'approved', 'rejected'], true) ? $row['status'] : 'pending',
                    'review_note' => ((string) $row['review_note']) ?: null,
                    'reviewed_by' => $this->admins[(int) $row['reviewed_by']] ?? null,
                    'reviewed_at' => $row['reviewed_at'] ? $this->date((string) $row['reviewed_at']) : null,
                    'created_at' => $created,
                ])->save();
            }

            $n++;
        }

        return $n;
    }

    /** Copies each release ZIP and signs it again with the (imported) key. */
    private function releaseFiles(): int
    {
        $n = 0;

        foreach ($this->rows('SELECT * FROM package_releases') as $row) {
            $package = $this->packages[(string) $row['extension']] ?? null;

            if ($package === null) {
                continue;
            }

            if (PackageRelease::query()->where('package_id', $package->id)->where('version', (string) $row['version'])->exists()) {
                $n++;

                continue;
            }

            $zip = $this->resolveReleaseZip($row, $package);

            if ($zip === null) {
                $this->warnings[] = "Release {$row['extension']} {$row['version']} skipped: its ZIP was not found.";

                continue;
            }

            $release = $this->releases->publish($package, $zip, (string) $row['version'], (string) $row['platform_version']);
            $release->forceFill(['published_at' => $this->date((string) $row['published_at'])])->save();
            $n++;
        }

        return $n;
    }

    /** @param array<string, mixed> $row */
    private function resolveReleaseZip(array $row, Package $package): ?string
    {
        $candidates = [(string) $row['path'], $this->dir.'/sources/'.$package->type.'/'.$row['extension'].'/'.$row['version'].'.zip'];

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function copyUpload(string $publicPath): ?string
    {
        return $this->copyPrivate($publicPath, 'thumbnails');
    }

    /** Copies a file from the legacy storage folder into private storage. */
    private function copyPrivate(string $legacyPath, string $folder): ?string
    {
        if ($legacyPath === '') {
            return null;
        }

        $source = $this->dir.'/'.ltrim(str_replace('\\', '/', $legacyPath), '/');

        if (! is_file($source)) {
            return null;
        }

        $relative = $folder.'/'.basename($source);
        $target = rtrim((string) config('marketplace.storage'), '/\\').'/'.$relative;
        File::ensureDirectoryExists(dirname($target));

        if (! is_file($target)) {
            File::copy($source, $target);
        }

        return $relative;
    }

    /** Category slugs are 64 characters here (the old app allowed 80). */
    private static function categorySlug(string $slug): string
    {
        return rtrim(substr($slug, 0, 64), '-');
    }

    /** URLs longer than the 500-character column are dropped with a warning. */
    private function url(string $identifier, string $label, string $url): ?string
    {
        if (strlen($url) > 500) {
            $this->warnings[] = "{$identifier}: {$label} dropped, it is longer than 500 characters.";

            return null;
        }

        return $url !== '' ? $url : null;
    }

    private function typeFromPath(string $path): ?string
    {
        return preg_match('#[\\\\/]sources[\\\\/](theme|module)[\\\\/]#', $path, $m) === 1 ? $m[1] : null;
    }

    private function uniqueSlug(string $slug): string
    {
        $slug = Str::slug($slug) ?: 'package';
        $candidate = $slug;

        for ($i = 2; Package::query()->where('slug', $candidate)->exists(); $i++) {
            $candidate = "{$slug}-{$i}";
        }

        return $candidate;
    }

    private function date(string $value): Carbon
    {
        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return now();
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(string $sql): array
    {
        try {
            return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            // A table the legacy version did not have yet.
            return [];
        }
    }
}
