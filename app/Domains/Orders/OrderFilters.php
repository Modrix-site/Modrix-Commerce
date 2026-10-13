<?php

namespace App\Domains\Orders;

use App\Domains\Orders\Enums\DisplayStatus;
use App\Domains\Orders\Enums\FulfillmentStatus;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Orders\Models\Order;
use App\Support\Like;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * The order list filters, shared by the admin list and the CSV export so an
 * export contains exactly the orders on screen.
 */
final class OrderFilters
{
    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'display_status' => ['nullable', Rule::enum(DisplayStatus::class)],
            'ready' => ['nullable', 'boolean'],
            'payment_status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'fulfillment_status' => ['nullable', Rule::enum(FulfillmentStatus::class)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters  validated with rules()
     * @return Builder<Order>
     */
    public static function query(array $filters, string $timezone): Builder
    {
        return Order::query()
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($q) use ($search) {
                    Like::where($q, 'number', $search);
                    Like::orWhere($q, 'email', $search);
                });
            })
            ->when($filters['display_status'] ?? null, fn ($q, string $v) => $q->where('display_status', $v))
            ->when($filters['ready'] ?? false, fn ($q) => $q->readyToFulfil())
            ->when($filters['payment_status'] ?? null, fn ($q, string $v) => $q->where('payment_status', $v))
            ->when($filters['fulfillment_status'] ?? null, fn ($q, string $v) => $q->where('fulfillment_status', $v))
            // Date filters are interpreted in the store timezone.
            ->when($filters['from'] ?? null, fn ($q, string $v) => $q->where('placed_at', '>=', Carbon::parse($v, $timezone)->startOfDay()->utc()))
            ->when($filters['to'] ?? null, fn ($q, string $v) => $q->where('placed_at', '<=', Carbon::parse($v, $timezone)->endOfDay()->utc()))
            ->latest('placed_at')
            ->latest('id');
    }
}
