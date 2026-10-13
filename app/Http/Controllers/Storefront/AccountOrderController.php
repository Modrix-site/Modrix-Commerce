<?php

namespace App\Http\Controllers\Storefront;

use App\Domains\Orders\Models\Order;
use App\Http\Controllers\Controller;
use App\Http\Presenters\OrderPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountOrderController extends Controller
{
    public function index(Request $request, OrderPresenter $presenter): Response
    {
        $orders = Order::query()
            ->where('user_id', $request->user()?->getAuthIdentifier())
            ->latest('placed_at')
            ->paginate(15)
            ->through(fn (Order $o) => $presenter->customerSummary($o));

        return Inertia::render('storefront/account/orders', ['orders' => $orders]);
    }

    public function show(Request $request, Order $order, OrderPresenter $presenter): Response
    {
        // Not found rather than forbidden, so order numbers cannot be probed.
        abort_unless($order->user_id !== null && $order->user_id === $request->user()?->getAuthIdentifier(), 404);

        return Inertia::render('storefront/account/order', ['order' => $presenter->forCustomer($order)]);
    }
}
