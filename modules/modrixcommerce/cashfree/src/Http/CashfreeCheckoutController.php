<?php

namespace EcoModular\Cashfree\Http;

use App\Domains\Payments\Models\Payment;
use App\Domains\Payments\PaymentReconciler;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\CheckoutController;
use EcoModular\Cashfree\CashfreeGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class CashfreeCheckoutController extends Controller
{
    public function handoff(Request $request, Payment $payment)
    {
        abort_unless($payment->provider === CashfreeGateway::CODE, 404);

        $order = $payment->order;
        $owns = in_array($order->id, (array) $request->session()->get(CheckoutController::SESSION_ORDERS, []), true)
            || ($request->user() !== null && $order->user_id === $request->user()->id);

        abort_unless($owns, 404);

        $handoff = Cache::get("cashfree:handoff:{$payment->id}");

        if (! $payment->isOpen() || $payment->expires_at?->isPast() || ! is_array($handoff)) {
            return redirect()->route('checkout.complete', $order);
        }

        return Inertia::render('module/modrixcommerce/cashfree/storefront/handoff', $handoff)
            ->toResponse($request)
            ->header('Cache-Control', 'private, no-store')
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function response(
        Request $request,
        CashfreeGateway $gateway,
        PaymentReconciler $reconciler,
    ) {
        $orderId = (string) $request->query('order_id');

        abort_if($orderId === '', 400, 'Missing order_id.');

        $payment = Payment::query()
            ->where('provider', CashfreeGateway::CODE)
            ->where('provider_reference', $orderId)
            ->first();

        abort_if($payment === null, 404);

        // Server-to-server inquiry validates the final payment status
        try {
            $update = $gateway->retrieve($payment);
            $reconciler->applyPayment($gateway, $update);
        } catch (\Throwable) {
            // Webhook or background reconciliation will catch up
        }

        return redirect()->route('checkout.payment.return', $payment->order, 303);
    }
}
