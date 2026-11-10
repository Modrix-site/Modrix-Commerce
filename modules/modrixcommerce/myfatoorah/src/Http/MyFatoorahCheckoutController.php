<?php

namespace EcoModular\MyFatoorah\Http;

use App\Domains\Payments\Models\Payment;
use App\Domains\Payments\PaymentReconciler;
use App\Http\Controllers\Controller;
use EcoModular\MyFatoorah\MyFatoorahGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class MyFatoorahCheckoutController extends Controller
{
    public function handoff(Payment $payment): Response
    {
        $data = Cache::get("myfatoorah:handoff:{$payment->id}");

        abort_if(! is_array($data), 404, 'MyFatoorah checkout handoff session has expired.');

        return Inertia::render('module/modrixcommerce/myfatoorah/storefront/handoff', [
            'checkoutUrl' => (string) ($data['checkoutUrl'] ?? ''),
            'customerRef' => (string) ($data['customerRef'] ?? ''),
            'orderNumber' => $data['orderNumber'] ?? null,
        ]);
    }

    public function response(
        Request $request,
        MyFatoorahGateway $gateway,
        PaymentReconciler $reconciler,
    ) {
        $paymentId = $request->input('paymentId') ?? $request->query('paymentId');
        $invoiceId = $request->input('invoiceId') ?? $request->query('invoiceId');
        $customerRef = $request->input('customerReference') ?? $request->query('customerReference');

        $payment = null;

        if ($paymentId !== null) {
            $payment = Payment::query()
                ->where('provider', MyFatoorahGateway::CODE)
                ->where('provider_payment_id', (string) $paymentId)
                ->first();
        }

        if ($payment === null && $customerRef !== null) {
            $payment = Payment::query()
                ->where('provider', MyFatoorahGateway::CODE)
                ->where('provider_reference', (string) $customerRef)
                ->first();
        }

        if ($payment === null && $invoiceId !== null) {
            // Find by cached invoice mapping
            $payment = Payment::query()
                ->where('provider', MyFatoorahGateway::CODE)
                ->where('provider_reference', (string) $invoiceId)
                ->first();
        }

        abort_if($payment === null, 404, 'Payment not found for MyFatoorah response.');

        // Server-to-server authoritative status check
        try {
            $update = $gateway->retrieve($payment);
            $reconciler->applyPayment($gateway, $update);
        } catch (\Throwable) {
            // Webhook or background scheduled reconciliation will reconcile
        }

        return redirect()->route('checkout.payment.return', $payment->order, 303);
    }
}
