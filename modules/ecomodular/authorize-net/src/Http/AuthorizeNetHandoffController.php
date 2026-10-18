<?php

namespace EcoModular\AuthorizeNet\Http;

use App\Domains\Payments\Models\Payment;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Models\User;
use EcoModular\AuthorizeNet\AuthorizeNetEndpoint;
use EcoModular\AuthorizeNet\AuthorizeNetFormToken;
use EcoModular\AuthorizeNet\AuthorizeNetGateway;
use EcoModular\AuthorizeNet\AuthorizeNetSettings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Posts the Accept Hosted token to Authorize.Net. The token is not put in
 * the page URL. Only the customer who placed the order can open this page.
 */
class AuthorizeNetHandoffController extends Controller
{
    public function __invoke(Request $request, Payment $payment, AuthorizeNetSettings $settings): Response|SymfonyResponse
    {
        abort_unless($payment->provider === AuthorizeNetGateway::CODE, 404);

        $order = $payment->order;
        $user = $request->user();
        $ownsBySession = in_array($order->id, (array) $request->session()->get(CheckoutController::SESSION_ORDERS, []), true);
        $ownsByAccount = $user instanceof User && $order->user_id === $user->id;
        abort_unless($ownsBySession || $ownsByAccount, 404);

        if (! $payment->isOpen()) {
            return Inertia::location(route('checkout.complete', $order));
        }

        $token = AuthorizeNetFormToken::get($payment->id);
        $action = $settings->formUrl();

        if ($token === null || ! AuthorizeNetEndpoint::isFormUrl($action)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('This Authorize.Net payment page expired. Start checkout again.')]);

            return redirect()->route('checkout.complete', $order);
        }

        return Inertia::render('module/ecomodular/authorize-net/storefront/handoff', [
            'orderNumber' => $order->number,
            'action' => $action,
            'token' => $token,
        ]);
    }
}
