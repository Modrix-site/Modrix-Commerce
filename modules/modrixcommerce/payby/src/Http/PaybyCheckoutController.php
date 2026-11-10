<?php

namespace EcoModular\PayBy\Http;

use App\Domains\Payments\Models\Payment;
use App\Domains\Payments\PaymentReconciler;
use App\Http\Controllers\Controller;
use EcoModular\PayBy\PaybyGateway;
use Illuminate\Http\Request;

class PaybyCheckoutController extends Controller
{
    public function response(
        Request $request,
        PaybyGateway $gateway,
        PaymentReconciler $reconciler,
    ) {
        $paymentId = $request->query('payment_id');
        $merchantOrderNo = $request->query('merchantOrderNo');

        $payment = null;

        if ($paymentId !== null) {
            $payment = Payment::query()->find($paymentId);
        }

        if ($payment === null && $merchantOrderNo !== null) {
            $payment = Payment::query()
                ->where('provider', PaybyGateway::CODE)
                ->where('provider_reference', (string) $merchantOrderNo)
                ->first();
        }

        abort_if($payment === null, 404, 'Payment not found for PayBy return.');

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
