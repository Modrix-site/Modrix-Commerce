<?php

namespace EcoModular\OrderReturns\Http;

use App\Core\Audit\AuditLogger;
use App\Http\Controllers\Controller;
use App\Models\User;
use EcoModular\OrderReturns\Models\OrderReturnRequest;
use EcoModular\OrderReturns\ReturnRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReturnAdminController extends Controller
{
    private const ROUTE = 'admin.modrixcommerce.order-returns.';

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in([OrderReturnRequest::PENDING, OrderReturnRequest::APPROVED, OrderReturnRequest::REJECTED])],
        ]);
        $status = $filters['status'] ?? OrderReturnRequest::PENDING;

        $returns = OrderReturnRequest::query()
            ->with(['order:id,number', 'customer:id,name,email', 'lines.orderItem:id,product_name'])
            ->where('status', $status)
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (OrderReturnRequest $return) => [
                'id' => $return->id,
                'status' => $return->status,
                'order_number' => $return->order->number,
                'order_url' => route('admin.orders.show', $return->order_id),
                'customer' => $return->customer->name,
                'email' => $return->customer->email,
                'customer_comment' => $return->customer_comment,
                'staff_comment' => $return->staff_comment,
                'created_at' => $return->created_at?->toIso8601String(),
                'items' => $return->lines->map(fn ($line) => [
                    'name' => $line->orderItem->product_name,
                    'quantity' => $line->quantity,
                ])->all(),
                'approve_url' => route(self::ROUTE.'approve', $return),
                'reject_url' => route(self::ROUTE.'reject', $return),
            ]);

        return Inertia::render('module/modrixcommerce/order-returns/admin/index', [
            'returns' => $returns,
            'status' => $status,
            'counts' => OrderReturnRequest::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'indexUrl' => route(self::ROUTE.'index'),
            'commentMin' => app(ReturnRequests::class)->commentMin(),
        ]);
    }

    public function approve(Request $request, OrderReturnRequest $returnRequest, ReturnRequests $returns, AuditLogger $audit): RedirectResponse
    {
        return $this->decide($request, $returnRequest, OrderReturnRequest::APPROVED, $returns, $audit);
    }

    public function reject(Request $request, OrderReturnRequest $returnRequest, ReturnRequests $returns, AuditLogger $audit): RedirectResponse
    {
        return $this->decide($request, $returnRequest, OrderReturnRequest::REJECTED, $returns, $audit);
    }

    private function decide(Request $request, OrderReturnRequest $returnRequest, string $status, ReturnRequests $returns, AuditLogger $audit): RedirectResponse
    {
        $min = $returns->commentMin();
        $data = $request->validate([
            'comment' => ['required', 'string', 'min:'.$min, 'max:2000'],
        ], [
            'comment.required' => 'Write a comment for the customer.',
            'comment.min' => "Use at least {$min} characters.",
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $returns->decide($returnRequest, $status, $data['comment'], $actor);
        $audit->record('returns.'.$status, $returnRequest->order, [
            'return_id' => $returnRequest->id,
            'order_id' => $returnRequest->order_id,
        ], $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => $status === OrderReturnRequest::APPROVED ? 'Return approved.' : 'Return rejected.']);

        return back();
    }
}
