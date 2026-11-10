<?php

namespace EcoModular\FraudDetection\Models;

use App\Domains\Customers\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $customer_id
 * @property int $trust_score
 * @property int $total_orders
 * @property int $flagged_orders
 * @property int $rejected_orders
 * @property int $approved_orders
 * @property string $total_revenue
 * @property bool $is_repeat_offender
 * @property Carbon|null $last_order_at
 * @property array<int, array<string, mixed>>|null $risk_history
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Customer $customer
 */
class FraudCustomerProfile extends Model
{
    protected $table = 'fraud_customer_profiles';

    protected $guarded = ['id'];

    protected $casts = [
        'trust_score' => 'integer',
        'total_orders' => 'integer',
        'flagged_orders' => 'integer',
        'rejected_orders' => 'integer',
        'approved_orders' => 'integer',
        'is_repeat_offender' => 'boolean',
        'last_order_at' => 'datetime',
        'risk_history' => 'array',
    ];

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function recalculateTrustScore(): void
    {
        if ($this->total_orders === 0) {
            $this->trust_score = 50;
            return;
        }

        $approvalRate = $this->total_orders > 0
            ? ($this->approved_orders / $this->total_orders) * 100
            : 50;

        $rejectionPenalty = min($this->rejected_orders * 15, 50);
        $flagPenalty = min($this->flagged_orders * 5, 25);

        $score = (int) round($approvalRate - $rejectionPenalty - $flagPenalty);
        $this->trust_score = max(0, min(100, $score));
        $this->is_repeat_offender = $this->rejected_orders >= 2;
    }

    /**
     * @return string
     */
    public function trustLevel(): string
    {
        if ($this->trust_score >= 70) {
            return 'trusted';
        }
        if ($this->trust_score >= 40) {
            return 'neutral';
        }
        return 'suspicious';
    }
}
