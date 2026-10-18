<?php

namespace EcoModular\ProductReviews\Http;

use App\Core\Extensions\ModuleSettingsSchema;
use App\Domains\Catalog\StorefrontCatalog;
use App\Http\Controllers\Controller;
use App\Models\User;
use EcoModular\ProductReviews\Models\Review;
use EcoModular\ProductReviews\ProductReviewsServiceProvider;
use EcoModular\ProductReviews\ReviewSummary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Storefront: a signed-in customer reviews a visible product, once.
 */
class ReviewController extends Controller
{
    public function store(Request $request, string $slug, StorefrontCatalog $catalog, ReviewSummary $summary, ModuleSettingsSchema $settings): RedirectResponse
    {
        $product = $catalog->findVisible($slug);
        abort_if($product === null, 404);

        /** @var User $user */
        $user = $request->user();
        $minLength = (int) $settings->of(ProductReviewsServiceProvider::MODULE, 'min_length', 10);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['required', 'string', "min:{$minLength}", 'max:2000'],
        ]);

        if ($summary->eligibility($user, $product) === 'purchase') {
            throw ValidationException::withMessages(['body' => 'Only verified buyers can review this product.']);
        }

        if (Review::query()->where('product_id', $product->id)->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages(['body' => 'You have already reviewed this product.']);
        }

        $autoApprove = (bool) $settings->of(ProductReviewsServiceProvider::MODULE, 'auto_approve', false);

        $review = new Review;
        $review->forceFill([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'author_name' => Str::limit(Str::before($user->name, ' ') ?: 'Customer', 80, ''),
            'rating' => (int) $data['rating'],
            'title' => $data['title'] ?? null,
            'body' => $data['body'],
            'status' => $autoApprove ? Review::APPROVED : Review::PENDING,
            'verified_purchase' => $summary->hasBought($user, $product),
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => $autoApprove
            ? 'Thank you! Your review is published.'
            : 'Thank you! Your review will appear once it has been approved.']);

        return back();
    }
}
