<?php

namespace EcoModular\Paytm\Http;

use App\Domains\Payments\Models\Payment;
use App\Domains\Payments\PaymentReconciler;
use App\Http\Controllers\Controller;
use EcoModular\Paytm\PaytmApi;
use EcoModular\Paytm\PaytmGateway;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaytmCheckoutController extends Controller
{
    public function handoff(Request $request, PaytmApi $api): Response
    {
        $token = (string) $request->query('token');
        $orderId = (string) $request->query('order_id');
        $mid = (string) $request->query('mid');

        abort_if($token === '' || $orderId === '' || $mid === '', 400, 'Invalid Paytm handoff parameters.');

        return Inertia::render('module/modrixcommerce/paytm/storefront/handoff', [
            'postUrl' => $api->showPaymentPageUrl($orderId),
            'mid' => $mid,
            'orderId' => $orderId,
            'txnToken' => $token,
        ]);
    }

    public function response(
        Request $request,
        PaytmGateway $gateway,
        PaymentReconciler $reconciler,
    ) {
        $orderId = (string) ($request->input('ORDERID') ?? $request->query('ORDERID') ?? $request->input('orderId') ?? '');

        abort_if($orderId === '', 400, 'Missing Paytm order reference in callback.');

        $payment = Payment::query()
            ->where('provider', PaytmGateway::CODE)
            ->where('provider_reference', $orderId)
            ->first();

        abort_if($payment === null, 404, 'Payment not found for Paytm order reference.');

        // Server-to-server authoritative status check
        try {
            $update = $gateway->retrieve($payment);
            $reconciler->applyPayment($gateway, $update);
        } catch (\Throwable) {
            // Webhook or background reconciliation will reconcile
        }

        return redirect()->route('checkout.payment.return', $payment->order, 303);
    }
}
