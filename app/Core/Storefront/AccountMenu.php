<?php

namespace App\Core\Storefront;

use InvalidArgumentException;

/**
 * Extra items modules can add to the signed-in customer's account menu.
 * Core items stay in the storefront navigation component.
 */
final class AccountMenu
{
    /** @var array<string, array{key: string, title: string, route: string, after: string, order: int}> */
    private array $items = [];

    public function add(string $key, string $title, string $route, string $after = 'orders', int $order = 100): void
    {
        if (isset($this->items[$key])) {
            throw new InvalidArgumentException("Account menu item [{$key}] is already registered.");
        }

        $this->items[$key] = compact('key', 'title', 'route', 'after', 'order');
    }

    /**
     * @return list<array{key: string, title: string, href: string, after: string}>
     */
    public function links(): array
    {
        return collect($this->items)
            ->filter(fn (array $item) => app('router')->has($item['route']))
            ->sortBy([['order', 'asc'], ['title', 'asc']])
            ->map(fn (array $item) => [
                'key' => $item['key'],
                'title' => $item['title'],
                'href' => route($item['route'], absolute: false),
                'after' => $item['after'],
            ])
            ->values()
            ->all();
    }
}
