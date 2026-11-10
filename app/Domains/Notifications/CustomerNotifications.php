<?php

namespace App\Domains\Notifications;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Notifications\DatabaseNotification;

/**
 * A person's customer notifications (order updates). Staff notifications of
 * someone who also shops stay in the admin.
 */
final class CustomerNotifications
{
    public function __construct(private readonly NotificationKinds $kinds) {}

    /**
     * @return MorphMany<DatabaseNotification, User>
     */
    public function for(User $user): MorphMany
    {
        // By audience, not name: "customer.new" is a staff kind.
        $customerKinds = array_keys(array_filter(
            $this->kinds->all(),
            fn (array $definition) => $definition['audience'] === NotificationKinds::CUSTOMER,
        ));

        return $user->notifications()->whereIn('data->kind', $customerKinds);
    }

    public function unreadCount(User $user): int
    {
        return $this->for($user)->whereNull('read_at')->count();
    }
}
