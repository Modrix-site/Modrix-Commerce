<?php

namespace App\Domains\Marketplace\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A versioned package of a product. Published only after it passed
 * validation and the owner approved the review; then its metadata is signed.
 *
 * @property int $id
 * @property int $product_id
 * @property string $version
 * @property string $platform_version
 * @property string|null $changelog
 * @property string $status
 * @property string $archive_path
 * @property string $sha256
 * @property int $size
 * @property array<string, mixed> $manifest
 * @property array<string, mixed>|null $signed_metadata
 * @property string|null $signature
 * @property string|null $review_notes
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property-read MarketplaceProduct $product
 * @property-read User|null $reviewer
 */
class MarketplaceRelease extends Model
{
    public const PENDING_REVIEW = 'pending_review';

    public const PUBLISHED = 'published';

    public const REJECTED = 'rejected';

    public const WITHDRAWN = 'withdrawn';

    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'manifest' => 'array',
            'signed_metadata' => 'array',
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<MarketplaceProduct, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(MarketplaceProduct::class, 'product_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * What a store needs to verify the package (signed metadata + signature).
     *
     * @return array{metadata: array<string, mixed>, signature: string}|null
     */
    public function verification(): ?array
    {
        return $this->signed_metadata !== null && $this->signature !== null
            ? ['metadata' => $this->signed_metadata, 'signature' => $this->signature]
            : null;
    }
}
