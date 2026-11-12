<?php

namespace App\Http\Controllers\Admin;

use App\Core\Audit\AuditLogger;
use App\Domains\Customers\Models\Customer;
use App\Domains\Customers\Models\CustomerAddress;
use App\Domains\Orders\Models\Order;
use App\Domains\Pricing\Money;
use App\Http\Controllers\Controller;
use App\Http\Presenters\OrderPresenter;
use App\Support\Like;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:120']]);

        $customers = Customer::query()
            ->withCount('orders')
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($q) use ($search) {
                    Like::where($q, 'email', $search);
                    Like::orWhere($q, 'first_name', $search);
                    Like::orWhere($q, 'last_name', $search);
                });
            })
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Customer $c) => [
                'id' => $c->id,
                'name' => $c->fullName(),
                'email' => $c->email,
                'registered' => $c->user_id !== null,
                'orders_count' => $c->getAttribute('orders_count'),
                'created_at' => $c->created_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/customers/index', [
            'customers' => $customers,
            'filters' => ['search' => $filters['search'] ?? ''],
        ]);
    }

    public function show(Request $request, Customer $customer, OrderPresenter $presenter): Response
    {
        $orders = $customer->orders()->latest('placed_at')->limit(50)->get();
        $spent = [];

        foreach ($orders as $order) {
            if ($order->status->value !== 'cancelled') {
                $spent[$order->currency] = ($spent[$order->currency] ?? Money::zero($order->currency))
                    ->plus($order->money('paid_total')->minus($order->money('refunded_total')));
            }
        }

        return Inertia::render('admin/customers/show', [
            'customer' => [
                'id' => $customer->id,
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'name' => $customer->fullName(),
                'email' => $customer->email,
                'phone' => $customer->phone,
                'accepts_marketing' => $customer->accepts_marketing,
                'registered' => $customer->user_id !== null,
                'created_at' => $customer->created_at?->toIso8601String(),
            ],
            'addresses' => $customer->addresses()->get()->map(fn (CustomerAddress $a) => $a->toPresentable()),
            'orders' => $orders->map(fn (Order $o) => $presenter->summary($o)),
            'netSpent' => array_values($spent),
            'canManage' => $request->user()?->can('customers.manage') ?? false,
        ]);
    }

    public function update(Request $request, Customer $customer, AuditLogger $audit): RedirectResponse
    {
        $request->merge(['accepts_marketing' => $request->boolean('accepts_marketing')]);
        $data = $request->validate([
            'first_name' => ['nullable', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'accepts_marketing' => ['boolean'],
        ]);

        // Email is tied to the login account and order history, so it is not editable here.
        $customer->fill($data)->save();
        $audit->record('customer.updated', $customer, ['fields' => array_keys($customer->getChanges())]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Customer updated.')]);

        return redirect()->route('admin.customers.show', $customer);
    }
}
