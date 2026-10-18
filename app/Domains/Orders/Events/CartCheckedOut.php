<?php

namespace App\Domains\Orders\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched synchronously inside the order transaction, just before the
 * cart is emptied, so modules can link what they put in the cart to the
 * order (e.g. offer attribution). Listeners run in the transaction: an
 * exception rolls the order back, so keep them small and safe.
 */
final class CartCheckedOut
{
    use Dispatchable;

    public function __construct(public readonly int $cartId, public readonly int $orderId) {}
}
