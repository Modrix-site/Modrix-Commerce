<?php

namespace EcoModular\PayU\Http;

use App\Domains\Payments\Models\Payment;
use App\Domains\Payments\PaymentReconciler;
use App\Http\Controllers\Controller;
use EcoModular\PayU\PayuGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class PayuCheckoutController extends Controller
{
    public function handoff(Payment $payment): Response
    {
        $data = Cache::get("payu:handoff:{$payment->id}");

        abort_if(! is_array($data), 404, 'PayU checkout handoff session has expired.');

        return Inertia::render('module/modrixcommerce/payu/storefront/handoff', [
            'action' => (string) ($data['action'] ?? ''),
            'fields' => (array) ($data['fields'] ?? []),
            'orderNumber' => $data['orderNumber'] ?? null,
        ]);
    }

    public function response(
        Request $request,
        PayuGateway $gateway,
        PaymentReconciler $reconciler,
    ) {
        $txnid = (string) ($request->input('txnid') ?? $request->query('txnid') ?? '');

        abort_if($txnid === '', 400, 'Missing PayU transaction reference in response.');

        $payment = Payment::query()
            ->where('provider', PayuGateway::CODE)
            ->where('provider_reference', $txnid)
            ->first();

        abort_if($payment === null, 404, 'Payment not found for PayU transaction reference.');

        // Authoritative server-to-server check via verify_payment API
        try {
            $update = $gateway->retrieve($payment);
            $reconciler->applyPayment($gateway, $update);
        } catch (\Throwable) {
            // Webhook or background reconciliation will reconcile
        }

        return redirect()->route('checkout.payment.return', $payment->order, 303);
    }
}
