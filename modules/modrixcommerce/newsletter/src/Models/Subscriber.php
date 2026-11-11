<?php

namespace EcoModular\Newsletter\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A newsletter subscriber with their consent and segmentation tags.
 *
 * @property int $id
 * @property string|null $name
 * @property string $email
 * @property string $consent_text
 * @property Carbon $consented_at
 * @property string $source
 * @property array<string>|null $groups
 * @property string $unsubscribe_token
 * @property Carbon|null $unsubscribed_at
 */
class Subscriber extends Model
{
    protected $table = 'newsletter_subscribers';

    protected $fillable = [
        'name',
        'email',
        'consent_text',
        'consented_at',
        'source',
        'groups',
        'unsubscribe_token',
        'unsubscribed_at',
    ];

    protected $hidden = ['unsubscribe_token'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'consented_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
            'groups' => 'array',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('unsubscribed_at');
    }

    public function scopeUnsubscribed(Builder $query): Builder
    {
        return $query->whereNotNull('unsubscribed_at');
    }

    public function scopeCustomers(Builder $query): Builder
    {
        return $query->where('source', 'order');
    }

    public function scopeInGroup(Builder $query, string $slug): Builder
    {
        return $query->whereJsonContains('groups', $slug);
    }
}
