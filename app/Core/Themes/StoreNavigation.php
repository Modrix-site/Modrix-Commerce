<?php

namespace App\Core\Themes;

use App\Core\Settings\SettingsRepository;
use App\Domains\Catalog\Models\Category;
use App\Domains\Content\Models\Page;

final class StoreNavigation
{
    public const KEY = 'store.navigation';

    public function __construct(private readonly SettingsRepository $settings) {}

    /** @return array{header: array{title: string, links: list<array{label: string, url: string}>}, footer: list<array{title: string, links: list<array{label: string, url: string}>}>} */
    public function forTheme(ResolvedTheme $theme): array
    {
        $saved = $this->settings->get(self::KEY);
        if (is_array($saved) && is_array($saved['footer'] ?? null)) {
            return ['header' => $this->menu($saved['header'] ?? null), 'footer' => array_values(array_map(fn ($menu) => $this->menu($menu), $saved['footer']))];
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

        return ['header' => ['title' => 'Main navigation', 'links' => $links], 'footer' => $footer];
    }

    /** @return array<string, mixed> */
    public function forEditing(ResolvedTheme $theme): array
    {
        $saved = $this->settings->get(self::KEY);

        return is_array($saved) && isset($saved['footer']) ? $saved : $this->forTheme($theme);
    }

    /** @param array<string, mixed> $menus */
    public function save(array $menus): void
    {
        $this->settings->set(self::KEY, $menus);
    }

    /** @return array{title: string, links: list<array{label: string, url: string}>} */
    private function menu(mixed $menu): array
    {
        $links = [];
        if (is_array($menu) && is_array($menu['links'] ?? null)) {
            foreach ($menu['links'] as $link) {
                if (is_array($link) && ($link['type'] ?? 'link') !== 'link') {
                    $target = ($link['type'] ?? '') === 'page'
                        ? Page::query()->where('status', 'published')->where('id', $link['page_id'] ?? 0)->first()
                        : Category::query()->active()->where('id', $link['category_id'] ?? 0)->first();
                    if (! $target) {
                        continue;
                    }
                    $link['url'] = (($link['type'] ?? '') === 'page' ? '/pages/' : '/c/').$target->slug;
                }

                if (is_array($link) && is_string($link['label'] ?? null) && is_string($link['url'] ?? null) && $link['url'] !== '' && ThemeSchema::isSafeLink($link['url'])) {
                    $links[] = ['label' => $link['label'], 'url' => $link['url']];
                }
            }
        }

        return ['title' => is_array($menu) && is_string($menu['title'] ?? null) ? $menu['title'] : '', 'links' => $links];
    }
}
