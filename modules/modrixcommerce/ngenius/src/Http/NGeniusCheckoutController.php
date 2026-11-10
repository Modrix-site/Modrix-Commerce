<?php

namespace EcoModular\NGenius\Http;

use App\Domains\Payments\Models\Payment;
use App\Domains\Payments\PaymentReconciler;
use App\Http\Controllers\Controller;
use EcoModular\NGenius\NGeniusGateway;
use Illuminate\Http\Request;

class NGeniusCheckoutController extends Controller
{
    public function response(
        Request $request,
        NGeniusGateway $gateway,
        PaymentReconciler $reconciler,
    ) {
        $paymentId = $request->query('payment_id');
        $ref = $request->query('ref') ?? $request->query('order');

        $payment = null;

        if ($paymentId !== null) {
            $payment = Payment::query()->find($paymentId);
        }

        if ($payment === null && $ref !== null) {
            $payment = Payment::query()
                ->where('provider', NGeniusGateway::CODE)
                ->where('provider_reference', (string) $ref)
                ->first();
        }

        abort_if($payment === null, 404, 'Payment not found for N-Genius return.');

        // Authoritative server-to-server check with N-Genius Order API
        try {
            $update = $gateway->retrieve($payment);
            $reconciler->applyPayment($gateway, $update);
        } catch (\Throwable) {
            // Webhook or background reconciliation will reconcile
        }

        return redirect()->route('checkout.payment.return', $payment->order, 303);
    }
}
