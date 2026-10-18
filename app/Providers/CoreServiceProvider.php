<?php

namespace App\Providers;

use App\Core\Admin\AdminNavigation;
use App\Core\Audit\AuditLogger;
use App\Core\Authorization\CorePermissions;
use App\Core\Authorization\PermissionRegistry;
use App\Core\Settings\SettingsRepository;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the platform core: permission registry, authorization gate,
 * settings, admin navigation and security audit listeners.
 */
class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PermissionRegistry::class, function () {
            $registry = new PermissionRegistry;
            CorePermissions::registerInto($registry);

            return $registry;
        });

        $this->app->scoped(SettingsRepository::class);

        $this->app->singleton(AdminNavigation::class, function () {
            $navigation = new AdminNavigation;
            $this->registerCoreNavigation($navigation);

            return $navigation;
        });
    }

    public function boot(): void
    {
        $this->configureGate();
        $this->registerSecurityAuditListeners();
    }

    private function configureGate(): void
    {
        // Registered permissions are resolved through roles. Owners hold every
        // registered permission. Disabled accounts hold none. Any ability that
        // is not a registered permission falls through to policies/gates.
        Gate::before(function (User $user, string $ability) {
            $registry = $this->app->make(PermissionRegistry::class);

            if (! $registry->has($ability)) {
                return null;
            }

            return $user->hasPermission($ability);
        });
    }

    private function registerCoreNavigation(AdminNavigation $navigation): void
    {
        $navigation->add('dashboard', 'Dashboard', 'admin.dashboard', 'layout-grid', 'Overview', null, 0);
        $navigation->add('users', 'Users', 'admin.users.index', 'users', 'Administration', 'users.view', 900);
        $navigation->add('roles', 'Roles & permissions', 'admin.roles.index', 'shield-check', 'Administration', 'roles.manage', 910);
        $navigation->add('general-settings', 'General settings', 'admin.settings.general.edit', 'sliders-horizontal', 'Administration', 'settings.manage', 919);
        $navigation->add('settings', 'Store settings', 'admin.settings.store.edit', 'settings', 'Administration', 'settings.manage', 920);
        $navigation->add('languages', 'Languages', 'admin.settings.languages.edit', 'languages', 'Administration', 'settings.manage', 921);
        $navigation->add('documents', 'Orders & documents', 'admin.settings.documents.edit', 'receipt', 'Administration', 'settings.manage', 922);
        $navigation->add('appearance', 'Appearance', 'admin.themes.index', 'palette', 'Configuration', 'themes.manage', 790);
        $navigation->add('extensions', 'Extensions', 'admin.extensions.index', 'puzzle', 'Administration', 'extensions.view', 925);
        $navigation->add('marketplace-client', 'Marketplace', 'admin.marketplace-client.index', 'store', 'Administration', 'extensions.view', 926);

        // Server-side marketplace administration exists only on the platform owner's marketplace instance.
        if ((bool) config('ecomodular.marketplace.server.enabled')) {
            $navigation->add('marketplace-products', 'Products', 'admin.marketplace.products.index', 'package', 'Marketplace', 'marketplace.manage', 950);
            $navigation->add('marketplace-reviews', 'Reviews', 'admin.marketplace.reviews.index', 'star', 'Marketplace', 'marketplace.manage', 953);
            $navigation->add('marketplace-sellers', 'Sellers', 'admin.marketplace.sellers.index', 'users', 'Marketplace', 'marketplace.manage', 950);
            $navigation->add('marketplace-orders', 'Orders', 'admin.marketplace.orders.index', 'receipt', 'Marketplace', 'marketplace.manage', 951);
            $navigation->add('marketplace-licenses', 'Licenses', 'admin.marketplace.licenses.index', 'key-round', 'Marketplace', 'licenses.manage', 952);
        }

        $navigation->add('audit', 'Audit log', 'admin.audit-logs.index', 'scroll-text', 'Administration', 'audit.view', 930);
        $navigation->add('system', 'System status', 'admin.system.status', 'activity', 'Administration', 'system.view', 940);
    }

    private function registerSecurityAuditListeners(): void
    {
        $audit = fn () => $this->app->make(AuditLogger::class);

        Event::listen(Login::class, function (Login $event) use ($audit) {
            if ($event->user instanceof User) {
                $audit()->record('auth.login', $event->user, ['remember' => $event->remember], $event->user);
            }
        });

        Event::listen(Logout::class, function (Logout $event) use ($audit) {
            if ($event->user instanceof User) {
                $audit()->record('auth.logout', $event->user, [], $event->user);
            }
        });

        Event::listen(Failed::class, function (Failed $event) use ($audit) {
            $audit()->record('auth.failed', null, [
                'email' => is_string($event->credentials['email'] ?? null) ? $event->credentials['email'] : null,
            ]);
        });

        Event::listen(Lockout::class, function (Lockout $event) use ($audit) {
            $audit()->record('auth.lockout', null, ['email' => $event->request->input('email')]);
        });

        Event::listen(PasswordReset::class, function (PasswordReset $event) use ($audit) {
            if ($event->user instanceof User) {
                $audit()->record('auth.password_reset', $event->user, [], $event->user);
            }
        });
    }
}
