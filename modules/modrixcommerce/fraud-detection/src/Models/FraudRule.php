<?php

namespace EcoModular\FraudDetection\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $type
 * @property string $field
 * @property string $operator
 * @property string $value
 * @property int $score_impact
 * @property bool $is_active
 * @property int $priority
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $creator
 */
class FraudRule extends Model
{
    public const TYPE_THRESHOLD = 'threshold';

    public const TYPE_PATTERN = 'pattern';

    public const TYPE_COUNTRY = 'country';

    public const TYPE_CUSTOM = 'custom';

    public const OPERATORS = ['gt', 'lt', 'eq', 'gte', 'lte', 'contains', 'in', 'not_in', 'matches'];

    public const FIELDS = [
        'order_total', 'email_domain', 'ip_address', 'country',
        'billing_country', 'shipping_country', 'payment_method',
        'items_count', 'customer_orders_count', 'email',
    ];

    protected $table = 'fraud_rules';

    protected $guarded = ['id'];

    protected $casts = [
        'score_impact' => 'integer',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Evaluate this rule against an order context.
     *
     * @param  array<string, mixed>  $context
     */
    public function evaluate(array $context): bool
    {
        $fieldValue = $context[$this->field] ?? null;
        if ($fieldValue === null) {
            return false;
        }

        $op = match ($this->operator) {
            'greater_than' => 'gt',
            'less_than' => 'lt',
            'equals' => 'eq',
            default => $this->operator,
        };

        return match ($op) {
            'gt' => (float) $fieldValue > (float) $this->value,
            'lt' => (float) $fieldValue < (float) $this->value,
            'eq' => (string) $fieldValue === (string) $this->value,
            'gte' => (float) $fieldValue >= (float) $this->value,
            'lte' => (float) $fieldValue <= (float) $this->value,
            'contains' => str_contains(strtolower((string) $fieldValue), strtolower($this->value)),
            'in' => in_array(strtolower((string) $fieldValue), array_map('strtolower', json_decode($this->value, true) ?? []), true),
            'not_in' => ! in_array(strtolower((string) $fieldValue), array_map('strtolower', json_decode($this->value, true) ?? []), true),
            'matches' => (bool) preg_match('/' . $this->value . '/i', (string) $fieldValue),
            default => false,
        };
    }
}
