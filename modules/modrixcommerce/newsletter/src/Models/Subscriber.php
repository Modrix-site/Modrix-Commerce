<?php

namespace EcoModular\Newsletter\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A newsletter subscriber with the consent they gave. Only the email, the
 * consent text, the time and the sign-up place are stored (no IP address).
 *
 * @property int $id
 * @property string $email
 * @property string $consent_text
 * @property Carbon $consented_at
 * @property string $source
 * @property string $unsubscribe_token
 * @property Carbon|null $unsubscribed_at
 */
class Subscriber extends Model
{
    protected $table = 'newsletter_subscribers';

    protected $guarded = ['*'];

    protected $hidden = ['unsubscribe_token'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['consented_at' => 'datetime', 'unsubscribed_at' => 'datetime'];
    }
}
