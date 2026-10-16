<?php

namespace App\Http\Middleware;

use App\Core\Admin\AdminNavigation;
use App\Core\Localization\InterfaceTranslations;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                // Explicit shape so loaded relations (roles, permissions) never leak into page props.
                'user' => $user instanceof User ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                    'two_factor_enabled' => $user->hasEnabledTwoFactorAuthentication(),
                    'created_at' => $user->created_at?->toIso8601String(),
                    'updated_at' => $user->updated_at?->toIso8601String(),
                ] : null,
                // UI hints only. Every route enforces authorization on the server.
                'permissions' => $user instanceof User ? $user->permissionNames() : [],
                'isStaff' => $user instanceof User && $user->isStaff(),
                'isOwner' => $user instanceof User && $user->isOwner(),
            ],
            'adminNavigation' => fn () => $user instanceof User && $user->isStaff()
                ? app(AdminNavigation::class)->forUser($user)
                : [],
            // Storefront wording in the shopper's language (customer-facing pages only).
            'i18n' => fn () => [
                'locale' => app()->getLocale(),
                'messages' => $request->is('admin', 'admin/*', 'install', 'install/*')
                    ? (object) []
                    : (object) app(InterfaceTranslations::class)->messages(app()->getLocale()),
            ],
            // Unread count for the notification bell (staff and customers).
            'notificationsUnread' => fn () => $user instanceof User ? $user->unreadNotifications()->count() : 0,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
