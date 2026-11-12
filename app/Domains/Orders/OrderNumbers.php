<?php

namespace App\Domains\Orders;

use App\Core\Settings\SettingsRepository;
use App\Core\Settings\StoreSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class OrderNumbers
{
    public function __construct(private readonly SettingsRepository $settings, private readonly StoreSettings $store) {}

    /** @return array{order_prefix:string,order_padding:int,next_order_number:int} */
    public function toArray(): array
    {
        return ['order_prefix' => (string) $this->settings->get('orders.number_prefix', config('ecomodular.order_number_prefix', 'EM-')), 'order_padding' => (int) $this->settings->get('orders.number_padding', 6), 'next_order_number' => (int) DB::table('document_counters')->where('name', 'order')->value('next')];
    }

    public function format(int $sequence): string
    {
        $settings = $this->toArray();

        return str_replace('{year}', now($this->store->timezone())->format('Y'), $settings['order_prefix']).str_pad((string) $sequence, $settings['order_padding'], '0', STR_PAD_LEFT);
    }

    public function take(): string
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Order numbers require a transaction.');
        }
        $next = (int) DB::table('document_counters')->where('name', 'order')->lockForUpdate()->value('next');
        do {
            $number = $this->format($next++);
        } while (DB::table('orders')->where('number', $number)->exists());
        DB::table('document_counters')->where('name', 'order')->update(['next' => $next]);

        return $number;
    }

    /** @param array<string,mixed> $values */
    public function update(array $values): void
    {
        DB::transaction(function () use ($values) {
            $current = (int) DB::table('document_counters')->where('name', 'order')->lockForUpdate()->value('next');
            if (isset($values['next_order_number'])) {
                if ((int) $values['next_order_number'] < $current) {
                    throw ValidationException::withMessages(['next_order_number' => "The next order number cannot be lower than {$current}."]);
                }DB::table('document_counters')->where('name', 'order')->update(['next' => (int) $values['next_order_number']]);
            }
            $settings = [];
            if (array_key_exists('order_prefix', $values)) {
                $settings['orders.number_prefix'] = $values['order_prefix'] ?? '';
            }if (isset($values['order_padding'])) {
                $settings['orders.number_padding'] = (int) $values['order_padding'];
            }if ($settings !== []) {
                $this->settings->setMany($settings);
            }
        });
    }
}
