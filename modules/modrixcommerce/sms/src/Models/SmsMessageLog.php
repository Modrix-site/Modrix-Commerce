<?php

namespace ModrixCommerce\Sms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A text the store sent or tried to send. Verification codes are never
 * stored in the body (it is replaced with a redacted copy).
 *
 * @property int $id
 * @property int|null $user_id
 * @property int|null $order_id
 * @property string|null $event
 * @property string $purpose
 * @property string $to
 * @property string $body
 * @property string $provider
 * @property string $status
 * @property string|null $provider_message_id
 * @property string|null $error
 * @property Carbon|null $created_at
 */
class SmsMessageLog extends Model
{
    protected $table = 'sms_messages';

    protected $guarded = ['id'];
}
