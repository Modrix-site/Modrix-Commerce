<?php

namespace EcoModular\Telr\Http;

use App\Domains\Payments\Gateway\GatewayException;
use App\Domains\Payments\Models\Payment;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Models\User;
use EcoModular\Telr\TelrCheckoutLink;
use EcoModular\Telr\TelrGateway;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Embeds the Telr payment page for the customer who placed the order.
 * The iframe URL is always https://secure.telr.com; card fields stay there.
 */
class TelrFrameController extends Controller
{
    public function __invoke(Request $request, Payment $payment): Response|SymfonyResponse
    {
        abort_unless($payment->provider === TelrGateway::CODE, 404);

        $order = $payment->order;
        $user = $request->user();
        $ownsBySession = in_array($order->id, (array) $request->session()->get(CheckoutController::SESSION_ORDERS, []), true);
        $ownsByAccount = $user instanceof User && $order->user_id === $user->id;
        abort_unless($ownsBySession || $ownsByAccount, 404);

        if (! $payment->isOpen()) {
            return Inertia::location(route('checkout.complete', $order));
        }

        try {
            $frameUrl = TelrCheckoutLink::urlFor($payment);
        } catch (GatewayException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return redirect()->route('checkout.complete', $order);
        }

        return Inertia::render('module/modrixcommerce/telr/storefront/frame', [
            'orderNumber' => $order->number,
            'frameUrl' => $frameUrl,
            'cancelUrl' => route('checkout.complete', [$order, 'payment' => 'cancelled']),
        ]);
    }
}
