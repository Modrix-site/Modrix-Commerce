<?php

namespace EcoModular\Easebuzz\Http;

use App\Domains\Payments\Models\Payment;
use App\Domains\Payments\PaymentReconciler;
use App\Http\Controllers\Controller;
use EcoModular\Easebuzz\EasebuzzGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class EasebuzzCheckoutController extends Controller
{
    public function handoff(Payment $payment): Response
    {
        $data = Cache::get("easebuzz:handoff:{$payment->id}");

        abort_if(! is_array($data), 404, 'Easebuzz checkout handoff session has expired.');

        return Inertia::render('module/modrixcommerce/easebuzz/storefront/handoff', [
            'checkoutUrl' => (string) ($data['checkoutUrl'] ?? ''),
            'txnid' => (string) ($data['txnid'] ?? ''),
            'orderNumber' => $data['orderNumber'] ?? null,
        ]);
    }

    public function response(
        Request $request,
        EasebuzzGateway $gateway,
        PaymentReconciler $reconciler,
    ) {
        $txnid = (string) ($request->input('txnid') ?? $request->query('txnid') ?? '');

        abort_if($txnid === '', 400, 'Missing Easebuzz transaction reference in response.');

        $payment = Payment::query()
            ->where('provider', EasebuzzGateway::CODE)
            ->where('provider_reference', $txnid)
            ->first();

        abort_if($payment === null, 404, 'Payment not found for Easebuzz transaction reference.');

        // Authoritative server-to-server check via retrieve API
        try {
            $update = $gateway->retrieve($payment);
            $reconciler->applyPayment($gateway, $update);
        } catch (\Throwable) {
            // Webhook or background scheduled reconciliation will reconcile
        }

        return redirect()->route('checkout.payment.return', $payment->order, 303);
    }
}
