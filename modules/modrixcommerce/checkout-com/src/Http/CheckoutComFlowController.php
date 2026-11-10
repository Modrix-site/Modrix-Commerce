<?php

namespace EcoModular\CheckoutCom\Http;

use App\Domains\Payments\Models\Payment;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Models\User;
use EcoModular\CheckoutCom\CheckoutComFlowSession;
use EcoModular\CheckoutCom\CheckoutComGateway;
use EcoModular\CheckoutCom\CheckoutComSettings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Shows Checkout.com Flow to the customer who placed the order. The payment
 * session is created on the server and is not put in the page URL.
 */
class CheckoutComFlowController extends Controller
{
    public function __invoke(Request $request, Payment $payment, CheckoutComSettings $settings): Response|SymfonyResponse
    {
        abort_unless($payment->provider === CheckoutComGateway::CODE, 404);
        abort_unless(str_starts_with((string) $payment->provider_reference, 'ps_'), 404);

        $order = $payment->order;
        $user = $request->user();
        $ownsBySession = in_array($order->id, (array) $request->session()->get(CheckoutController::SESSION_ORDERS, []), true);
        $ownsByAccount = $user instanceof User && $order->user_id === $user->id;
        abort_unless($ownsBySession || $ownsByAccount, 404);

        if (! $payment->isOpen()) {
            return Inertia::location(route('checkout.complete', $order));
        }

        $session = CheckoutComFlowSession::get($payment);
        $publicKey = $settings->publicKey();

        if ($session === null || $publicKey === null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('This Checkout.com payment session expired. Start checkout again.')]);

            return redirect()->route('checkout.complete', $order);
        }

        return Inertia::render('module/modrixcommerce/checkout-com/storefront/flow', [
            'orderNumber' => $order->number,
            'publicKey' => $publicKey,
            'environment' => str_starts_with($publicKey, 'pk_sbox_') ? 'sandbox' : 'production',
            'paymentSession' => $session,
            'returnUrl' => route('checkout.payment.return', $payment),
            'cancelUrl' => route('checkout.complete', [$order, 'payment' => 'cancelled']),
        ]);
    }
}
