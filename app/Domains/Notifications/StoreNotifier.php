<?php

namespace App\Domains\Notifications;

use App\Models\User;
use App\Notifications\StoreNotification;
use Illuminate\Support\Facades\DB;

/**
 * Sends store notifications to the right people: staff with the kind's
 * permission (owners always) who keep it on, never the staff member who
 * caused the event; or a customer about their own order. The same event
 * (dedupe key) never notifies a person twice.
 */
final class StoreNotifier
{
    public function __construct(private readonly NotificationKinds $kinds) {}

    /**
     * @param  array<string, scalar|null>  $params
     * @return int how many staff members were notified
     */
    public function toStaff(string $kind, array $params, ?string $url, string $dedupe): int
    {
        $definition = $this->kinds->get($kind);

        if ($definition === null || $definition['audience'] !== NotificationKinds::STAFF) {
            return 0;
        }

        $actorId = auth()->id();
        $sent = 0;

        foreach (User::query()->whereHas('roles')->with('roles.permissions')->get() as $user) {
            if ($user->id === $actorId || $user->disabled_at !== null) {
                continue;
            }

            if (! $user->isOwner() && ! $user->can((string) $definition['permission'])) {
                continue;
            }

            $sent += $this->deliver($user, $kind, $params, $url, $dedupe) ? 1 : 0;
        }

        return $sent;
    }

    /**
     * @param  array<string, scalar|null>  $params
     */
    public function toCustomer(User $customer, string $kind, array $params, ?string $url, string $dedupe): bool
    {
        $definition = $this->kinds->get($kind);

        if ($definition === null || $definition['audience'] !== NotificationKinds::CUSTOMER) {
            return false;
        }

        return $this->deliver($customer, $kind, $params, $url, $dedupe, channels: ['database']);
    }

    /**
     * @param  array<string, scalar|null>  $params
     * @param  list<string>|null  $channels  null: from the user's preferences
     */
    private function deliver(User $user, string $kind, array $params, ?string $url, string $dedupe, ?array $channels = null): bool
    {
        $channels ??= array_values(array_filter([
            $user->wantsNotification($kind, 'bell') ? 'database' : null,
            $user->wantsNotification($kind, 'email') ? 'mail' : null,
        ]));

        if ($channels === [] || $this->alreadySent($user, $dedupe)) {
            return false;
        }

        $user->notify(new StoreNotification($kind, $params, self::safeUrl($url), $dedupe, $channels));

        return true;
    }

    private function alreadySent(User $user, string $dedupe): bool
    {
        return DB::table('notifications')
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->getKey())
            ->where('data->dedupe', $dedupe)
            ->exists();
    }

    /** Only links inside the store (a path, not "//host" or another site). */
    public static function safeUrl(?string $url): ?string
    {
        return $url !== null && preg_match('#^/(?![/\\\\])#', $url) === 1 ? $url : null;
    }
}
