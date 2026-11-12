<?php

namespace App\Domains\Orders;

use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\Refund;
use App\Domains\Payments\Gateway\GatewayException;
use App\Domains\Payments\Gateway\RefundUpdate;
use App\Domains\Payments\Models\Payment;
use App\Domains\Payments\PaymentMethods;
use App\Domains\Pricing\Money;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Staff-initiated refunds. Manual payments are only recorded; gateway
 * payments are refunded at the provider first and recorded once the
 * provider has accepted the refund, so a failed provider call changes nothing.
 */
final class OrderRefunds
{
    public function __construct(
        private readonly PaymentMethods $methods,
        private readonly OrderWorkflow $workflow,
    ) {}

    /**
     * @param  array<int, int>  $restock  order item id => quantity to put back into stock
     */
    public function refund(Order $order, User $actor, Money $amount, string $reason, array $restock = []): Refund
    {
        $gateway = $this->methods->gateway($order->payment_method_code);

        if ($gateway === null) {
            return $this->workflow->refund($order, $actor, $amount, $reason, $restock);
        }

        if (! $amount->isPositive() || $amount->currency() !== $order->currency) {
            throw ValidationException::withMessages(['amount' => 'Enter a positive amount in the order currency.']);
        }

        if ($amount->greaterThan($order->refundable())) {
            throw ValidationException::withMessages(['amount' => "At most {$order->refundable()->format()} can be refunded."]);
        }

        /** @var Payment|null $payment */
        $payment = $order->payments()
            ->where('provider', $gateway->provider())
            ->where('status', Payment::STATUS_PAID)
            ->latest('id')
            ->first();

        // Money taken manually (cash, bank, card terminal) is returned manually.
        $onlineLeft = $payment === null ? Money::of('0', $order->currency) : $payment->money()->minus(Money::of(
            (string) $order->refunds()->where('payment_id', $payment->id)->whereNotNull('provider_reference')->where('status', '!=', Refund::STATUS_FAILED)->sum('amount'),
            $order->currency,
        ))->nonNegative();

        if ($onlineLeft->isZero()) {
            return $this->workflow->refund($order, $actor, $amount, $reason, $restock);
        }

        if ($amount->greaterThan($onlineLeft)) {
            throw ValidationException::withMessages(['amount' => "At most {$onlineLeft->format()} can be refunded to the online payment. Refund that first, then record the rest as a separate refund."]);
        }

        // Same order state and amount → same key, so a double submission cannot refund twice.
        $key = "ecomodular-refund-{$order->id}-{$order->money('refunded_total')->toDecimal()}-{$amount->toDecimal()}";

        try {
            $update = $gateway->refund($payment, $amount, $key);
        } catch (GatewayException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        if ($update->status === RefundUpdate::FAILED) {
            throw ValidationException::withMessages(['amount' => 'The payment provider declined the refund'.($update->failureReason !== null ? " ({$update->failureReason})." : '.')]);
        }

        return $this->workflow->refund($order, $actor, $amount, $reason, $restock, $payment, $update);
    }
}
