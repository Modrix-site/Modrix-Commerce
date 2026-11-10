<?php

namespace EcoModular\FraudDetection\Http\Controllers;

use App\Core\Audit\AuditLogger;
use App\Http\Controllers\Controller;
use EcoModular\FraudDetection\Models\FraudAuditLog;
use EcoModular\FraudDetection\Models\FraudScore;
use EcoModular\FraudDetection\Services\CustomerIntelligenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FraudQueueController extends Controller
{
    private const ROUTE = 'admin.modrixcommerce.fraud-detection.queue.';

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CustomerIntelligenceService $customerIntelligence,
    ) {}

    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();
        $riskLevel = $request->string('risk_level')->toString();
        $search = $request->string('search')->toString();
        $sort = $request->string('sort', 'newest')->toString();

        $query = FraudScore::query()->with(['order', 'customer', 'reviewer'])->latest('id');

        if ($status !== '' && $status !== 'all') {
            $query->where('review_status', $status);
        }

        if ($riskLevel !== '' && $riskLevel !== 'all') {
            $query->where('risk_level', $riskLevel);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('customer_email', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhereHas('order', fn ($oq) => $oq->where('number', 'like', "%{$search}%"));
            });
        }

        if ($sort === 'highest_score') {
            $query->reorder()->orderByDesc('total_score');
        }

        $fraudScores = $query->paginate(25)->withQueryString()->through(fn (FraudScore $s) => [
            'id' => $s->id,
            'order_id' => $s->order_id,
            'order_number' => $s->order?->number ?? '#' . $s->order_id,
            'customer_email' => $s->customer_email,
            'customer_id' => $s->customer_id,
            'total_score' => $s->total_score,
            'risk_level' => $s->risk_level,
            'review_status' => $s->review_status,
            'indicators' => $s->indicators,
            'indicators_count' => is_array($s->indicators) ? count($s->indicators) : 0,
            'ip_address' => $s->ip_address,
            'order_total' => number_format((float) $s->order_total, 2),
            'currency' => $s->currency,
            'is_vpn' => $s->is_vpn,
            'is_proxy' => $s->is_proxy,
            'is_disposable_email' => $s->is_disposable_email,
            'address_mismatch' => $s->address_mismatch,
            'reviewed_by' => $s->reviewer?->name,
            'reviewed_at' => $s->reviewed_at?->diffForHumans(),
            'created_at' => $s->created_at?->diffForHumans(),
            'created_date' => $s->created_at?->toFormattedDateString(),
            'approve_url' => route(self::ROUTE . 'approve', $s),
            'reject_url' => route(self::ROUTE . 'reject', $s),
            'review_url' => route(self::ROUTE . 'review', $s),
            'note_url' => route(self::ROUTE . 'note', $s),
            'detail_url' => route(self::ROUTE . 'show', $s),
        ]);

        return Inertia::render('module/modrixcommerce/fraud-detection/admin/queue', [
            'fraudScores' => $fraudScores,
            'filters' => [
                'status' => $status ?: 'all',
                'risk_level' => $riskLevel ?: 'all',
                'search' => $search,
                'sort' => $sort,
            ],
        ]);
    }

    public function show(FraudScore $fraudScore): Response
    {
        $fraudScore->load(['order', 'customer', 'reviewer', 'reviews.user']);

        $customerProfile = null;
        if ($fraudScore->customer_id) {
            $customerProfile = $this->customerIntelligence->getCustomerProfile($fraudScore->customer_id);
        }

        return Inertia::render('module/modrixcommerce/fraud-detection/admin/order-detail', [
            'fraudScore' => [
                'id' => $fraudScore->id,
                'order_id' => $fraudScore->order_id,
                'order_number' => $fraudScore->order?->number ?? '#' . $fraudScore->order_id,
                'customer_email' => $fraudScore->customer_email,
                'customer_id' => $fraudScore->customer_id,
                'total_score' => $fraudScore->total_score,
                'risk_level' => $fraudScore->risk_level,
                'review_status' => $fraudScore->review_status,
                'indicators' => $fraudScore->indicators,
                'ip_address' => $fraudScore->ip_address,
                'customer_country' => $fraudScore->customer_country,
                'payment_country' => $fraudScore->payment_country,
                'is_vpn' => $fraudScore->is_vpn,
                'is_proxy' => $fraudScore->is_proxy,
                'is_disposable_email' => $fraudScore->is_disposable_email,
                'address_mismatch' => $fraudScore->address_mismatch,
                'failed_payment_attempts' => $fraudScore->failed_payment_attempts,
                'order_total' => number_format((float) $fraudScore->order_total, 2),
                'currency' => $fraudScore->currency,
                'review_notes' => $fraudScore->review_notes,
                'reviewed_by' => $fraudScore->reviewer?->name,
                'reviewed_at' => $fraudScore->reviewed_at?->toFormattedDateString(),
                'created_at' => $fraudScore->created_at?->diffForHumans(),
                'created_date' => $fraudScore->created_at?->toFormattedDateString(),
                'approve_url' => route(self::ROUTE . 'approve', $fraudScore),
                'reject_url' => route(self::ROUTE . 'reject', $fraudScore),
                'review_url' => route(self::ROUTE . 'review', $fraudScore),
                'note_url' => route(self::ROUTE . 'note', $fraudScore),
            ],
            'reviews' => $fraudScore->reviews->map(fn ($r) => [
                'id' => $r->id,
                'user_name' => $r->user?->name ?? 'System',
                'action' => $r->action,
                'comment' => $r->comment,
                'previous_status' => $r->previous_status,
                'new_status' => $r->new_status,
                'created_at' => $r->created_at?->diffForHumans(),
                'created_date' => $r->created_at?->toFormattedDateString(),
            ]),
            'customerProfile' => $customerProfile,
            'queueUrl' => route('admin.modrixcommerce.fraud-detection.queue.index'),
        ]);
    }

    public function approve(Request $request, FraudScore $fraudScore): RedirectResponse
    {
        $notes = $request->string('notes')->toString();
        $previousStatus = $fraudScore->review_status;
        $fraudScore->markAsApproved($request->user(), $notes ?: null);

        $this->customerIntelligence->syncAfterReview($fraudScore, $previousStatus, FraudScore::STATUS_APPROVED);

        FraudAuditLog::record('review.approved', 'fraud_score', $fraudScore->id, [
            'order_id' => $fraudScore->order_id,
            'previous_status' => $previousStatus,
            'notes' => $notes,
        ]);

        $this->audit->record('fraud.order_approved', null, [
            'fraud_score_id' => $fraudScore->id,
            'order_id' => $fraudScore->order_id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Order approved successfully.']);

        return back();
    }

    public function reject(Request $request, FraudScore $fraudScore): RedirectResponse
    {
        $notes = $request->string('notes')->toString();
        $previousStatus = $fraudScore->review_status;
        $fraudScore->markAsRejected($request->user(), $notes ?: null);

        $this->customerIntelligence->syncAfterReview($fraudScore, $previousStatus, FraudScore::STATUS_REJECTED);

        FraudAuditLog::record('review.rejected', 'fraud_score', $fraudScore->id, [
            'order_id' => $fraudScore->order_id,
            'previous_status' => $previousStatus,
            'notes' => $notes,
        ]);

        $this->audit->record('fraud.order_rejected', null, [
            'fraud_score_id' => $fraudScore->id,
            'order_id' => $fraudScore->order_id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Order rejected.']);

        return back();
    }

    public function markUnderReview(Request $request, FraudScore $fraudScore): RedirectResponse
    {
        $notes = $request->string('notes')->toString();
        $previousStatus = $fraudScore->review_status;
        $fraudScore->markAsUnderReview($request->user(), $notes ?: null);

        FraudAuditLog::record('review.under_review', 'fraud_score', $fraudScore->id, [
            'order_id' => $fraudScore->order_id,
            'previous_status' => $previousStatus,
            'notes' => $notes,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Order marked as under review.']);

        return back();
    }

    public function addNote(Request $request, FraudScore $fraudScore): RedirectResponse
    {
        $request->validate(['comment' => 'required|string|max:2000']);

        $fraudScore->addNote($request->user(), $request->input('comment'));

        FraudAuditLog::record('review.note_added', 'fraud_score', $fraudScore->id, [
            'order_id' => $fraudScore->order_id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Note added.']);

        return back();
    }
}
