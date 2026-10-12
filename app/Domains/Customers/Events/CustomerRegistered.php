<?php

namespace App\Domains\Customers\Events;

use Illuminate\Foundation\Events\Dispatchable;

final class CustomerRegistered
{
    use Dispatchable;

    public function __construct(public readonly int $customerId, public readonly int $userId) {}
}
