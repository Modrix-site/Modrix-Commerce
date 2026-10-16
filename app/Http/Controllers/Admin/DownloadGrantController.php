<?php

namespace App\Http\Controllers\Admin;

use App\Core\Audit\AuditLogger;
use App\Domains\Downloads\Models\DownloadGrant;
use App\Domains\Orders\Models\Order;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Order page → Downloads: help a customer whose link ran out.
 */
class DownloadGrantController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function reset(Order $order, DownloadGrant $grant): RedirectResponse
    {
        $this->ensureBelongs($order, $grant);
        $grant->update(['downloads' => 0]);

        return $this->done('download.reset', $order, $grant, __('Download count reset.'));
    }

    public function extend(Order $order, DownloadGrant $grant): RedirectResponse
    {
        $this->ensureBelongs($order, $grant);
        $from = $grant->expires_at !== null && $grant->expires_at->isFuture() ? $grant->expires_at : now();
        $grant->update(['expires_at' => $from->copy()->addDays(30)]);

        return $this->done('download.extended', $order, $grant, __('Link extended by 30 days.'));
    }

    public function revoke(Order $order, DownloadGrant $grant): RedirectResponse
    {
        $this->ensureBelongs($order, $grant);
        $grant->update(['revoked_at' => now()]);

        return $this->done('download.revoked', $order, $grant, __('Download link revoked.'));
    }

    private function ensureBelongs(Order $order, DownloadGrant $grant): void
    {
        abort_unless($grant->order_id === $order->id, 404);
    }

    private function done(string $action, Order $order, DownloadGrant $grant, string $message): RedirectResponse
    {
        $this->audit->record($action, $order, ['number' => $order->number, 'grant_id' => $grant->id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
