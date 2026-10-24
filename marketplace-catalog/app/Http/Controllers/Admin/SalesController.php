<?php

namespace Marketplace\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Marketplace\Models\License;
use Marketplace\Models\Order;

/** Licenses (with their connected stores) and orders. */
class SalesController
{
    public function licenses(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        return view('admin.licenses', [
            'q' => $q,
            'licenses' => License::query()
                ->with(['user', 'package', 'activations' => fn ($a) => $a->where('active', true)])
                ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                    ->where('key', 'like', "%{$q}%")
                    ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$q}%"))
                    ->orWhereHas('package', fn ($p) => $p->where('name', 'like', "%{$q}%")->orWhere('identifier', 'like', "%{$q}%"))
                    ->orWhereHas('activations', fn ($a) => $a->where('domain', 'like', "%{$q}%"))))
                ->latest()->paginate(50)->withQueryString(),
        ]);
    }

    public function release(License $license): RedirectResponse
    {
        $license->activations()->where('active', true)->update(['active' => false, 'deactivated_at' => now()]);

        return back()->with('notice', 'Store disconnected from '.$license->key.'.');
    }

    public function revoke(License $license): RedirectResponse
    {
        $license->forceFill(['status' => 'revoked'])->save();
        $license->activations()->where('active', true)->update(['active' => false, 'deactivated_at' => now()]);

        return back()->with('notice', 'License '.$license->key.' revoked.');
    }

    public function orders(): View
    {
        return view('admin.orders', ['orders' => Order::query()->with(['user', 'package', 'license'])->latest()->paginate(50)]);
    }
}
