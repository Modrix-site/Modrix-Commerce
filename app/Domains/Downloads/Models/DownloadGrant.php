<?php

namespace App\Domains\Downloads\Models;

use App\Domains\Catalog\Models\ProductFile;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderItem;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A customer's link to one file of a digital product they paid for. The
 * random token is the secret; it works without signing in (guests too).
 *
 * @property int $id
 * @property int $order_id
 * @property int $order_item_id
 * @property int $product_file_id
 * @property string $token
 * @property int $downloads
 * @property int|null $max_downloads
 * @property Carbon|null $expires_at
 * @property Carbon|null $revoked_at
 * @property-read Order $order
 * @property-read OrderItem $item
 * @property-read ProductFile|null $file
 */
class DownloadGrant extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'downloads' => 'integer',
            'max_downloads' => 'integer',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<OrderItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    /**
     * @return BelongsTo<ProductFile, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(ProductFile::class, 'product_file_id');
    }

    /**
     * @return HasMany<DownloadLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(DownloadLog::class);
    }

    /** Not revoked, not expired and under its download limit. */
    public function isUsable(CarbonInterface $now): bool
    {
        return $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isAfter($now))
            && ($this->max_downloads === null || $this->downloads < $this->max_downloads);
    }

    /** Downloads left, or null for unlimited. */
    public function remaining(): ?int
    {
        return $this->max_downloads === null ? null : max(0, $this->max_downloads - $this->downloads);
    }
}
