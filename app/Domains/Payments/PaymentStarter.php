<?php

namespace App\Domains\Payments;

use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderEvent;
use App\Domains\Payments\Gateway\GatewayException;
use App\Domains\Payments\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sends a customer to the gateway's hosted payment page for an unpaid order.
 *
 * Reuses a still-valid session instead of creating a second one, and closes
 * older sessions so the same order cannot be paid twice by accident. A
 * duplicate payment that still happens is detected and flagged for refund by
 * the OrderWorkflow.
 */
final class PaymentStarter
{
    public function __construct(private readonly PaymentMethods $methods) {}

    /**
     * @throws GatewayException when the provider cannot create the session
     * @throws ValidationException when the order cannot be paid now
     */
    public function start(Order $order, string $returnUrl, string $cancelUrl): Payment
    {
        $gateway = $this->methods->gateway($order->payment_method_code)
            ?? throw ValidationException::withMessages(['payment' => 'This order is not paid online.']);

        /** @var array{0: Payment, 1: bool, 2: list<Payment>} $result */
        $result = DB::transaction(function () use ($order, $gateway) {
            /** @var Order $order */
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->payment_status !== PaymentStatus::Unpaid || $order->status === OrderStatus::Cancelled) {
                throw ValidationException::withMessages(['payment' => 'This order cannot be paid now.']);
            }

            $open = $order->payments()->whereIn('status', Payment::OPEN)->latest('id')->get();

            if ($open->contains(fn (Payment $p) => $p->status === Payment::STATUS_PROCESSING)) {
                throw ValidationException::withMessages(['payment' => 'Your payment is being processed. We will email you when it is confirmed.']);
            }

            $resumable = $open->first(fn (Payment $p) => $p->isResumable());

            if ($resumable !== null) {
                return [$resumable, true, []];
            }

            if ($open->contains(fn (Payment $p) => $p->checkout_url === null && $p->created_at?->isAfter(now()->subMinute()))) {
                throw ValidationException::withMessages(['payment' => 'Your payment page is being prepared. Please try again in a moment.']);
            }

            $stale = $open->all();
            foreach ($stale as $old) {
                $old->forceFill(['status' => Payment::STATUS_EXPIRED, 'failure_reason' => 'Replaced by a new payment attempt.'])->save();
            }

            $payment = new Payment;
            $payment->forceFill([
                'order_id' => $order->id,
                'method_code' => $order->payment_method_code,
                'provider' => $gateway->provider(),
                'amount' => $order->money('grand_total')->toDecimal(),
                'currency' => $order->currency,
                'status' => Payment::STATUS_PENDING,
            ])->save();

            return [$payment, false, array_values($stale)];
        });

        [$payment, $reused, $stale] = $result;

        foreach ($stale as $old) {
            $gateway->expireSession($old);
        }

        if ($reused) {
            return $payment;
        }

        try {
            $session = $gateway->createSession($order, $payment, $returnUrl, $cancelUrl);
        } catch (GatewayException $e) {
            $payment->forceFill(['status' => Payment::STATUS_FAILED, 'failure_reason' => $e->getMessage()])->save();
            $this->log($order, "Could not create the payment page: {$e->getMessage()}");

            throw $e;
        }

        $payment->forceFill([
            'provider_reference' => $session->reference,
            'checkout_url' => $session->redirectUrl,
            'expires_at' => $session->expiresAt,
        ])->save();
        $this->log($order, "Payment page created ({$gateway->label()}).");

        return $payment;
    }

    private function log(Order $order, string $message): void
    {
        OrderEvent::query()->forceCreate([
            'order_id' => $order->id,
            'type' => 'payment',
            'message' => $message,
            'is_customer_visible' => false,
        ]);
    }
}
