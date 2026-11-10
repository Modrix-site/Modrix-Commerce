<?php

namespace EcoModular\OrderReturns\Http;

use App\Domains\Orders\Models\Order;
use App\Http\Controllers\Controller;
use App\Models\User;
use EcoModular\OrderReturns\ReturnRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReturnController extends Controller
{
    public function index(Request $request, ReturnRequests $returns): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return Inertia::render('module/modrixcommerce/order-returns/storefront/index', [
            'returns' => $returns->forCustomer($user),
        ]);
    }

    public function store(Request $request, Order $order, ReturnRequests $returns): RedirectResponse
    {
        abort_unless($order->user_id !== null && $order->user_id === $request->user()?->getAuthIdentifier(), 404);

        $min = $returns->commentMin();
        $data = $request->validate([
            'comment' => ['required', 'string', 'min:'.$min, 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:0', 'max:1000'],
        ], [
            'comment.required' => 'Tell the store why you are returning these items.',
            'comment.min' => "Use at least {$min} characters.",
        ]);

        $returns->submit($order, $request->user(), $data['comment'], $data['items']);

        return back();
    }
}
