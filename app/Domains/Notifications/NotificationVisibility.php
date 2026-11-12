<?php

namespace App\Domains\Notifications;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Which stored notifications a person may still see. A staff notification
 * stays hidden once the person no longer has its kind's permission (for
 * example after a role change), so order and customer details are not
 * shown to someone who lost access to them.
 */
final class NotificationVisibility
{
    public function __construct(private readonly NotificationKinds $kinds) {}

    /**
     * Staff kinds this person may not see (owners see every kind).
     *
     * @return list<string>
     */
    public function hiddenKinds(User $user): array
    {
        if ($user->isOwner()) {
            return [];
        }

        $hidden = [];

        foreach ($this->kinds->all() as $kind => $definition) {
            if ($definition['audience'] === NotificationKinds::STAFF && ! $user->can((string) $definition['permission'])) {
                $hidden[] = $kind;
            }
        }

        return $hidden;
    }

    /**
     * @return MorphMany<DatabaseNotification, User>
     */
    public function query(User $user): MorphMany
    {
        $hidden = $this->hiddenKinds($user);
        $query = $user->notifications();

        return $hidden === [] ? $query : $query->whereNotIn('data->kind', $hidden);
    }

    public function unreadCount(User $user): int
    {
        return $this->query($user)->whereNull('read_at')->count();
    }
}
