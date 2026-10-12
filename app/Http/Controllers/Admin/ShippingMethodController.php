<?php

namespace App\Http\Controllers\Admin;

use App\Core\Audit\AuditLogger;
use App\Core\Settings\Countries;
use App\Core\Settings\StoreSettings;
use App\Domains\Shipping\Models\ShippingMethod;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ShippingMethodController extends Controller
{
    public function index(StoreSettings $store): Response
    {
        return Inertia::render('admin/shipping/index', [
            'methods' => ShippingMethod::query()->orderBy('position')->orderBy('id')->get()->map(fn (ShippingMethod $m) => [
                ...$m->only(['id', 'code', 'name', 'description', 'rate', 'currency', 'free_over', 'is_active', 'position']),
                'countries' => $m->countries ?? [],
            ]),
            'countries' => Countries::options(),
            'currency' => $store->currency(),
        ]);
    }

    public function store(Request $request, StoreSettings $store, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validated($request, null);
        $method = new ShippingMethod($data + ['currency' => $store->currency()]);
        $method->code = $data['code'];
        $method->save();
        $audit->record('shipping_method.created', $method, ['code' => $method->code]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Shipping method created.')]);

        return redirect()->route('admin.shipping-methods.index');
    }

    public function update(Request $request, ShippingMethod $method, StoreSettings $store, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validated($request, $method);
        $method->fill($data + ['currency' => $store->currency()])->save();
        $audit->record('shipping_method.updated', $method, ['code' => $method->code]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Shipping method saved.')]);

        return redirect()->route('admin.shipping-methods.index');
    }

    public function destroy(ShippingMethod $method, AuditLogger $audit): RedirectResponse
    {
        // Orders keep a snapshot of the method's code and name, so deleting is safe.
        $method->delete();
        $audit->record('shipping_method.deleted', $method, ['code' => $method->code]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Shipping method deleted.')]);

        return redirect()->route('admin.shipping-methods.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?ShippingMethod $method): array
    {
        $request->merge([
            'code' => Str::slug((string) ($request->input('code') ?: $request->input('name')), '_'),
            'is_active' => $request->boolean('is_active'),
        ]);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:64', Rule::unique('shipping_methods', 'code')->ignore($method?->id)],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'rate' => ['required', 'string', 'regex:/^\d{1,13}(\.\d{1,4})?$/'],
            'free_over' => ['nullable', 'string', 'regex:/^\d{1,13}(\.\d{1,4})?$/'],
            'countries' => ['nullable', 'array'],
            'countries.*' => ['string', 'size:2', Rule::in(Countries::codes())],
            'is_active' => ['boolean'],
            'position' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ]);

        $data['countries'] = empty($data['countries']) ? null : array_values(array_unique($data['countries']));
        $data['position'] ??= 0;

        if ($method !== null) {
            unset($data['code']); // codes are snapshotted on orders and stay stable
        }

        return $data;
    }
}
