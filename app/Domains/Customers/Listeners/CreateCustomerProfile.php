<?php

namespace App\Domains\Customers\Listeners;

use App\Domains\Customers\CustomerProfiles;
use App\Domains\Customers\Events\CustomerRegistered;
use App\Models\User;
use Illuminate\Auth\Events\Registered;

/**
 * Creates the commerce profile for every self-registered account.
 */
final class CreateCustomerProfile
{
    public function __construct(private readonly CustomerProfiles $profiles) {}

    public function handle(Registered $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $customer = $this->profiles->forUser($event->user);
        CustomerRegistered::dispatch($customer->id, $event->user->id);
    }
}
