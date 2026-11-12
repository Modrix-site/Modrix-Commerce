<?php

namespace App\Core\Themes;

use App\Core\Localization\ContentTranslations;
use App\Core\Settings\SettingsRepository;
use App\Domains\Catalog\Models\Category;
use App\Domains\Content\Models\Page;

final class StoreNavigation
{
    public const KEY = 'store.navigation';

    public function __construct(private readonly SettingsRepository $settings) {}

    /** @return array<string, mixed> */
    public function forTheme(ResolvedTheme $theme): array
    {
        $saved = $this->settings->get(self::KEY);
        if (is_array($saved) && is_array($saved['footer'] ?? null)) {
            // "custom": the merchant saved menus in Admin → Navigation, so
            // section themes use them instead of their own demo links.
            return [...$this->resolve($saved), 'custom' => true];
        }
        $design = $theme->design;
        $links = [];
        for ($i = 1; $i <= 12; $i++) {
            if (filled($design["menu_{$i}_label"] ?? null)) {
                $links[] = ['label' => $design["menu_{$i}_label"], 'url' => $design["menu_{$i}_link"] ?: '/search'];
            }
        }
        if ($links === []) {
            $links = [['label' => 'All products', 'url' => '/search'], ...Category::query()->active()->whereNull('parent_id')->orderBy('position')->orderBy('name')->limit(12)->get()->map(fn (Category $c) => ['label' => $c->localized('name'), 'url' => '/c/'.$c->slug])->all()];
        }
        $footer = [];
        for ($column = 1; $column <= 3; $column++) {
            $items = [];
            for ($i = 1; $i <= 6; $i++) {
                if (filled($design["footer_{$column}_{$i}_label"] ?? null)) {
                    $items[] = ['label' => $design["footer_{$column}_{$i}_label"], 'url' => $design["footer_{$column}_{$i}_link"] ?: '/search'];
                }
            }
            $footer[] = ['title' => $design["footer_{$column}_title"] ?: ['Shop', 'Your account', 'Help'][$column - 1], 'links' => $items];
        }
        if (! array_filter($footer, fn (array $menu) => $menu['links'] !== [])) {
            $footer = [['title' => 'Shop', 'links' => array_slice($links, 0, 6)], ['title' => 'Your account', 'links' => [['label' => 'Your account', 'url' => '/account'], ['label' => 'Your orders', 'url' => '/account/orders'], ['label' => 'Cart', 'url' => '/cart']]], ['title' => 'Explore', 'links' => [['label' => 'All products', 'url' => '/search']]]];
        }

        return [...$this->resolve(['header' => ['title' => 'Main navigation', 'links' => $links], 'footer' => $footer]), 'custom' => false];
    }

    /** @return array<string, mixed> */
    public function forEditing(ResolvedTheme $theme): array
    {
        $saved = $this->settings->get(self::KEY);

        $menus = is_array($saved) && isset($saved['footer']) ? $saved : $this->forTheme($theme);
        $menus['sidebar'] ??= ['title' => 'Browse departments', 'layout' => 'vertical', 'enabled' => false, 'links' => []];
        $menus['mobile'] ??= ['title' => 'Mobile navigation', 'layout' => 'vertical', 'enabled' => true, 'inherit_header' => true, 'links' => []];
        if (! is_array($saved)) {
            $menus['mobile'] = ['title' => 'Mobile navigation', 'layout' => 'vertical', 'enabled' => true, 'inherit_header' => true, 'links' => []];
        }

        return $menus;
    }

    /** @param array<string, mixed> $menus */
    public function save(array $menus): void
    {
        $this->settings->set(self::KEY, $menus);
    }

    /**
     * @param  array<string, mixed>  $menus
     * @return array<string, mixed>
     */
    private function resolve(array $menus): array
    {
        $menus = $this->attachFaq($menus);
        $ids = ['page' => [], 'category' => []];
        $collect = function (mixed $links, int $depth = 1) use (&$collect, &$ids): void {
            if (! is_array($links) || $depth > NavigationSchema::MAX_DEPTH) {
                return;
            }
            foreach ($links as $link) {
                if (! is_array($link)) {
                    continue;
                }
                $type = $link['type'] ?? 'link';
                if (isset($ids[$type])) {
                    $ids[$type][] = (int) ($link[$type.'_id'] ?? 0);
                }
                $collect($link['children'] ?? [], $depth + 1);
            }
        };
        foreach ([$menus['header'] ?? [], $menus['sidebar'] ?? [], $menus['mobile'] ?? [], ...($menus['footer'] ?? [])] as $menu) {
            $collect($menu['links'] ?? []);
        }
        $pages = Page::query()->where('status', 'published')->whereIn('id', $ids['page'])->get()->keyBy('id');
        $categories = Category::query()->active()->withCurrentTranslation()->whereIn('id', $ids['category'])->get()->keyBy('id');
        $links = function (mixed $items, int $depth = 1) use (&$links, $pages, $categories): array {
            if (! is_array($items) || $depth > NavigationSchema::MAX_DEPTH) {
                return [];
            }
            $result = [];
            foreach ($items as $link) {
                if (! is_array($link)) {
                    continue;
                }
                $type = $link['type'] ?? 'link';
                $url = $link['url'] ?? '';
                $target = null;
                if ($type !== 'link') {
                    $target = $type === 'page' ? $pages->get($link['page_id'] ?? 0) : $categories->get($link['category_id'] ?? 0);
                    if (! $target) {
                        continue;
                    }
                    $url = ($type === 'page' ? '/pages/' : '/c/').$target->slug;
                }
                if (! is_string($link['label'] ?? null) || ! is_string($url) || $url === '' || ! ThemeSchema::isSafeLink($url)) {
                    continue;
                }
                if (app(StorefrontLinks::class)->resolve($url) === null) {
                    continue;
                }
                $label = $link['label'];
                if ($type === 'category' && $target instanceof Category && $label === $target->name) {
                    $localized = $target->localized('name');
                    $label = is_string($localized) && $localized !== '' && $localized !== $target->name
                        ? $localized
                        : app(ContentTranslations::class)->translate($label);
                } else {
                    $label = app(ContentTranslations::class)->translate($label);
                }
                $item = ['label' => $label, 'url' => $url];
                $children = $links($link['children'] ?? [], $depth + 1);
                if ($children !== []) {
                    $item['children'] = $children;
                }
                $result[] = $item;
            }

            return $result;
        };
        $menu = fn (array $value, string $layout = 'vertical') => ['title' => app(ContentTranslations::class)->translate((string) ($value['title'] ?? '')), 'layout' => ($value['layout'] ?? $layout) === 'horizontal' ? 'horizontal' : 'vertical', 'enabled' => (bool) ($value['enabled'] ?? true), 'links' => ! ($value['enabled'] ?? true) ? [] : $links($value['links'] ?? [])];
        $header = $menu($menus['header'] ?? [], 'horizontal');
        $mobile = $menus['mobile'] ?? ['title' => $header['title'], 'inherit_header' => true];
        if ($mobile['inherit_header'] ?? false) {
            $mobile['links'] = $menus['header']['links'] ?? [];
        }

        return ['header' => $header, 'sidebar' => $menu($menus['sidebar'] ?? ['title' => 'Browse departments', 'enabled' => false]), 'mobile' => $menu($mobile), 'footer' => array_values(array_filter(array_map(fn ($value) => $menu($value), $menus['footer'] ?? []), fn ($value) => $value['enabled']))];
    }

    /**
     * A published FAQ page is listed in the footer until the merchant adds that page themselves.
     *
     * @param  array<string, mixed>  $menus
     * @return array<string, mixed>
     */
    private function attachFaq(array $menus): array
    {
        $faq = Page::query()->where('kind', Page::KIND_FAQ)->where('status', 'published')->orderBy('title')->first();
        if ($faq === null) {
            return $menus;
        }

        $linked = false;
        $walk = function (mixed $links) use (&$walk, &$linked, $faq): void {
            if (! is_array($links) || $linked) {
                return;
            }
            foreach ($links as $link) {
                if (! is_array($link)) {
                    continue;
                }
                $url = is_string($link['url'] ?? null) ? $link['url'] : '';
                if ((($link['type'] ?? '') === 'page' && (int) ($link['page_id'] ?? 0) === $faq->id) || $url === '/pages/'.$faq->slug) {
                    $linked = true;

                    return;
                }
                $walk($link['children'] ?? []);
            }
        };
        foreach (['header', 'sidebar', 'mobile'] as $place) {
            $walk(is_array($menus[$place] ?? null) ? ($menus[$place]['links'] ?? []) : []);
        }
        foreach ($menus['footer'] ?? [] as $column) {
            $walk(is_array($column) ? ($column['links'] ?? []) : []);
        }
        if ($linked) {
            return $menus;
        }

        $link = ['label' => $faq->title, 'type' => 'page', 'page_id' => $faq->id, 'url' => ''];
        $footer = array_values(array_filter($menus['footer'] ?? [], 'is_array'));
        $target = null;
        foreach ($footer as $index => $column) {
            if (($column['enabled'] ?? true) !== false) {
                $target = $index;
            }
        }
        if ($target === null) {
            $footer[] = ['title' => 'Help', 'enabled' => true, 'links' => [$link]];
        } else {
            $links = is_array($footer[$target]['links'] ?? null) ? $footer[$target]['links'] : [];
            $links[] = $link;
            $footer[$target]['links'] = $links;
        }
        $menus['footer'] = $footer;

        return $menus;
    }
}
