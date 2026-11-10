<?php

namespace EcoModular\AmazonPaymentServices\Http;

use App\Domains\Payments\Gateway\InvalidWebhook;
use App\Domains\Payments\Models\Payment;
use App\Domains\Payments\WebhookInbox;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\CheckoutController;
use EcoModular\AmazonPaymentServices\AmazonPaymentServicesGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class AmazonPaymentServicesCheckoutController extends Controller
{
    public function handoff(Request $request, Payment $payment)
    {
        abort_unless($payment->provider === AmazonPaymentServicesGateway::CODE, 404);

        $order = $payment->order;
        $owns = in_array($order->id, (array) $request->session()->get(CheckoutController::SESSION_ORDERS, []), true)
            || ($request->user() !== null && $order->user_id === $request->user()->id);

        abort_unless($owns, 404);

        $handoff = Cache::get("aps:handoff:{$payment->id}");

        if (! $payment->isOpen() || $payment->expires_at?->isPast() || ! is_array($handoff)) {
            return redirect()->route('checkout.complete', $order);
        }

        return Inertia::render('module/modrixcommerce/amazon-payment-services/storefront/handoff', [
            'action' => $handoff['action'],
            'fields' => $handoff['fields'],
            'orderNumber' => $order->number ?? (string) $order->id,
        ])->toResponse($request)
            ->header('Cache-Control', 'private, no-store')
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function response(
        Request $request,
        AmazonPaymentServicesGateway $gateway,
        WebhookInbox $inbox,
    ) {
        $payload = $request->getContent();
        if ($payload === '') {
            $payload = http_build_query($request->all());
        }

        try {
            $event = $gateway->verifyWebhook($payload, []);
        } catch (InvalidWebhook $e) {
            abort(400, 'Invalid Amazon Payment Services signature: ' . $e->getMessage());
        }

        $inbox->receive($gateway, $payload, []);

        $merchantRef = (string) ($event['merchant_reference'] ?? '');
        $payment = Payment::query()
            ->where('provider', AmazonPaymentServicesGateway::CODE)
            ->where('provider_reference', $merchantRef)
            ->first();

        abort_if($payment === null, 404);

        // Standard 303 redirect back to the checkout return endpoint.
        // Restores session cookies and executes server-to-server reconciliation.
        return redirect()->route('checkout.payment.return', $payment->order, 303);
    }
}
