<?php

namespace App\Domains\Catalog\Models;

use App\Domains\Pricing\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $variant_id
 * @property string $currency
 * @property string $amount
 * @property string|null $compare_at_amount
 */
#[Fillable(['currency', 'amount', 'compare_at_amount'])]
class ProductPrice extends Model
{
    /**
     * Decimal casts return exact strings on every driver (SQLite returns floats otherwise).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['amount' => 'decimal:4', 'compare_at_amount' => 'decimal:4'];
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function money(): Money
    {
        return Money::of($this->amount, $this->currency);
    }

    public function compareAtMoney(): ?Money
    {
        return $this->compare_at_amount === null ? null : Money::of($this->compare_at_amount, $this->currency);
    }
}
