<?php

namespace App\Http\Controllers\Marketplace;

use App\Core\Audit\AuditLogger;
use App\Domains\Marketplace\Models\License;
use App\Domains\Marketplace\Models\MarketplaceProduct;
use App\Domains\Marketplace\Models\MarketplaceReview;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Product reviews: only active license holders may review, once per
 * product; reviews appear after the owner approves them.
 */
class MarketplaceReviewController extends Controller
{
    public function store(Request $request, MarketplaceProduct $product): RedirectResponse
    {
        abort_unless($product->isListed(), 404);
        $user = $this->user($request);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['required', 'string', 'min:20', 'max:3000'],
        ]);

        if (! License::query()->where('user_id', $user->id)->where('product_id', $product->id)->where('status', License::ACTIVE)->exists()) {
            throw ValidationException::withMessages(['body' => 'Only customers with an active license can review this product.']);
        }

        if (MarketplaceReview::query()->where('product_id', $product->id)->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages(['body' => 'You already reviewed this product.']);
        }

        $review = new MarketplaceReview;
        $review->forceFill([...$data, 'product_id' => $product->id, 'user_id' => $user->id, 'status' => MarketplaceReview::PENDING])->save();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Thank you. Your review appears after moderation.']);

        return back();
    }

    public function index(): Response
    {
        return Inertia::render('admin/marketplace/reviews', [
            'reviews' => MarketplaceReview::query()->with(['product:id,name,slug', 'user:id,name,email'])
                ->where('status', MarketplaceReview::PENDING)->oldest()->limit(100)->get()
                ->map(fn (MarketplaceReview $r) => [
                    'id' => $r->id,
                    'product' => $r->product->name,
                    'author' => $r->user->name,
                    'email' => $r->user->email,
                    'rating' => $r->rating,
                    'title' => $r->title,
                    'body' => $r->body,
                    'created_at' => $r->created_at?->toDateString(),
                ]),
        ]);
    }

    public function moderate(Request $request, MarketplaceReview $review, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', 'in:approve,reject']]);
        $status = $data['decision'] === 'approve' ? MarketplaceReview::APPROVED : MarketplaceReview::REJECTED;

        $review->forceFill(['status' => $status, 'moderated_by' => $this->user($request)->id, 'moderated_at' => now()])->save();
        $audit->record('marketplace.review_moderated', null, ['review' => $review->id, 'status' => $status]);

        return back();
    }

    /**
     * Review data for the product page.
     *
     * @return array<string, mixed>
     */
    public static function forProduct(MarketplaceProduct $product, ?User $user): array
    {
        $approved = MarketplaceReview::query()->with('user:id,name')->where('product_id', $product->id)->where('status', MarketplaceReview::APPROVED);
        $mine = $user === null ? null : MarketplaceReview::query()->where('product_id', $product->id)->where('user_id', $user->id)->first();

        return [
            'average' => ($avg = (clone $approved)->avg('rating')) === null ? null : round((float) $avg, 1),
            'count' => (clone $approved)->count(),
            'items' => $approved->latest()->limit(20)->get()->map(fn (MarketplaceReview $r) => [
                'id' => $r->id,
                'author' => $r->user->name,
                'rating' => $r->rating,
                'title' => $r->title,
                'body' => $r->body,
                'created_at' => $r->created_at?->toDateString(),
            ]),
            'mine' => $mine?->status,
            'canReview' => $user !== null && $mine === null
                && License::query()->where('user_id', $user->id)->where('product_id', $product->id)->where('status', License::ACTIVE)->exists(),
        ];
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
