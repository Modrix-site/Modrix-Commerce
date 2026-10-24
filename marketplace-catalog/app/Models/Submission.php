<?php

namespace Marketplace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A publisher's new package or update, waiting for review.
 *
 * @property int $id
 * @property string $identifier
 * @property string $kind new|update
 * @property string $status pending|approved|rejected
 * @property array<string, mixed> $data
 * @property string|null $zip_path
 */
class Submission extends Model
{
    protected $fillable = ['user_id', 'identifier', 'kind', 'data', 'zip_path', 'status', 'review_note', 'reviewed_by', 'reviewed_at'];

    protected function casts(): array
    {
        return ['data' => 'array', 'reviewed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
