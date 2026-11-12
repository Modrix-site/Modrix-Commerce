<?php

namespace App\Core\Media;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * An image in the media library. `collection` says where it came from:
 * `library` (uploaded to the library), `products` (uploaded on a product)
 * or `themes` (uploaded in the theme customizer).
 *
 * @property int $id
 * @property string $disk
 * @property string $path
 * @property string $collection
 * @property string $filename
 * @property string $mime
 * @property int $size
 * @property int|null $width
 * @property int|null $height
 * @property string|null $alt
 * @property int|null $uploaded_by
 * @property Carbon|null $created_at
 * @property-read User|null $uploader
 */
class Media extends Model
{
    public const LIBRARY = 'library';

    public const PRODUCTS = 'products';

    public const THEMES = 'themes';

    protected $table = 'media';

    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['size' => 'integer', 'width' => 'integer', 'height' => 'integer'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    /** The value theme image settings use for this file. */
    public function themeValue(): string
    {
        return 'media:'.$this->path;
    }
}
