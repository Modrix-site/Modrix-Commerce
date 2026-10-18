<?php

namespace EcoModular\ProductReviews;

use App\Core\Extensions\ModuleSettingsSchema;
use App\Domains\Catalog\Models\Product;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Orders\Models\Order;
use App\Models\User;
use EcoModular\ProductReviews\Models\Review;

/**
 * Builds the storefront slot props and the review eligibility rules.
 */
final class ReviewSummary
{
    public function __construct(private readonly ModuleSettingsSchema $settings) {}

    /**
     * @return array<string, mixed>
     */
    public function forProduct(Product $product): array
    {
        $approved = Review::query()->published()->where('product_id', $product->id);
        $count = (clone $approved)->count();
        $user = auth()->user();
        $own = $user instanceof User ? Review::query()->where('product_id', $product->id)->where('user_id', $user->id)->first() : null;
        $eligibility = $user instanceof User ? $this->eligibility($user, $product) : 'login';

        return [
            'count' => $count,
            'average' => $count > 0 ? number_format((float) (clone $approved)->avg('rating'), 1) : null,
            'reviews' => (clone $approved)->latest()->orderByDesc('id')->limit(20)->get()->map(fn (Review $r) => [
                'id' => $r->id,
                'author' => $r->author_name,
                'rating' => $r->rating,
                'title' => $r->title,
                'body' => $r->body,
                'verified' => $r->verified_purchase,
                'date' => $r->created_at?->toDateString(),
            ])->values()->all(),
            'own' => $own === null ? null : ['status' => $own->status, 'rating' => $own->rating],
            'canReview' => $own === null && $eligibility === null,
            'reason' => $own === null ? $eligibility : null,
            'minLength' => (int) $this->settings->of(ProductReviewsServiceProvider::MODULE, 'min_length', 10),
            'submitUrl' => route('ecomodular.product-reviews.store', $product->slug),
            'loginUrl' => route('login'),
        ];
    }

    /**
     * The approved reviews' count and average, for the rating stars.
     *
     * @return array{count: int, average: string|null}
     */
    public function rating(Product $product): array
    {
        $approved = Review::query()->published()->where('product_id', $product->id);
        $count = (clone $approved)->count();

        return ['count' => $count, 'average' => $count > 0 ? number_format((float) $approved->avg('rating'), 1) : null];
    }

    /**
     * Only verified buyers review: a paid (or partly refunded) order of theirs
     * includes the product.
     *
     * @return string|null why the user cannot review ("purchase"), or null when they can
     */
    public function eligibility(User $user, Product $product): ?string
    {
        return $this->hasBought($user, $product) ? null : 'purchase';
    }

    public function hasBought(User $user, Product $product): bool
    {
        return Order::query()
            ->where('user_id', $user->id)
            ->whereIn('payment_status', [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded])
            ->whereHas('items', fn ($q) => $q->where('product_id', $product->id))
            ->exists();
    }
}
