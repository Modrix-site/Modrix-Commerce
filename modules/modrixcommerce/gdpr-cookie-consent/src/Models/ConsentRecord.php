<?php

namespace EcoModular\GdprCookieConsent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A single consent decision made in the cookie banner. Stored for GDPR
 * accountability (art. 7(1)): what was shown, what was chosen, when and from
 * which country. No IP address is stored.
 *
 * @property int $id
 * @property string $visitor_token
 * @property string $decision
 * @property bool $analytics_allowed
 * @property bool $marketing_allowed
 * @property string|null $country_code
 * @property string $consent_text
 * @property string|null $page
 * @property string|null $user_agent
 * @property Carbon $decided_at
 */
class ConsentRecord extends Model
{
    protected $table = 'gdpr_consent_records';

    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'analytics_allowed' => 'boolean',
            'marketing_allowed' => 'boolean',
            'decided_at' => 'datetime',
        ];
    }
}
