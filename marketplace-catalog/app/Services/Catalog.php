<?php

namespace Marketplace\Services;

use Illuminate\Support\Collection;
use Marketplace\Models\Category;
use Marketplace\Models\License;
use Marketplace\Models\Package;
use Marketplace\Models\Setting;
use Marketplace\Models\User;

/**
 * Catalog data for the pages: published packages with the fields cards and
 * detail pages show, plus browsing (filters, sorting, pages).
 */
class Catalog
{
    public const PER_PAGE = 12;

    /** @var Collection<int, array<string, mixed>>|null */
    private ?Collection $published = null;

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function published(): Collection
    {
        return $this->published ??= Package::query()
            ->where('status', 'published')
            ->with(['categories', 'releases'])
            ->get()
            ->map(fn (Package $p) => $this->present($p));
    }

    public function find(string $slug): ?array
    {
        return $this->published()->firstWhere('slug', $slug);
    }

    /**
     * Fields used by cards and detail pages.
     *
     * @return array<string, mixed>
     */
    public function present(Package $package): array
    {
        $release = $package->latestRelease();
        $currency = (string) (Setting::get('currency') ?: $package->currency);

        return [
            'model' => $package,
            'id' => $package->id,
            'identifier' => $package->identifier,
            'slug' => $package->slug,
            'name' => $package->name,
            'type' => $package->type,
            'summary' => (string) $package->summary,
            'author' => $package->author !== '' ? $package->author : 'Independent publisher',
            'license' => $package->license_name,
            'version' => $release?->version ?? $package->version,
            'platform_version' => $release?->platform_version ?? $package->platform_version,
            'premium' => ! $package->is_free,
            'price' => $package->price,
            'price_label' => $package->is_free ? 'Free' : $currency.' '.$package->price,
            'featured' => $package->featured,
            'demo_url' => $package->demo_url,
            'repository_url' => $package->repository_url,
            'features' => $package->features ?? [],
            'changelog' => $package->changelog,
            'thumbnail' => $package->thumbnail_path ? url('/media/'.$package->slug) : null,
            'has_release' => $release !== null,
            'categories' => $package->categories->pluck('slug')->all(),
            'category_labels' => $package->categories->where('enabled', true)->mapWithKeys(fn (Category $c) => [$c->slug => ['slug' => $c->slug, 'name' => $c->name]])->all(),
            'updated_at' => (string) $package->updated_at,
        ];
    }

    /**
     * Filtered, sorted page of packages for the home page.
     *
     * @param  array<string, string>  $filters  q, type, tier, category, demo, sort
     * @return array{items: Collection<int, array<string, mixed>>, total: int, pages: int, page: int}
     */
    public function browse(array $filters, int $page): array
    {
        $type = $filters['type'] ?? 'all';
        $tier = $filters['tier'] ?? 'all';
        $category = $filters['category'] ?? '';
        $query = trim($filters['q'] ?? '');
        $demo = ($filters['demo'] ?? '') === '1';
        $sort = $filters['sort'] ?? 'featured';

        $visible = $this->published()->filter(fn (array $p) => ($type === 'all' || $p['type'] === $type)
            && ($tier === 'all' || ($tier === 'premium' ? $p['premium'] : ! $p['premium']))
            && ($category === '' || isset($p['category_labels'][$category]))
            && (! $demo || ! empty($p['demo_url']))
            && ($query === '' || stripos($p['name'].' '.$p['summary'].' '.$p['author'].' '.$p['identifier'].' '.implode(' ', array_column($p['category_labels'], 'name')), $query) !== false));

        $visible = $visible->sort(fn (array $a, array $b) => match ($sort) {
            'price_asc' => ((float) $a['price'] <=> (float) $b['price']) ?: strcasecmp($a['name'], $b['name']),
            'price_desc' => ((float) $b['price'] <=> (float) $a['price']) ?: strcasecmp($a['name'], $b['name']),
            'updated' => strcmp($b['updated_at'], $a['updated_at']) ?: strcasecmp($a['name'], $b['name']),
            'name' => strcasecmp($a['name'], $b['name']),
            default => ((int) $b['featured'] <=> (int) $a['featured']) ?: strcasecmp($a['name'], $b['name']),
        })->values();

        $total = $visible->count();
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = max(1, min($pages, $page));

        return ['items' => $visible->slice(($page - 1) * self::PER_PAGE, self::PER_PAGE)->values(), 'total' => $total, 'pages' => $pages, 'page' => $page];
    }

    /** @return array{all: int, theme: int, module: int} */
    public function counts(): array
    {
        $all = $this->published();

        return ['all' => $all->count(), 'theme' => $all->where('type', 'theme')->count(), 'module' => $all->where('type', 'module')->count()];
    }

    /** Whether this person may download or license a paid package. */
    public function owns(?User $user, Package $package): bool
    {
        return $user !== null && License::query()->where('user_id', $user->id)->where('package_id', $package->id)->where('status', 'active')->exists();
    }

    public function catalogName(): string
    {
        return (string) (Setting::get('catalog_name') ?: config('app.name', 'Modrix Marketplace'));
    }
}
