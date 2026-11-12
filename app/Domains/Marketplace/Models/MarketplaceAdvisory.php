<?php

namespace App\Domains\Marketplace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A security advisory for a marketplace product. Stores receive it inside
 * the signed license token and warn when their installed version is affected.
 *
 * @property int $id
 * @property int $product_id
 * @property string $severity
 * @property string $title
 * @property string $body
 * @property string $affected_versions
 * @property string|null $fixed_version
 * @property int|null $created_by
 * @property Carbon $published_at
 * @property-read MarketplaceProduct $product
 */
class MarketplaceAdvisory extends Model
{
    public const SEVERITIES = ['low', 'medium', 'high', 'critical'];

    protected $table = 'marketplace_advisories';

    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<MarketplaceProduct, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(MarketplaceProduct::class, 'product_id');
    }

    /**
     * The form signed into license tokens.
     *
     * @return array{id: int, severity: string, title: string, body: string, affected_versions: string, fixed_version: string|null, published_at: string}
     */
    public function payload(): array
    {
        return [
            'id' => $this->id,
            'severity' => $this->severity,
            'title' => $this->title,
            'body' => $this->body,
            'affected_versions' => $this->affected_versions,
            'fixed_version' => $this->fixed_version,
            'published_at' => $this->published_at->toIso8601String(),
        ];
    }
}
