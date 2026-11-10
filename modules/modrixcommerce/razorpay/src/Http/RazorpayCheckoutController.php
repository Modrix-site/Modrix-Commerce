<?php

namespace ModrixCommerce\Razorpay\Http;

use App\Domains\Payments\Gateway\GatewayException;
use App\Domains\Payments\Models\Payment;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\CheckoutController;
use Illuminate\Http\Request;
use Inertia\Inertia;
use ModrixCommerce\Razorpay\RazorpayGateway;
use ModrixCommerce\Razorpay\RazorpaySettings;

class RazorpayCheckoutController extends Controller
{
    public function show(Request $request, Payment $payment, RazorpayGateway $gateway, RazorpaySettings $settings)
    {
        abort_unless($payment->provider === RazorpayGateway::CODE, 404);
        $order = $payment->order;
        $owns = in_array($order->id, (array) $request->session()->get(CheckoutController::SESSION_ORDERS, []), true)
            || ($request->user() !== null && $request->user()->id === $order->user_id);
        abort_unless($owns, 404);
        if (! $payment->isOpen() || $payment->expires_at?->isPast()) {
            return redirect()->route('checkout.complete', $order);
        }
        try {
            $options = $gateway->checkoutOptions($payment);
        } catch (GatewayException) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Razorpay is temporarily unavailable. Please contact the store.']);

            return redirect()->route('checkout.complete', $order);
        }

        return Inertia::render('module/modrixcommerce/razorpay/storefront/checkout', [
            'options' => $options, 'orderNumber' => $order->number, 'amount' => $payment->money()->format(),
            'autoOpen' => (bool) $settings->value('auto_open'), 'orderUrl' => route('checkout.complete', $order),
        ])->toResponse($request)->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function complete(Request $request, Payment $payment, RazorpayGateway $gateway)
    {
        abort_unless($payment->provider === RazorpayGateway::CODE, 404);
        abort_if(strlen($request->getContent()) > 16384, 413);
        try {
            $valid = $gateway->verifyReturn($payment, $request->post());
        } catch (GatewayException) {
            $valid = false;
        }
        abort_unless($valid, 400, 'Invalid Razorpay payment signature.');

        // Only the subsequent owned core return page verifies and applies captured funds.
        return redirect()->route('checkout.payment.return', $payment->order, 303);
    }
}
