<?php

namespace App\Domains\Customers\Listeners;

use App\Domains\Customers\CustomerProfiles;
use App\Domains\Orders\Models\Order;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Str;

/**
 * Attaches earlier guest orders to an account once the account holder has
 * proven they own the email address. Never done on registration alone:
 * otherwise anyone could claim someone else's order history.
 */
final class LinkGuestOrdersOnVerification
{
    public function __construct(private readonly CustomerProfiles $profiles) {}

    public function handle(Verified $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $customer = $this->profiles->forUser($event->user);

        Order::query()
            ->whereNull('user_id')
            ->where('email', Str::lower($event->user->email))
            ->update(['user_id' => $event->user->id, 'customer_id' => $customer->id]);
    }
}
