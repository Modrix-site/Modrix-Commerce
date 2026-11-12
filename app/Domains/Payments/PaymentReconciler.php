<?php

namespace App\Domains\Payments;

use App\Domains\Orders\Models\Order;
use App\Domains\Orders\OrderWorkflow;
use App\Domains\Payments\Contracts\PaymentGateway;
use App\Domains\Payments\Gateway\GatewayException;
use App\Domains\Payments\Gateway\PaymentUpdate;
use App\Domains\Payments\Gateway\PaymentUpdateKind;
use App\Domains\Payments\Gateway\RefundUpdate;
use App\Domains\Payments\Models\Payment;

/**
 * The single path by which provider facts change payments and orders
 * (ADR-027). Webhooks, the return page and the reconcile command all end
 * here; every operation is idempotent, so duplicate, late and out-of-order
 * facts are harmless.
 */
final class PaymentReconciler
{
    public function __construct(
        private readonly PaymentMethods $methods,
        private readonly OrderWorkflow $workflow,
    ) {}

    /**
     * @return Order|null the affected order, or null when the session is not ours
     */
    public function applyPayment(PaymentGateway $gateway, PaymentUpdate $update): ?Order
    {
        $payment = Payment::query()
            ->where('provider', $gateway->provider())
            ->where('provider_reference', $update->sessionReference)
            ->first();

        // Sessions created by other software on the same provider account are not ours.
        if ($payment === null) {
            return null;
        }

        match ($update->kind) {
            PaymentUpdateKind::Pending => null,
            PaymentUpdateKind::Processing => $this->workflow->markGatewayPaymentProcessing($payment, $update),
            PaymentUpdateKind::Succeeded => $this->workflow->confirmGatewayPayment($payment, $update),
            PaymentUpdateKind::Failed => $this->workflow->failGatewayPayment($payment, $update->failureReason ?? 'The payment failed.'),
            PaymentUpdateKind::Expired => $this->workflow->expireGatewayPayment($payment),
        };

        return $payment->order;
    }

    public function applyRefund(PaymentGateway $gateway, RefundUpdate $update): ?Order
    {
        if ($update->paymentReference === null) {
            return null;
        }

        $payment = Payment::query()
            ->where('provider', $gateway->provider())
            ->where('provider_payment_id', $update->paymentReference)
            ->latest('id')
            ->first();

        if ($payment === null) {
            return null;
        }

        $this->workflow->applyProviderRefund($payment, $update);

        return $payment->order;
    }

    /**
     * Asks the provider for the state of an open payment and applies it.
     *
     * @throws GatewayException
     */
    public function reconcile(Payment $payment): void
    {
        if (! $payment->isOpen() || $payment->provider_reference === null) {
            return;
        }

        $gateway = $this->methods->gatewayForProvider($payment->provider);

        if ($gateway === null) {
            return;
        }

        $this->applyPayment($gateway, $gateway->retrieve($payment));
    }
}
