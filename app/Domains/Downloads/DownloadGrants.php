<?php

namespace App\Domains\Downloads;

use App\Domains\Catalog\Models\Product;
use App\Domains\Downloads\Models\DownloadGrant;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Download links for the digital products of paid orders.
 */
final class DownloadGrants
{
    public function __construct(private readonly DownloadSettings $settings) {}

    /** Payment states in which links work. */
    public const PAID = [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded];

    /**
     * Creates any missing links for a fully paid order: one per digital order
     * item and product file. Idempotent; returns how many were created.
     */
    public function issueFor(Order $order): int
    {
        if (! in_array($order->payment_status, self::PAID, true)) {
            return 0;
        }

        $limit = $this->settings->limit();
        $days = $this->settings->expiryDays();
        $created = 0;

        foreach ($this->digitalItems($order) as [$item, $product]) {
            foreach ($product->files as $file) {
                $grant = DownloadGrant::query()->firstOrCreate(
                    ['order_item_id' => $item->id, 'product_file_id' => $file->id],
                    [
                        'order_id' => $order->id,
                        'token' => Str::random(40),
                        'max_downloads' => $limit > 0 ? $limit : null,
                        'expires_at' => $days > 0 ? now()->addDays($days) : null,
                    ],
                );
                $created += $grant->wasRecentlyCreated ? 1 : 0;
            }
        }

        return $created;
    }

    /**
     * An order's download links for pages and emails. A link that no longer
     * works has no url.
     *
     * @return list<array{id: int, file: string, product: string, url: string|null, downloads: int, max_downloads: int|null, expires_at: string|null, usable: bool, last_downloaded_at: string|null}>
     */
    public function linksFor(Order $order): array
    {
        $now = now();
        $paid = in_array($order->payment_status, self::PAID, true);

        return array_values(DownloadGrant::query()->where('order_id', $order->id)
            ->with(['file', 'item'])->withMax('logs', 'created_at')->orderBy('id')->get()
            ->map(function (DownloadGrant $grant) use ($now, $paid) {
                $usable = $paid && $grant->file !== null && $grant->isUsable($now);
                $last = $grant->getAttribute('logs_max_created_at');

                return [
                    'id' => $grant->id,
                    'file' => $grant->file->name ?? 'File no longer available',
                    'product' => $grant->item->product_name,
                    'url' => $usable ? route('storefront.downloads.show', $grant->token) : null,
                    'downloads' => $grant->downloads,
                    'max_downloads' => $grant->max_downloads,
                    'expires_at' => $grant->expires_at?->toIso8601String(),
                    'usable' => $usable,
                    'last_downloaded_at' => $last === null ? null : Carbon::parse($last)->toIso8601String(),
                ];
            })->all());
    }

    /** Whether every item of the order is a digital product. */
    public function isDigitalOnly(Order $order): bool
    {
        $items = $order->items()->get();

        return $items->isNotEmpty() && count($this->digitalItems($order)) === $items->count();
    }

    /** Stops all of an order's links (cancelled or fully refunded). */
    public function revokeFor(Order $order): void
    {
        DB::table('download_grants')->where('order_id', $order->id)->whereNull('revoked_at')->update(['revoked_at' => now(), 'updated_at' => now()]);
    }

    /**
     * @return list<array{0: OrderItem, 1: Product}>
     */
    private function digitalItems(Order $order): array
    {
        $items = $order->items()->whereNotNull('product_id')->get();
        $products = Product::withTrashed()->with('files')->whereIn('id', $items->pluck('product_id'))->get()->keyBy('id');
        $digital = [];

        foreach ($items as $item) {
            $product = $products->get($item->product_id);

            if ($product instanceof Product && $product->is_digital) {
                $digital[] = [$item, $product];
            }
        }

        return $digital;
    }
}
