<?php

namespace App\Core\Settings;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Key/value store for platform and store settings.
 *
 * Values are JSON-encoded. All settings are cached as a single map and the
 * cache is invalidated on every write. Keys are namespaced: `store.*`,
 * `system.*`, and later `extension.<name>.*` / `theme.<name>.*`.
 */
final class SettingsRepository
{
    private const CACHE_KEY = 'ecomodular.settings';

    /** @var array<string, mixed>|null */
    private ?array $loaded = null;

    public function __construct(private readonly Cache $cache) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $all = $this->all()) ? $all[$key] : $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->setMany([$key => $value]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function setMany(array $values): void
    {
        $now = now();

        DB::transaction(function () use ($values, $now) {
            foreach ($values as $key => $value) {
                DB::table('settings')->updateOrInsert(
                    ['key' => $key],
                    ['value' => json_encode($value, JSON_THROW_ON_ERROR), 'updated_at' => $now, 'created_at' => $now],
                );
            }
        });

        $this->flush();
    }

    /**
     * Deletes the given keys (used when an extension is uninstalled).
     *
     * @param  list<string>  $keys
     */
    public function forget(array $keys): void
    {
        if ($keys !== []) {
            DB::table('settings')->whereIn('key', $keys)->delete();
        }

        $this->flush();
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->loaded !== null) {
            return $this->loaded;
        }

        // Before migrations have run there is nothing to cache; do not poison the cache with [].
        if (! $this->cache->has(self::CACHE_KEY) && ! Schema::hasTable('settings')) {
            return [];
        }

        /** @var array<string, mixed> $values */
        $values = $this->cache->rememberForever(self::CACHE_KEY, function () {
            return DB::table('settings')
                ->pluck('value', 'key')
                ->map(fn ($value) => $value === null ? null : json_decode((string) $value, true))
                ->all();
        });

        return $this->loaded = $values;
    }

    /** Re-reads the shared cache on next access (long-running workers). */
    public function reload(): void
    {
        $this->loaded = null;
    }

    public function flush(): void
    {
        $this->loaded = null;
        $this->cache->forget(self::CACHE_KEY);
    }
}
