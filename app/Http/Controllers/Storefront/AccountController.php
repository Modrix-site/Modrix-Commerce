<?php

namespace App\Http\Controllers\Storefront;

use App\Core\Settings\Countries;
use App\Domains\Customers\AddressRules;
use App\Domains\Customers\CustomerProfiles;
use App\Domains\Customers\Models\CustomerAddress;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Customer self-service: overview and address book.
 * Every query is scoped to the signed-in user's own customer profile.
 */
class AccountController extends Controller
{
    public function __construct(private readonly CustomerProfiles $profiles) {}

    public function show(Request $request): Response
    {
        $customer = $this->profiles->forUser($this->user($request));

        return Inertia::render('storefront/account/index', [
            'customer' => [
                'name' => $customer->fullName(),
                'email' => $customer->email,
            ],
            'defaultAddress' => $customer->addresses()->where('is_default_shipping', true)->first()?->toPresentable(),
        ]);
    }

    public function addresses(Request $request): Response
    {
        $customer = $this->profiles->forUser($this->user($request));

        return Inertia::render('storefront/account/addresses', [
            'addresses' => $customer->addresses()->get()->map(fn (CustomerAddress $a) => $a->toPresentable()),
            'countries' => Countries::options(),
        ]);
    }

    public function storeAddress(Request $request): RedirectResponse
    {
        $customer = $this->profiles->forUser($this->user($request));
        $this->profiles->saveAddress($customer, $this->validated($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Address saved.')]);

        return redirect()->route('account.addresses');
    }

    public function updateAddress(Request $request, CustomerAddress $address): RedirectResponse
    {
        $customer = $this->profiles->forUser($this->user($request));
        abort_unless($address->customer_id === $customer->id, 404);

        $this->profiles->saveAddress($customer, $this->validated($request), $address);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Address updated.')]);

        return redirect()->route('account.addresses');
    }

    public function destroyAddress(Request $request, CustomerAddress $address): RedirectResponse
    {
        $customer = $this->profiles->forUser($this->user($request));
        abort_unless($address->customer_id === $customer->id, 404);

        $this->profiles->deleteAddress($address);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Address removed.')]);

        return redirect()->route('account.addresses');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $request->merge([
            'is_default_shipping' => $request->boolean('is_default_shipping'),
            'is_default_billing' => $request->boolean('is_default_billing'),
        ]);

        return $request->validate([
            ...AddressRules::rules(),
            'is_default_shipping' => ['boolean'],
            'is_default_billing' => ['boolean'],
        ]);
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
