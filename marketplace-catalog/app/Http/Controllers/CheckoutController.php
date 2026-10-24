<?php

namespace Marketplace\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Marketplace\Models\Order;
use Marketplace\Models\Package;
use Marketplace\Services\Catalog;
use Marketplace\Services\StripeGateway;
use RuntimeException;

/**
 * Buying a paid package: a pending order, then Stripe hosted Checkout. The
 * license is issued by the webhook, not by the redirect back.
 */
class CheckoutController
{
    public function store(Request $request, Package $package, StripeGateway $stripe, Catalog $catalog): RedirectResponse
    {
        abort_if($package->status !== 'published' || $package->is_free, 404);

        if ($catalog->owns($request->user(), $package)) {
            return back()->withErrors(['checkout' => 'You already own this package. Its license key is in your account.']);
        }

        if (! $stripe->configured()) {
            return back()->withErrors(['checkout' => 'Payments are not available yet. Please try again later.']);
        }

        $order = Order::query()->create([
            'user_id' => $request->user()->id,
            'package_id' => $package->id,
            'amount' => $package->price,
            'currency' => $catalog->currency(),
            'status' => 'pending',
        ]);

        try {
            $url = $stripe->createSession($order, route('checkout.success', ['order' => $order->id]), url('/item/'.$package->slug));
        } catch (RuntimeException $e) {
            report($e);
            $order->forceFill(['status' => 'expired'])->save();

            return back()->withErrors(['checkout' => 'The payment page could not be opened. Please try again.']);
        }

        return redirect()->away($url);
    }

    /** Back from Stripe: shows the key once the webhook has marked the order paid. */
    public function success(Request $request): View
    {
        $order = Order::query()->with(['package', 'license'])
            ->whereKey((int) $request->query('order'))
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        // Refresh every 4 s for about two minutes, then stop and explain.
        $try = max(0, (int) $request->query('try', 0));

        return view('checkout.success', ['order' => $order, 'try' => $try, 'refresh' => $order->status === 'pending' && $try < 30]);
    }
}
