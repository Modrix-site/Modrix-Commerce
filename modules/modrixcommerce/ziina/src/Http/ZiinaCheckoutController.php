<?php

namespace EcoModular\Ziina\Http;

use App\Domains\Payments\Models\Payment;
use App\Domains\Payments\PaymentReconciler;
use App\Http\Controllers\Controller;
use EcoModular\Ziina\ZiinaGateway;
use Illuminate\Http\Request;

class ZiinaCheckoutController extends Controller
{
    public function response(
        Request $request,
        ZiinaGateway $gateway,
        PaymentReconciler $reconciler,
    ) {
        $paymentId = $request->query('payment_id');
        $intentId = $request->query('intent_id');

        $payment = null;

        if ($paymentId !== null) {
            $payment = Payment::query()->find($paymentId);
        }

        if ($payment === null && $intentId !== null) {
            $payment = Payment::query()
                ->where('provider', ZiinaGateway::CODE)
                ->where('provider_reference', (string) $intentId)
                ->first();
        }

        abort_if($payment === null, 404, 'Payment not found for Ziina checkout return.');

        // Server-to-server authoritative retrieval
        try {
            $update = $gateway->retrieve($payment);
            $reconciler->applyPayment($gateway, $update);
        } catch (\Throwable) {
            // Webhook will reconcile
        }

        return redirect()->route('checkout.payment.return', $payment->order, 303);
    }
}
