<?php

namespace App\Http\Controllers\Storefront;

use App\Core\Audit\AuditLogger;
use App\Core\Settings\Countries;
use App\Domains\Customers\AddressRules;
use App\Domains\Customers\CustomerProfiles;
use App\Domains\Customers\Models\CustomerAddress;
use App\Domains\Customers\PersonalDataExport;
use App\Domains\Notifications\CustomerNotifications;
use App\Domains\Orders\Models\Order;
use App\Http\Controllers\Controller;
use App\Http\Presenters\OrderPresenter;
use App\Models\User;
use Illuminate\Http\JsonResponse;
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

    public function show(Request $request, OrderPresenter $orders, CustomerNotifications $notifications): Response
    {
        $user = $this->user($request);
        $customer = $this->profiles->forUser($user);
        $placed = Order::query()->where('user_id', $user->id);

        return Inertia::render('storefront/account/index', [
            'customer' => [
                'name' => $customer->fullName(),
                'email' => $customer->email,
                'member_since' => $user->created_at?->toIso8601String(),
            ],
            'defaultAddress' => $customer->addresses()->where('is_default_shipping', true)->first()?->toPresentable(),
            'stats' => [
                'orders' => (clone $placed)->count(),
                'unread_notifications' => $notifications->unreadCount($user),
                'addresses' => $customer->addresses()->count(),
            ],
            'recentOrders' => (clone $placed)->latest('placed_at')->limit(3)->get()
                ->map(fn (Order $order) => $orders->customerSummary($order))
                ->values(),
        ]);
    }

    /**
     * Download everything the store holds about this customer (JSON).
     */
    public function exportData(Request $request, PersonalDataExport $export, AuditLogger $audit): JsonResponse
    {
        $user = $this->user($request);
        $audit->record('account.data_exported', $user, [], $user);

        return response()->json($export->for($user), 200, [
            'Content-Disposition' => 'attachment; filename="my-data-'.now()->format('Y-m-d').'.json"',
            'Cache-Control' => 'no-store',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
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
        Inertia::flash('toast', ['type' => 'success', 'message' => st('Address saved.')]);

        return redirect()->route('account.addresses');
    }

    public function updateAddress(Request $request, CustomerAddress $address): RedirectResponse
    {
        $customer = $this->profiles->forUser($this->user($request));
        abort_unless($address->customer_id === $customer->id, 404);

        $this->profiles->saveAddress($customer, $this->validated($request), $address);
        Inertia::flash('toast', ['type' => 'success', 'message' => st('Address updated.')]);

        return redirect()->route('account.addresses');
    }

    public function destroyAddress(Request $request, CustomerAddress $address): RedirectResponse
    {
        $customer = $this->profiles->forUser($this->user($request));
        abort_unless($address->customer_id === $customer->id, 404);

        $this->profiles->deleteAddress($address);
        Inertia::flash('toast', ['type' => 'success', 'message' => st('Address removed.')]);

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
