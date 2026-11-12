<?php

namespace App\Domains\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A free tag on a product, stored normalised. Used for admin filtering and
 * storefront search; tags are not shown on the storefront.
 *
 * @property int $product_id
 * @property string $tag
 */
class ProductTag extends Model
{
    public const MAX_PER_PRODUCT = 20;

    public const MAX_LENGTH = 60;

    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'tag';

    protected $guarded = [];

    /** Trimmed, single spaces, lowercase. */
    public static function normalise(string $tag): string
    {
        return Str::lower(trim((string) preg_replace('/\s+/u', ' ', $tag)));
    }
}
