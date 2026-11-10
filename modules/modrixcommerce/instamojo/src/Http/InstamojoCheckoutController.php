<?php

namespace EcoModular\Instamojo\Http;

use App\Domains\Payments\Models\Payment;
use App\Domains\Payments\PaymentReconciler;
use App\Http\Controllers\Controller;
use EcoModular\Instamojo\InstamojoGateway;
use Illuminate\Http\Request;

class InstamojoCheckoutController extends Controller
{
    public function response(
        Request $request,
        InstamojoGateway $gateway,
        PaymentReconciler $reconciler,
    ) {
        $paymentId = $request->query('payment_id');
        $paymentRequestId = $request->query('payment_request_id');

        $payment = null;

        if ($paymentId !== null) {
            $payment = Payment::query()->find($paymentId);
        }

        if ($payment === null && $paymentRequestId !== null) {
            $payment = Payment::query()
                ->where('provider', InstamojoGateway::CODE)
                ->where('provider_reference', (string) $paymentRequestId)
                ->first();
        }

        abort_if($payment === null, 404, 'Payment not found for Instamojo return.');

        // Server-to-server authoritative retrieval
        try {
            $update = $gateway->retrieve($payment);
            $reconciler->applyPayment($gateway, $update);
        } catch (\Throwable) {
            // Webhook or background scheduled reconciliation will reconcile
        }

        return redirect()->route('checkout.payment.return', $payment->order, 303);
    }
}
