<?php

namespace EcoModular\Newsletter\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A subscriber group / audience segment for email marketing.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $color
 */
class NewsletterGroup extends Model
{
    protected $table = 'newsletter_groups';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'color',
    ];

    public function subscribersCount(): int
    {
        return Subscriber::query()->active()->inGroup($this->slug)->count();
    }
}
