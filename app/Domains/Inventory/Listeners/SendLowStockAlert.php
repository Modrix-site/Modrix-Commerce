<?php

namespace App\Domains\Inventory\Listeners;

use App\Core\Settings\SettingsRepository;
use App\Core\Settings\StoreSettings;
use App\Domains\Inventory\Events\InventoryLow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

/**
 * Emails the store when a variant crosses its low-stock threshold.
 * At most one alert per variant per day, so retries and bursts stay quiet.
 */
final class SendLowStockAlert implements ShouldQueue
{
    public function __construct(
        private readonly StoreSettings $store,
        private readonly SettingsRepository $settings,
    ) {}

    public function handle(InventoryLow $event): void
    {
        $email = $this->store->toArray()['store_email'];

        if ($email === null || ! (bool) $this->settings->get('inventory.low_stock_alerts', true)) {
            return;
        }

        if (! Cache::add("low-stock-alert:{$event->variantId}", true, now()->addDay())) {
            return;
        }

        Mail::raw(
            "Stock for {$event->sku} is down to {$event->onHand} (alert threshold: {$event->threshold}).",
            fn ($message) => $message->to($email)->subject("Low stock: {$event->sku}"),
        );
    }
}
