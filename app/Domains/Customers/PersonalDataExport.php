<?php

namespace App\Domains\Customers;

use App\Domains\Customers\Models\Customer;
use App\Domains\Customers\Models\CustomerAddress;
use App\Domains\Marketplace\Models\License;
use App\Domains\Marketplace\Models\MarketplaceOrder;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderAddress;
use App\Domains\Orders\Models\OrderItem;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * The personal data a store holds about a signed-in customer, as one JSON
 * document (access requests, GDPR art. 15/20). Modules add their own data
 * with contribute(); a failing contributor is reported and skipped.
 * Secrets (password hashes, 2FA secrets, full license keys) are never included.
 */
final class PersonalDataExport
{
    /** @var array<string, Closure(User): mixed> */
    private array $contributors = [];

    /**
     * @param  Closure(User): mixed  $data
     */
    public function contribute(string $key, Closure $data): void
    {
        $this->contributors[$key] = $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function for(User $user): array
    {
        $customer = Customer::query()->where('user_id', $user->id)->first();
        $orders = Order::query()->with(['items', 'addresses'])
            ->where(fn ($q) => $q->where('user_id', $user->id)->when($customer, fn ($w) => $w->orWhere('customer_id', $customer?->id)))
            ->orderBy('placed_at')->get();

        $export = [
            'generated_at' => now()->toIso8601String(),
            'account' => [
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            'customer' => $customer === null ? null : [
                'email' => $customer->email,
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'phone' => $customer->phone,
                'accepts_marketing' => $customer->accepts_marketing,
                'addresses' => $customer->addresses()->get()->map(fn (CustomerAddress $a) => $a->only([
                    'first_name', 'last_name', 'company', 'line1', 'line2', 'city', 'region', 'postal_code', 'country_code', 'phone',
                ]))->all(),
            ],
            'orders' => $orders->map(fn (Order $o) => [
                'number' => $o->number,
                'placed_at' => $o->placed_at->toIso8601String(),
                'status' => $o->status->value,
                'payment_status' => $o->payment_status->value,
                'email' => $o->email,
                'phone' => $o->phone,
                'currency' => $o->currency,
                'grand_total' => $o->grand_total,
                'customer_note' => $o->customer_note,
                'items' => $o->items->map(fn (OrderItem $i) => $i->only(['sku', 'product_name', 'variant_name', 'quantity', 'unit_price', 'total']))->all(),
                'addresses' => $o->addresses->map(fn (OrderAddress $a) => $a->only(['type', 'first_name', 'last_name', 'company', 'line1', 'line2', 'city', 'region', 'postal_code', 'country_code', 'phone']))->all(),
            ])->all(),
        ];

        if (Schema::hasTable('licenses')) {
            $export['marketplace'] = [
                'licenses' => License::query()->with('product:id,name')->where('user_id', $user->id)->get()->map(fn (License $l) => [
                    'product' => $l->product->name,
                    'key_ending' => $l->key_hint,
                    'status' => $l->status,
                    'updates_until' => $l->updates_until?->toDateString(),
                ])->all(),
                'orders' => MarketplaceOrder::query()->where('user_id', $user->id)->get()->map(fn (MarketplaceOrder $o) => [
                    'number' => $o->number, 'status' => $o->status, 'total' => $o->total, 'currency' => $o->currency, 'created_at' => $o->created_at?->toIso8601String(),
                ])->all(),
            ];
        }

        foreach ($this->contributors as $key => $contributor) {
            try {
                $export['extensions'][$key] = $contributor($user);
            } catch (Throwable $e) {
                Log::error("Personal data contributor {$key} failed.", ['exception' => $e]);
                $export['extensions'][$key] = ['error' => 'This data could not be collected; contact the store.'];
            }
        }

        return $export;
    }
}
