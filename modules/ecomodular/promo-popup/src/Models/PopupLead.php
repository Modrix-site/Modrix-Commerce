<?php

namespace EcoModular\PromoPopup\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A lead captured by the marketing pop-up: the email, the consent given, the
 * page it was answered on and the coupon promised. No IP address is stored.
 *
 * @property int $id
 * @property string $email
 * @property string|null $coupon_code
 * @property string|null $page
 * @property string $consent_text
 * @property Carbon $consented_at
 */
class PopupLead extends Model
{
    protected $table = 'marketing_popup_leads';

    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['consented_at' => 'datetime'];
    }
}
