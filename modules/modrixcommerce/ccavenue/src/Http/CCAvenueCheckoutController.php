<?php

namespace ModrixCommerce\CCAvenue\Http;

use App\Domains\Payments\Gateway\InvalidWebhook;
use App\Domains\Payments\Models\Payment;
use App\Domains\Payments\WebhookInbox;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\CheckoutController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use ModrixCommerce\CCAvenue\CCAvenueGateway;

class CCAvenueCheckoutController extends Controller
{
    public function handoff(Request $request, Payment $payment)
    {
        abort_unless($payment->provider === CCAvenueGateway::CODE, 404);
        $order = $payment->order;
        $owns = in_array($order->id, (array) $request->session()->get(CheckoutController::SESSION_ORDERS, []), true)
            || ($request->user() !== null && $order->user_id === $request->user()->id);
        abort_unless($owns, 404);
        $handoff = Cache::get('ccavenue:handoff:'.$payment->id);
        if (! $payment->isOpen() || $payment->expires_at?->isPast() || ! is_array($handoff)) {
            return redirect()->route('checkout.complete', $order);
        }

        return Inertia::render('module/modrixcommerce/ccavenue/storefront/handoff', $handoff + ['orderNumber' => $order->number])
            ->toResponse($request)->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function response(Request $request, CCAvenueGateway $gateway, WebhookInbox $inbox)
    {
        try {
            $event = $gateway->verifyWebhook($request->getContent(), []);
        } catch (InvalidWebhook) {
            abort(400, 'Invalid CCAvenue response.');
        }
        $inbox->receive($gateway, $request->getContent(), []);
        $payment = Payment::query()->where('provider', CCAvenueGateway::CODE)->where('provider_reference', $event['order_ref'])->first();
        abort_if($payment === null, 404);

        // A GET restores SameSite=Lax guest cookies after CCAvenue's cross-site POST.
        // The core return route enforces ownership and reconciles the payment.
        return redirect()->route('checkout.payment.return', $payment->order, 303);
    }
}
