<?php

namespace EcoModular\FraudDetection\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $country_code
 * @property string $country_name
 * @property string $risk_level
 * @property int $score_modifier
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class FraudCountryRisk extends Model
{
    protected $table = 'fraud_country_risks';

    protected $guarded = ['id'];

    protected $casts = [
        'score_modifier' => 'integer',
        'is_active' => 'boolean',
    ];
}
