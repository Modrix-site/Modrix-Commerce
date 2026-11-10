<?php

namespace EcoModular\PhonePe\Http;

use App\Domains\Payments\Models\Payment;
use App\Domains\Payments\PaymentReconciler;
use App\Http\Controllers\Controller;
use EcoModular\PhonePe\PhonePeGateway;
use Illuminate\Http\Request;

class PhonePeCheckoutController extends Controller
{
    public function response(
        Request $request,
        PhonePeGateway $gateway,
        PaymentReconciler $reconciler,
    ) {
        $txnId = (string) ($request->query('txn') ?? $request->input('transactionId') ?? '');

        abort_if($txnId === '', 400, 'Missing PhonePe transaction reference.');

        $payment = Payment::query()
            ->where('provider', PhonePeGateway::CODE)
            ->where('provider_reference', $txnId)
            ->first();

        abort_if($payment === null, 404);

        // Authoritative server-to-server check with PhonePe Status API
        try {
            $update = $gateway->retrieve($payment);
            $reconciler->applyPayment($gateway, $update);
        } catch (\Throwable) {
            // Webhook or background scheduled reconciliation will reconcile
        }

        return redirect()->route('checkout.payment.return', $payment->order, 303);
    }
}
