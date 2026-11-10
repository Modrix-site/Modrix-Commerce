<?php

namespace EcoModular\CartAbandonmentRecovery\Listeners;

use App\Domains\Orders\Events\CartCheckedOut;
use EcoModular\CartAbandonmentRecovery\Services\CartRecoveryService;
use Throwable;

class HandleCartCheckedOut
{
    public function __construct(private readonly CartRecoveryService $recoveryService) {}

    public function handle(CartCheckedOut $event): void
    {
        try {
            $this->recoveryService->recordOrderConversion($event->cartId, $event->orderId);
        } catch (Throwable $e) {
            // Never fail the checkout transaction if recovery attribution throws
            report($e);
        }
    }
}
