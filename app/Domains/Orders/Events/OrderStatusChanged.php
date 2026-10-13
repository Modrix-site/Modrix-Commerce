<?php

namespace App\Domains\Orders\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A status change the customer may be emailed about (see OrderEmailSettings).
 * `notify` is the staff member's choice for this change; null means "use the
 * store setting". Dispatched after the surrounding transaction commits.
 */
final class OrderStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly int $orderId,
        public readonly string $kind,
        public readonly ?bool $notify,
        public readonly string $key,
        public readonly ?string $note = null,
    ) {}
}
