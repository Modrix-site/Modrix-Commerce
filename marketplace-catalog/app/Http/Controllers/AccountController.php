<?php

namespace Marketplace\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Marketplace\Models\License;
use Marketplace\Models\Order;
use Marketplace\Services\Catalog;
use Marketplace\Services\Licensing;

/** The signed-in customer's licenses, connected stores and orders. */
class AccountController
{
    public function show(Request $request, Catalog $catalog): View
    {
        $user = $request->user();
        $selected = $catalog->find((string) $request->query('product', ''));

        return view('account.show', [
            'user' => $user,
            'licenses' => License::query()->where('user_id', $user->id)->with(['package', 'activations' => fn ($q) => $q->where('active', true)])->latest()->get(),
            'orders' => Order::query()->where('user_id', $user->id)->with('package')->latest()->limit(50)->get(),
            'selected' => $selected,
            'free' => $catalog->published()->filter(fn ($p) => ! $p['premium'])->sortBy('name')->values(),
        ]);
    }

    /** A license for a free package, or for a paid one already bought. */
    public function license(Request $request, Catalog $catalog, Licensing $licensing): RedirectResponse
    {
        $request->validate(['product' => ['required', 'string']]);
        $product = $catalog->find((string) $request->input('product'));

        if ($product === null) {
            throw ValidationException::withMessages(['product' => 'Choose a package from the marketplace.']);
        }

        $owned = $catalog->owns($request->user(), $product['model']);

        if ($product['premium'] && ! $owned) {
            throw ValidationException::withMessages(['product' => 'Buy this package first; its license key is issued with the purchase.']);
        }

        $licensing->issue($request->user(), $product['model'], $product['premium'] ? 'paid' : 'free');

        return redirect('/account')->with('notice', 'License ready for '.$product['name'].'.');
    }

    /** Frees a key from its store so it can be activated on another one. */
    public function release(Request $request): RedirectResponse
    {
        $request->validate(['license_id' => ['required', 'integer']]);
        $license = License::query()->findOrFail((int) $request->input('license_id'));

        abort_unless($license->user_id === $request->user()->id, 403);

        $license->activations()->where('active', true)->update(['active' => false, 'deactivated_at' => now()]);

        return redirect('/account')->with('notice', 'The store was disconnected. You can activate the key on another store.');
    }
}
