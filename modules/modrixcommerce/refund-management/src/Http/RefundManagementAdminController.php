<?php

namespace EcoModular\RefundManagement\Http;

use App\Core\Settings\StoreSettings;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderItem;
use App\Domains\Orders\Models\Refund;
use App\Domains\Orders\OrderWorkflow;
use App\Domains\Pricing\Money;
use App\Http\Controllers\Controller;
use App\Http\Responses\PdfDownload;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use EcoModular\RefundManagement\Models\CreditMemo;
use EcoModular\RefundManagement\Models\RefundPaymentTrack;
use EcoModular\RefundManagement\RefundManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class RefundManagementAdminController extends Controller
{
    public function __construct(
        private readonly RefundManagementService $service,
        private readonly StoreSettings $store,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $tab = $request->string('tab')->toString();
        if (! in_array($tab, ['refunds', 'credit_memos', 'orders'], true)) {
            $tab = 'refunds';
        }

        $status = $request->string('status')->toString();
        if (! in_array($status, ['all', 'completed', 'pending', 'failed'], true)) {
            $status = 'all';
        }

        $search = trim($request->string('search')->toString());

        $refunds = Refund::query()
            ->with(['actor:id,name,email', 'items'])
            ->latest('id')
            ->limit(150)
            ->get();

        $orderIds = $refunds->pluck('order_id')->unique()->values()->all();
        $ordersById = Order::query()
            ->whereIn('id', $orderIds)
            ->with(['items', 'customer'])
            ->get()
            ->keyBy('id');

        $memosByRefund = CreditMemo::query()
            ->whereIn('refund_id', $refunds->pluck('id')->all())
            ->get()
            ->keyBy('refund_id');

        $tracksByRefund = RefundPaymentTrack::query()
            ->whereIn('refund_id', $refunds->pluck('id')->all())
            ->with('updater:id,name,email')
            ->get()
            ->keyBy('refund_id');

        // Backfill missing tracks in memory / DB so older refunds created before the module was installed can also be tracked immediately.
        foreach ($refunds as $refund) {
            if (! $tracksByRefund->has($refund->id) && $ordersById->has($refund->order_id)) {
                $tracksByRefund->put($refund->id, $this->service->recordPaymentTrack($refund));
            }
        }

        $storeCurrency = $this->store->currency();
        $totalRefundedAmount = Money::zero($storeCurrency);
        $completedCount = 0;
        $pendingCount = 0;
        $failedCount = 0;

        $presentedRefunds = [];

        foreach ($refunds as $refund) {
            /** @var Order|null $order */
            $order = $ordersById->get($refund->order_id);
            if ($order === null) {
                continue;
            }

            if ($refund->status === Refund::STATUS_COMPLETED) {
                $completedCount++;
                if ($refund->currency === $storeCurrency) {
                    $totalRefundedAmount = $totalRefundedAmount->plus($refund->money());
                }
            } elseif ($refund->status === Refund::STATUS_PENDING) {
                $pendingCount++;
            } elseif ($refund->status === Refund::STATUS_FAILED) {
                $failedCount++;
            }

            if ($status !== 'all' && $refund->status !== $status) {
                continue;
            }

            /** @var CreditMemo|null $memo */
            $memo = $memosByRefund->get($refund->id);
            /** @var RefundPaymentTrack|null $track */
            $track = $tracksByRefund->get($refund->id);

            if ($search !== '') {
                $haystack = strtolower(implode(' ', [
                    $order->number,
                    $order->email,
                    $refund->reason,
                    $refund->provider_reference ?? '',
                    $memo?->number ?? '',
                    $track?->reference ?? '',
                ]));
                if (! str_contains($haystack, strtolower($search))) {
                    continue;
                }
            }

            $itemsById = $order->items->keyBy('id');
            $refundLines = $refund->items->map(function ($line) use ($itemsById) {
                /** @var OrderItem|null $orderItem */
                $orderItem = $itemsById->get($line->order_item_id);

                return [
                    'id' => $line->id,
                    'order_item_id' => $line->order_item_id,
                    'name' => $orderItem?->product_name ?? 'Order item',
                    'sku' => $orderItem?->sku ?? '—',
                    'quantity' => $line->quantity,
                    'restocked' => (bool) $line->restocked,
                ];
            })->values()->all();

            $isFull = Money::of($refund->amount, $order->currency)->equals($order->money('grand_total'));

            $presentedRefunds[] = [
                'id' => $refund->id,
                'order_id' => $order->id,
                'order_number' => $order->number,
                'order_url' => route('admin.orders.show', $order, false),
                'customer_name' => $order->customer?->fullName() ?? $order->email,
                'customer_email' => $order->email,
                'payment_method_name' => $order->payment_method_name,
                'amount' => $refund->money()->format(),
                'amount_raw' => $refund->money()->toDisplayDecimal(),
                'currency' => $refund->currency,
                'type' => $isFull ? 'full' : 'partial',
                'reason' => $refund->reason,
                'status' => $refund->status,
                'provider_reference' => $refund->provider_reference,
                'failure_reason' => $refund->failure_reason,
                'actor' => $refund->actor?->name ?? $refund->actor?->email ?? 'System',
                'created_at' => $refund->created_at?->toIso8601String(),
                'items' => $refundLines,
                'credit_memo' => $memo ? [
                    'id' => $memo->id,
                    'number' => $memo->number,
                    'issued_at' => $memo->issued_at->toIso8601String(),
                    'pdf_url' => route('admin.modrixcommerce.refund-management.credit-memos.pdf', $memo, false),
                ] : null,
                'generate_memo_url' => route('admin.modrixcommerce.refund-management.credit-memos.generate', $refund, false),
                'update_track_url' => route('admin.modrixcommerce.refund-management.payment-tracks.update', $refund, false),
                'payment_track' => [
                    'channel' => $track?->channel ?? 'gateway',
                    'channel_label' => RefundPaymentTrack::CHANNELS[$track?->channel ?? 'gateway'] ?? 'Payment gateway',
                    'payout_status' => $track?->payout_status ?? RefundPaymentTrack::STATUS_PENDING,
                    'payout_status_label' => RefundPaymentTrack::STATUSES[$track?->payout_status ?? RefundPaymentTrack::STATUS_PENDING] ?? 'Pending',
                    'reference' => $track?->reference ?? $refund->provider_reference ?? '',
                    'destination' => $track?->destination ?? $order->email,
                    'notes' => $track?->notes ?? '',
                    'processed_at' => $track?->processed_at?->toIso8601String(),
                    'updated_by' => $track?->updater?->name ?? $track?->updater?->email,
                ],
            ];
        }

        $creditMemos = CreditMemo::query()
            ->with(['order', 'refund', 'issuer:id,name,email'])
            ->latest('id')
            ->limit(150)
            ->get()
            ->filter(function (CreditMemo $memo) use ($search) {
                if ($search === '') {
                    return true;
                }
                $haystack = strtolower(implode(' ', [
                    $memo->number,
                    $memo->order?->number ?? '',
                    $memo->buyer['email'] ?? '',
                    $memo->notes ?? '',
                ]));

                return str_contains($haystack, strtolower($search));
            })
            ->map(fn (CreditMemo $memo) => [
                'id' => $memo->id,
                'number' => $memo->number,
                'refund_id' => $memo->refund_id,
                'order_number' => $memo->order?->number ?? '—',
                'order_url' => $memo->order ? route('admin.orders.show', $memo->order, false) : null,
                'buyer_email' => $memo->buyer['email'] ?? $memo->order?->email ?? '—',
                'subtotal' => $memo->totals['subtotal'] ?? $memo->money('subtotal')->format(),
                'tax_total' => $memo->totals['tax_total'] ?? $memo->money('tax_total')->format(),
                'shipping_refund' => $memo->totals['shipping_refund'] ?? null,
                'adjustment_amount' => $memo->totals['adjustment_amount'] ?? null,
                'grand_total' => $memo->totals['grand_total'] ?? $memo->money('grand_total')->format(),
                'lines_count' => count($memo->lines),
                'lines' => $memo->lines,
                'notes' => $memo->notes,
                'issued_by' => $memo->issuer?->name ?? $memo->issuer?->email ?? 'System',
                'issued_at' => $memo->issued_at->toIso8601String(),
                'pdf_url' => route('admin.modrixcommerce.refund-management.credit-memos.pdf', $memo, false),
            ])
            ->values()
            ->all();

        $refundableOrders = Order::query()
            ->where('status', '!=', OrderStatus::Cancelled->value)
            ->whereColumn('paid_total', '>', 'refunded_total')
            ->with(['items', 'customer'])
            ->latest('id')
            ->limit(100)
            ->get()
            ->filter(fn (Order $order) => $order->refundable()->isPositive())
            ->map(function (Order $order) {
                $currency = $order->currency;

                return [
                    'id' => $order->id,
                    'number' => $order->number,
                    'order_url' => route('admin.orders.show', $order, false),
                    'store_refund_url' => route('admin.modrixcommerce.refund-management.refunds.store', $order, false),
                    'customer_name' => $order->customer?->fullName() ?? $order->email,
                    'email' => $order->email,
                    'currency' => $currency,
                    'payment_method_code' => $order->payment_method_code,
                    'payment_method_name' => $order->payment_method_name,
                    'is_manual_payment' => isset(OrderWorkflow::MANUAL_METHODS[$order->payment_method_code]),
                    'grand_total' => $order->money('grand_total')->format(),
                    'paid_total' => $order->money('paid_total')->format(),
                    'refunded_total' => $order->money('refunded_total')->format(),
                    'refundable_formatted' => $order->refundable()->format(),
                    'refundable_amount' => $order->refundable()->toDisplayDecimal(),
                    'shipping_total_amount' => $order->money('shipping_total')->toDisplayDecimal(),
                    'shipping_total_formatted' => $order->money('shipping_total')->format(),
                    'placed_at' => $order->placed_at->toIso8601String(),
                    'items' => $order->items->map(function (OrderItem $item) use ($currency) {
                        $effectiveUnit = $item->quantity > 0
                            ? Money::of(
                                (string) BigDecimal::of($item->total)->dividedBy($item->quantity, 4, RoundingMode::HALF_UP),
                                $currency,
                            )
                            : $item->money('unit_price', $currency);

                        return [
                            'id' => $item->id,
                            'sku' => $item->sku,
                            'product_name' => $item->product_name,
                            'variant_name' => $item->variant_name,
                            'quantity' => $item->quantity,
                            'restocked_quantity' => $item->restocked_quantity,
                            'restockable_quantity' => max(0, $item->quantity - $item->restocked_quantity),
                            'unit_price_formatted' => $item->money('unit_price', $currency)->format(),
                            'effective_unit_amount' => $effectiveUnit->toDisplayDecimal(),
                            'total_formatted' => $item->money('total', $currency)->format(),
                        ];
                    })->values()->all(),
                ];
            })
            ->values()
            ->all();

        $channels = [];
        foreach (RefundPaymentTrack::CHANNELS as $value => $label) {
            $channels[] = ['value' => $value, 'label' => $label];
        }

        $payoutStatuses = [];
        foreach (RefundPaymentTrack::STATUSES as $value => $label) {
            $payoutStatuses[] = ['value' => $value, 'label' => $label];
        }

        return Inertia::render('module/modrixcommerce/refund-management/admin/index', [
            'tab' => $tab,
            'status' => $status,
            'search' => $search,
            'stats' => [
                'total_refunded' => $totalRefundedAmount->format(),
                'completed_count' => $completedCount,
                'pending_count' => $pendingCount,
                'failed_count' => $failedCount,
                'credit_memos_count' => CreditMemo::query()->count(),
                'refundable_orders_count' => count($refundableOrders),
            ],
            'refunds' => $presentedRefunds,
            'creditMemos' => $creditMemos,
            'refundableOrders' => $refundableOrders,
            'channels' => $channels,
            'payoutStatuses' => $payoutStatuses,
            'defaults' => [
                'auto_generate_credit_memo' => $this->service->autoGenerateCreditMemo(),
                'default_restock' => $this->service->defaultRestock(),
            ],
        ]);
    }

    public function storeRefund(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(['full', 'items', 'custom'])],
            'amount' => ['nullable', 'string', 'max:32'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'restock_all' => ['nullable', 'boolean'],
            'items' => ['nullable', 'array'],
            'items.*.quantity' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'items.*.restock' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'shipping_refund' => ['nullable', 'string', 'max:32'],
            'adjustment_amount' => ['nullable', 'string', 'max:32'],
            'channel' => ['nullable', Rule::in(array_keys(RefundPaymentTrack::CHANNELS))],
            'payout_status' => ['nullable', Rule::in(array_keys(RefundPaymentTrack::STATUSES))],
            'reference' => ['nullable', 'string', 'max:191'],
            'destination' => ['nullable', 'string', 'max:191'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'generate_credit_memo' => ['nullable', 'boolean'],
        ]);

        $refund = $this->service->processRefund($order, $request->user(), $data);

        return back()->with(
            'status',
            "Refund of {$refund->money()->format()} processed for order {$order->number}."
        );
    }

    public function generateCreditMemo(Request $request, Refund $refund): RedirectResponse
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $memo = $this->service->issueCreditMemo($refund, $request->user(), [
            'notes' => $data['notes'] ?? null,
        ]);

        return back()->with('status', "Credit memo {$memo->number} issued.");
    }

    public function downloadCreditMemoPdf(CreditMemo $creditMemo): Response
    {
        return PdfDownload::make(
            $this->service->creditMemoPdf($creditMemo),
            "credit-memo-{$creditMemo->number}.pdf",
        );
    }

    public function updatePaymentTrack(Request $request, Refund $refund): RedirectResponse
    {
        $data = $request->validate([
            'channel' => ['required', Rule::in(array_keys(RefundPaymentTrack::CHANNELS))],
            'payout_status' => ['required', Rule::in(array_keys(RefundPaymentTrack::STATUSES))],
            'reference' => ['nullable', 'string', 'max:191'],
            'destination' => ['nullable', 'string', 'max:191'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->service->recordPaymentTrack($refund, $request->user(), $data);

        return back()->with('status', 'Refund payment tracking updated.');
    }
}
