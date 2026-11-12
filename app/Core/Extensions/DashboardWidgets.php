<?php

namespace App\Core\Extensions;

use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Admin dashboard widgets contributed by modules (stable public API).
 *
 * Each widget names a React component in the module's `resources/js/slots`
 * directory, the permission needed to see it, and a closure that builds its
 * props. Props are only computed for users who hold the permission.
 */
final class DashboardWidgets
{
    /** @var array<string, array{key: string, module: string, title: string, component: string, permission: string, props: Closure, order: int}> */
    private array $widgets = [];

    /**
     * @param  Closure(User): array<string, mixed>  $props
     */
    public function register(string $key, string $module, string $title, string $component, string $permission, Closure $props, int $order = 100): void
    {
        $key = "{$module}:{$key}";

        if (isset($this->widgets[$key])) {
            throw new InvalidArgumentException("Dashboard widget [{$key}] is already registered.");
        }

        if (! preg_match('/^[A-Z][A-Za-z0-9]{0,60}$/', $component)) {
            throw new InvalidArgumentException("Invalid widget component name [{$component}].");
        }

        $this->widgets[$key] = compact('key', 'module', 'title', 'component', 'permission', 'props', 'order');
    }

    /**
     * @return list<array{key: string, title: string, component: string, props: array<string, mixed>}>
     */
    public function forUser(User $user): array
    {
        $widgets = array_filter($this->widgets, fn (array $w) => $user->can($w['permission']));
        usort($widgets, fn (array $a, array $b) => [$a['order'], $a['title']] <=> [$b['order'], $b['title']]);
        $result = [];

        foreach ($widgets as $widget) {
            try {
                $props = ($widget['props'])($user);
            } catch (Throwable $e) {
                Log::error("Dashboard widget {$widget['key']} failed.", ['exception' => $e]);

                continue;
            }

            $result[] = [
                'key' => $widget['key'],
                'title' => $widget['title'],
                'component' => "{$widget['module']}/{$widget['component']}",
                'props' => $props,
            ];
        }

        return $result;
    }
}
