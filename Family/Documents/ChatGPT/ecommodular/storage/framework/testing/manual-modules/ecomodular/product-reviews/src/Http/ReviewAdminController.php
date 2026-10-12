<?php

namespace EcoModular\ProductReviews\Http;

use App\Core\Audit\AuditLogger;
use App\Http\Controllers\Controller;
use App\Models\User;
use EcoModular\ProductReviews\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin moderation. Every route is protected by `can:reviews.moderate`.
 */
class ReviewAdminController extends Controller
{
    private const ROUTE = 'admin.ecomodular.product-reviews.';

    public function index(Request $request): Response
    {
        $filters = $request->validate(['status' => ['nullable', Rule::in([Review::PENDING, Review::APPROVED, Review::REJECTED])]]);
        $status = $filters['status'] ?? Review::PENDING;

        $reviews = Review::query()
            ->with('product:id,name,slug')
            ->where('status', $status)
            ->latest()
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Review $r) => [
                'id' => $r->id,
                'product' => $r->product->name,
                'product_url' => route('storefront.product', $r->product->slug),
                'author' => $r->author_name,
                'rating' => $r->rating,
                'title' => $r->title,
                'body' => $r->body,
                'verified' => $r->verified_purchase,
                'status' => $r->status,
                'created_at' => $r->created_at?->toIso8601String(),
                'approve_url' => route(self::ROUTE.'approve', $r->id),
                'reject_url' => route(self::ROUTE.'reject', $r->id),
                'delete_url' => route(self::ROUTE.'destroy', $r->id),
            ]);

        return Inertia::render('module/ecomodular/product-reviews/admin/index', [
            'reviews' => $reviews,
            'status' => $status,
            'counts' => Review::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'indexUrl' => route(self::ROUTE.'index'),
        ]);
    }

    public function approve(Request $request, Review $review, AuditLogger $audit): RedirectResponse
    {
        return $this->moderate($request, $review, Review::APPROVED, $audit);
    }

    public function reject(Request $request, Review $review, AuditLogger $audit): RedirectResponse
    {
        return $this->moderate($request, $review, Review::REJECTED, $audit);
    }

    public function destroy(Review $review, AuditLogger $audit): RedirectResponse
    {
        $review->delete();
        $audit->record('reviews.deleted', null, ['review_id' => $review->id, 'product_id' => $review->product_id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Review deleted.']);

        return back();
    }

    private function moderate(Request $request, Review $review, string $status, AuditLogger $audit): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $review->forceFill(['status' => $status, 'moderated_by' => $actor->id, 'moderated_at' => now()])->save();
        $audit->record("reviews.{$status}", null, ['review_id' => $review->id, 'product_id' => $review->product_id], $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => $status === Review::APPROVED ? 'Review published.' : 'Review rejected.']);

        return back();
    }
}
