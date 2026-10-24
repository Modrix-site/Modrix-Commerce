<?php

namespace Marketplace\Models;

use Illuminate\Database\Eloquent\Model;

/** A processed payment-provider event (each is handled once). */
class WebhookEvent extends Model
{
    protected $fillable = ['provider', 'event_id', 'type', 'processed_at'];

    protected function casts(): array
    {
        return ['processed_at' => 'datetime'];
    }
}
