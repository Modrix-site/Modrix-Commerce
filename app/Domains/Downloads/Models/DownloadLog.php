<?php

namespace App\Domains\Downloads\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $download_grant_id
 * @property string $ip
 * @property string|null $user_agent
 */
class DownloadLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];
}
