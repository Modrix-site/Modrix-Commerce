<?php

namespace App\Http\Controllers\Admin;

use App\Core\Settings\StoreSettings;
use App\Domains\Orders\Documents\OrderDocuments;
use App\Domains\Orders\Enums\DisplayStatus;
use App\Domains\Orders\Enums\FulfillmentStatus;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\Refund;
use App\Domains\Orders\OrderEmailSettings;
use App\Domains\Orders\OrderRefunds;
use App\Domains\Orders\OrderWorkflow;
use App\Domains\Pricing\Money;
use App\Http\Controllers\Controller;
use App\Http\Presenters\OrderPresenter;
use App\Models\User;
use App\Support\Like;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function __construct(private readonly OrderWorkflow $workflow) {}

    public function index(Request $request, OrderPresenter $presenter, StoreSettings $store): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'display_status' => ['nullable', Rule::enum(DisplayStatus::class)],
            'ready' => ['nullable', 'boolean'],
            'payment_status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'fulfillment_status' => ['nullable', Rule::enum(FulfillmentStatus::class)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $timezone = $store->timezone();

        $orders = Order::query()
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($q) use ($search) {
                    Like::where($q, 'number', $search);
                    Like::orWhere($q, 'email', $search);
                });
            })
            ->when($filters['display_status'] ?? null, fn ($q, string $v) => $q->where('display_status', $v))
            ->when($filters['ready'] ?? false, fn ($q) => $q->readyToFulfil())
            ->when($filters['payment_status'] ?? null, fn ($q, string $v) => $q->where('payment_status', $v))
            ->when($filters['fulfillment_status'] ?? null, fn ($q, string $v) => $q->where('fulfillment_status', $v))
            // Date filters are interpreted in the store timezone.
            ->when($filters['from'] ?? null, fn ($q, string $v) => $q->where('placed_at', '>=', Carbon::parse($v, $timezone)->startOfDay()->utc()))
            ->when($filters['to'] ?? null, fn ($q, string $v) => $q->where('placed_at', '<=', Carbon::parse($v, $timezone)->endOfDay()->utc()))
            ->latest('placed_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Order $o) => $presenter->summary($o));

        return Inertia::render('admin/orders/index', [
            'orders' => $orders,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'display_status' => $filters['display_status'] ?? '',
                'ready' => ($filters['ready'] ?? false) ? '1' : '',
                'payment_status' => $filters['payment_status'] ?? '',
                'fulfillment_status' => $filters['fulfillment_status'] ?? '',
                'from' => $filters['from'] ?? '',
                'to' => $filters['to'] ?? '',
            ],
            'displayStatuses' => $this->options(DisplayStatus::cases()),
            'statusCounts' => (object) Order::query()->toBase()->selectRaw('display_status, count(*) as total')->groupBy('display_status')->pluck('total', 'display_status')->map(fn ($n) => (int) $n)->all(),
            'paymentStatuses' => $this->options(PaymentStatus::cases()),
            'fulfillmentStatuses' => $this->options(FulfillmentStatus::cases()),
        ]);
    }

    public function show(Request $request, Order $order, OrderPresenter $presenter, OrderEmailSettings $emails): Response
    {
        /** @var User $user */
        $user = $request->user();
        $allowed = $this->workflow->allowedActions($order);

        // The UI only offers actions the staff member may perform; routes re-check.
        $permitted = array_values(array_filter($allowed, fn (string $action) => $action === 'refund'
            ? $user->can('orders.refund')
            : $user->can('orders.manage')));

        return Inertia::render('admin/orders/show', [
            'order' => $presenter->forAdmin($order),
            'actions' => $permitted,
            'canAddNotes' => $user->can('orders.manage'),
            'emailDefaults' => $emails->all(),
            'canIssueInvoice' => $user->can('orders.manage') && $order->invoice === null && $order->status !== OrderStatus::Cancelled,
        ]);
    }

    public function recordPayment(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'reference' => ['nullable', 'string', 'max:191'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $this->workflow->recordManualPayment($order, $this->actor($request), $data['reference'] ?? null, $data['note'] ?? null);

        return $this->done($order, __('Payment recorded.'));
    }

    public function ship(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'carrier' => ['nullable', 'string', 'max:120'],
            'tracking_number' => ['nullable', 'string', 'max:120'],
            'tracking_url' => ['nullable', 'url:https,http', 'max:500'],
            'notify' => ['boolean'],
        ]);
        $this->workflow->ship($order, $this->actor($request), $data['carrier'] ?? null, $data['tracking_number'] ?? null, $data['tracking_url'] ?? null, $this->notify($data));

        return $this->done($order, __('Order marked as shipped.'));
    }

    public function issueInvoice(Request $request, Order $order, OrderDocuments $documents): RedirectResponse
    {
        $invoice = $documents->issueInvoice($order, $this->actor($request));

        return $this->done($order, __('Invoice :number issued.', ['number' => $invoice->number]));
    }

    public function advance(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(FulfillmentStatus::class)->except([FulfillmentStatus::Unfulfilled, FulfillmentStatus::DeliveryFailed, FulfillmentStatus::Returned])],
            'notify' => ['nullable', 'boolean'],
            'carrier' => ['nullable', 'string', 'max:120'],
            'tracking_number' => ['nullable', 'string', 'max:120'],
            'tracking_url' => ['nullable', 'url:https,http', 'max:500'],
        ]);
        $target = FulfillmentStatus::from($data['status']);
        $this->workflow->advanceFulfillment($order, $this->actor($request), $target, $this->notify($data), $data['carrier'] ?? null, $data['tracking_number'] ?? null, $data['tracking_url'] ?? null);

        return $this->done($order, __('Order moved to :status.', ['status' => $target->label()]));
    }

    public function deliveryFailed(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'notify' => ['boolean'],
        ]);
        $this->workflow->markDeliveryFailed($order, $this->actor($request), $data['reason'], $this->notify($data));

        return $this->done($order, __('Failed delivery recorded.'));
    }

    public function returned(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'restock' => ['boolean'],
            'notify' => ['boolean'],
        ]);
        $this->workflow->markReturned($order, $this->actor($request), $data['reason'], (bool) ($data['restock'] ?? false), $this->notify($data));

        return $this->done($order, __('Return recorded.'));
    }

    public function confirm(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate(['notify' => ['boolean']]);
        $this->workflow->confirm($order, $this->actor($request), $this->notify($data));

        return $this->done($order, __('Order confirmed.'));
    }

    public function hold(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
            'notify' => ['boolean'],
        ]);
        $this->workflow->hold($order, $this->actor($request), $data['reason'] ?? null, (bool) ($data['notify'] ?? false));

        return $this->done($order, __('Order put on hold.'));
    }

    public function release(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate(['notify' => ['boolean']]);
        $this->workflow->release($order, $this->actor($request), (bool) ($data['notify'] ?? false));

        return $this->done($order, __('Hold released.'));
    }

    public function pack(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate(['notify' => ['boolean']]);
        $this->workflow->markPacked($order, $this->actor($request), (bool) ($data['notify'] ?? false));

        return $this->done($order, __('Order marked as packed.'));
    }

    public function deliver(Request $request, Order $order): RedirectResponse
    {
        $this->workflow->markDelivered($order, $this->actor($request));

        return $this->done($order, __('Order marked as delivered.'));
    }

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $this->workflow->cancel($order, $this->actor($request), $data['reason']);

        return $this->done($order, __('Order cancelled and stock returned.'));
    }

    public function refund(Request $request, Order $order, OrderRefunds $refunds): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'string', 'regex:/^\d{1,13}(\.\d{1,4})?$/'],
            'reason' => ['required', 'string', 'max:500'],
            'restock' => ['nullable', 'array'],
            'restock.*' => ['integer', 'min:0', 'max:1000000'],
        ]);

        /** @var array<int, int> $restock */
        $restock = array_map('intval', $data['restock'] ?? []);
        $refund = $refunds->refund($order, $this->actor($request), Money::of($data['amount'], $order->currency), $data['reason'], $restock);

        return $this->done($order, $refund->status === Refund::STATUS_PENDING
            ? __('Refund issued. The payment provider is still processing it.')
            : __('Refund recorded.'));
    }

    public function note(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'customer_visible' => ['boolean'],
        ]);
        $this->workflow->addNote($order, $this->actor($request), $data['message'], (bool) ($data['customer_visible'] ?? false));

        return $this->done($order, __('Note added.'));
    }

    private function done(Order $order, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return redirect()->route('admin.orders.show', $order);
    }

    /**
     * The "Email the customer" choice for this change; null when not sent,
     * so the store's email setting applies.
     *
     * @param  array<string, mixed>  $data
     */
    private function notify(array $data): ?bool
    {
        return isset($data['notify']) ? (bool) $data['notify'] : null;
    }

    private function actor(Request $request): User
    {
        /** @var User */
        return $request->user();
    }

    /**
     * @param  list<DisplayStatus|PaymentStatus|FulfillmentStatus>  $cases
     * @return list<array{value: string, label: string}>
     */
    private function options(array $cases): array
    {
        return array_map(fn ($c) => ['value' => $c->value, 'label' => $c->label()], $cases);
    }
}
