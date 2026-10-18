<?php

namespace App\Core\Admin;

use App\Models\User;
use InvalidArgumentException;

/**
 * Registry of admin sidebar entries contributed by core and modules.
 *
 * Items are resolved per request and filtered by the user's permissions.
 * Filtering is a UX concern only: every admin route enforces authorization
 * on the server independently.
 */
final class AdminNavigation
{
    /** @var array<string, array{key: string, title: string, route: string, icon: string, group: string, permission: string|null, order: int, paymentGateway: bool}> */
    private array $items = [];

    /**
     * @param  string  $icon  A lucide icon name understood by the admin UI (e.g. "layout-grid").
     */
    public function add(
        string $key,
        string $title,
        string $route,
        string $icon,
        string $group = 'Store',
        ?string $permission = null,
        int $order = 100,
        bool $paymentGateway = false,
    ): void {
        if (isset($this->items[$key])) {
            throw new InvalidArgumentException("Admin navigation item [{$key}] is already registered.");
        }

        $this->items[$key] = compact('key', 'title', 'route', 'icon', 'group', 'permission', 'order', 'paymentGateway');
    }

    /** @return list<array{key: string, title: string, href: string, icon: string}> */
    public function paymentGatewaysForUser(User $user): array
    {
        return array_merge([], ...array_column($this->forUser($user, true), 'items'));
    }

    /**
     * @return list<array{title: string, items: list<array{key: string, title: string, href: string, icon: string}>}>
     */
    public function forUser(User $user, bool $paymentGateways = false): array
    {
        $groups = [];

        $items = collect($this->items)
            ->filter(fn (array $item) => $item['paymentGateway'] === $paymentGateways)
            ->filter(fn (array $item) => $item['permission'] === null || $user->can($item['permission']))
            ->filter(fn (array $item) => app('router')->has($item['route']))
            ->sortBy([['order', 'asc'], ['title', 'asc']]);

        foreach ($items as $item) {
            $groups[$item['group']][] = [
                'key' => $item['key'],
                'title' => $item['title'],
                'href' => route($item['route'], absolute: false),
                'icon' => $item['icon'],
            ];
        }

        $result = [];

        foreach ($groups as $title => $groupItems) {
            $result[] = ['title' => $title, 'items' => $groupItems];
        }

        return $result;
    }
}
