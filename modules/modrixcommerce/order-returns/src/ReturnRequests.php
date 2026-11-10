<?php

namespace EcoModular\OrderReturns;

use App\Core\Extensions\ModuleSettingsSchema;
use App\Domains\Orders\Enums\FulfillmentStatus;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderItem;
use App\Models\User;
use EcoModular\OrderReturns\Models\OrderReturnItem;
use EcoModular\OrderReturns\Models\OrderReturnRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Rules for requesting a return and for staff approving or rejecting it.
 */
final class ReturnRequests
{
    public function __construct(private readonly ModuleSettingsSchema $settings) {}

    public function windowDays(): int
    {
        $days = $this->settings->of(OrderReturnsServiceProvider::MODULE, 'window_days', 30);

        return is_int($days) && $days >= 1 ? $days : 30;
    }

    public function commentMin(): int
    {
        $min = $this->settings->of(OrderReturnsServiceProvider::MODULE, 'comment_min', 10);

        return is_int($min) && $min >= 1 ? $min : 10;
    }

    public function eligibleAfter(): string
    {
        return $this->settings->of(OrderReturnsServiceProvider::MODULE, 'eligible_after', 'delivered') === 'shipped'
            ? 'shipped'
            : 'delivered';
    }

    /**
     * @return array<string, mixed>
     */
    public function present(Order $order): array
    {
        $order->loadMissing('items');
        $committed = $this->committedQuantities($order);
        $reason = $this->blockReason($order);
        $items = $order->items->map(function (OrderItem $item) use ($committed) {
            $returnable = max(0, $item->quantity - ($committed[$item->id] ?? 0));

            return [
                'id' => $item->id,
                'name' => $item->product_name,
                'quantity' => $item->quantity,
                'returnable' => $returnable,
            ];
        })->values()->all();

        $canRequest = $reason === null && collect($items)->contains(fn (array $item) => $item['returnable'] > 0);

        return [
            'canRequest' => $canRequest,
            'reason' => $canRequest ? null : ($reason ?? 'Every item on this order already has a return request.'),
            'windowDays' => $this->windowDays(),
            'commentMin' => $this->commentMin(),
            'submitUrl' => route('modrixcommerce.order-returns.store', $order),
            'items' => $items,
            'requests' => $this->history($order),
        ];
    }

    /**
     * @param  list<array{order_item_id: int, quantity: int}>  $lines
     *
     * @throws ValidationException
     */
    public function submit(Order $order, User $customer, string $comment, array $lines): OrderReturnRequest
    {
        $chosen = [];

        foreach ($lines as $line) {
            $quantity = (int) ($line['quantity'] ?? 0);

            if ($quantity < 1) {
                continue;
            }

            $chosen[(int) $line['order_item_id']] = ($chosen[(int) $line['order_item_id']] ?? 0) + $quantity;
        }

        if ($chosen === []) {
            throw ValidationException::withMessages(['items' => 'Choose at least one item to return.']);
        }

        return DB::transaction(function () use ($order, $customer, $comment, $chosen) {
            $order->load('items');
            $reason = $this->blockReason($order);

            if ($reason !== null) {
                throw ValidationException::withMessages(['order' => $reason]);
            }

            $committed = $this->committedQuantities($order);

            foreach ($chosen as $itemId => $quantity) {
                $item = $order->items->firstWhere('id', $itemId);
                $returnable = $item instanceof OrderItem ? $item->quantity - ($committed[$itemId] ?? 0) : 0;

                if ($quantity > $returnable) {
                    throw ValidationException::withMessages(['items' => 'One or more items exceed the quantity that can still be returned.']);
                }
            }

            $request = new OrderReturnRequest;
            $request->forceFill([
                'order_id' => $order->id,
                'user_id' => $customer->id,
                'status' => OrderReturnRequest::PENDING,
                'customer_comment' => $comment,
            ])->save();

            foreach ($chosen as $itemId => $quantity) {
                $line = new OrderReturnItem;
                $line->forceFill([
                    'order_return_request_id' => $request->id,
                    'order_item_id' => $itemId,
                    'quantity' => $quantity,
                ])->save();
            }

            return $request;
        });
    }

    /**
     * @throws ValidationException
     */
    public function decide(OrderReturnRequest $request, string $status, string $comment, User $actor): void
    {
        if ($request->status !== OrderReturnRequest::PENDING) {
            throw ValidationException::withMessages(['comment' => 'This return has already been decided.']);
        }

        $request->forceFill([
            'status' => $status,
            'staff_comment' => $comment,
            'decided_by' => $actor->id,
            'decided_at' => now(),
        ])->save();
    }

    private function blockReason(Order $order): ?string
    {
        if ($order->status === OrderStatus::Cancelled) {
            return 'Cancelled orders cannot be returned.';
        }

        $needed = $this->eligibleAfter() === 'shipped' ? FulfillmentStatus::Shipped : FulfillmentStatus::Delivered;

        if ($order->fulfillment_status->rank() < $needed->rank() || $order->fulfillment_status === FulfillmentStatus::DeliveryFailed) {
            return $needed === FulfillmentStatus::Shipped
                ? 'Returns open after the order has shipped.'
                : 'Returns open after the order has been delivered.';
        }

        $started = $needed === FulfillmentStatus::Shipped
            ? ($order->shipped_at ?? $order->delivered_at)
            : $order->delivered_at;

        if ($started === null || $started->copy()->addDays($this->windowDays())->lt(now())) {
            return 'The return window for this order has closed.';
        }

        $pending = OrderReturnRequest::query()
            ->where('order_id', $order->id)
            ->where('status', OrderReturnRequest::PENDING)
            ->exists();

        return $pending ? 'This order already has a return waiting for review.' : null;
    }

    /**
     * @return array<int, int>
     */
    private function committedQuantities(Order $order): array
    {
        $totals = [];
        $lines = OrderReturnItem::query()
            ->whereHas('request', fn ($query) => $query
                ->where('order_id', $order->id)
                ->whereIn('status', [OrderReturnRequest::PENDING, OrderReturnRequest::APPROVED]))
            ->get();

        foreach ($lines as $line) {
            $totals[$line->order_item_id] = ($totals[$line->order_item_id] ?? 0) + $line->quantity;
        }

        return $totals;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forCustomer(User $user): array
    {
        return OrderReturnRequest::query()
            ->where('user_id', $user->id)
            ->with(['lines.orderItem', 'order'])
            ->latest('id')
            ->get()
            ->map(fn (OrderReturnRequest $request) => [
                'id' => $request->id,
                'status' => $request->status,
                'order_number' => $request->order?->number,
                'order_url' => $request->order?->number
                    ? route('account.orders.show', $request->order, absolute: false)
                    : null,
                'customer_comment' => $request->customer_comment,
                'staff_comment' => $request->staff_comment,
                'created_at' => $request->created_at?->toIso8601String(),
                'items' => $request->lines->map(fn (OrderReturnItem $line) => [
                    'name' => $line->orderItem->product_name,
                    'quantity' => $line->quantity,
                ])->all(),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function history(Order $order): array
    {
        return OrderReturnRequest::query()
            ->where('order_id', $order->id)
            ->with('lines.orderItem')
            ->latest('id')
            ->get()
            ->map(fn (OrderReturnRequest $request) => [
                'id' => $request->id,
                'status' => $request->status,
                'customer_comment' => $request->customer_comment,
                'staff_comment' => $request->staff_comment,
                'created_at' => $request->created_at?->toIso8601String(),
                'items' => $request->lines->map(fn (OrderReturnItem $line) => [
                    'name' => $line->orderItem->product_name,
                    'quantity' => $line->quantity,
                ])->all(),
            ])
            ->all();
    }
}
