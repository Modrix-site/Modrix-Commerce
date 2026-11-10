<?php

namespace ModrixCommerce\PayTabs\Http;

use App\Domains\Payments\Models\Payment;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use ModrixCommerce\PayTabs\PayTabsGateway;

class PayTabsReturnController extends Controller
{
    public function __invoke(Request $request, Payment $payment, PayTabsGateway $gateway)
    {
        abort_unless($payment->provider === PayTabsGateway::CODE, 404);
        abort_if(strlen($request->getContent()) > 16384, 413);
        abort_unless($gateway->verifyReturn($payment, $request->post()), 400, 'Invalid PayTabs return signature.');

        // The GET restores SameSite=Lax guest cookies and the core enforces ownership,
        // then queries the provider. A signed browser status never marks an order paid.
        return redirect()->route('checkout.payment.return', $payment->order, 303);
    }
}
