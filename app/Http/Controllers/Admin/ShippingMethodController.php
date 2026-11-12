<?php

namespace App\Http\Controllers\Admin;

use App\Core\Audit\AuditLogger;
use App\Core\Settings\Countries;
use App\Core\Settings\StoreSettings;
use App\Domains\Shipping\DeliveryArea;
use App\Domains\Shipping\Models\ShippingMethod;
use App\Domains\Shipping\ShippingLocationAudit;
use App\Domains\Shipping\ShippingRegions;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ShippingMethodController extends Controller
{
    public function index(StoreSettings $store): Response
    {
        $methods = ShippingMethod::query()->orderBy('position')->orderBy('id')->get();

        return Inertia::render('admin/shipping/index', [
            'methods' => $methods->map(fn (ShippingMethod $m) => [
                ...$m->only(['id', 'code', 'name', 'description', 'rate', 'currency', 'free_over', 'is_active', 'position', 'regions_only', 'delivery_estimate']),
                'countries' => $m->countries ?? [],
                'regional_rates' => $m->regional_rates ?? [],
            ]),
            'countries' => Countries::options(),
            'currency' => $store->currency(),
            'deliveryArea' => app(DeliveryArea::class)->label(),
            'locationWarnings' => app(ShippingLocationAudit::class)->warnings($methods),
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
            'regions_only' => $request->boolean('regions_only'),
        ]);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:64', Rule::unique('shipping_methods', 'code')->ignore($method?->id)],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'rate' => ['required', 'string', 'regex:/^\d{1,13}(\.\d{1,4})?$/'],
            'free_over' => ['nullable', 'string', 'regex:/^\d{1,13}(\.\d{1,4})?$/'],
            'countries' => ['nullable', 'array'],
            'countries.*' => ['string', 'size:2', 'distinct', Rule::in(Countries::codes())],
            'is_active' => ['boolean'],
            'delivery_estimate' => ['nullable', 'string', 'max:255'],
            'regions_only' => ['boolean'],
            'regional_rates' => ['sometimes', 'array', 'list', 'max:100'],
            'regional_rates.*' => ['array:country_code,region,rate,free_over'],
            'regional_rates.*.country_code' => ['required', Rule::in(Countries::codes())],
            'regional_rates.*.region' => ['required', 'string', 'max:120'],
            'regional_rates.*.rate' => ['required', 'string', 'regex:/^\d{1,13}(\.\d{1,4})?$/'],
            'regional_rates.*.free_over' => ['nullable', 'string', 'regex:/^\d{1,13}(\.\d{1,4})?$/'],
            'position' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ]);

        $data['regional_rates'] ??= $method->regional_rates ?? [];
        $seen = [];
        foreach ($data['regional_rates'] as $index => &$rule) {
            $rule['region'] = trim($rule['region']);
            $rule['free_over'] ??= null;
            $key = $rule['country_code'].':'.ShippingRegions::normalize($rule['region'], $rule['country_code']);
            if (isset($seen[$key])) {
                throw ValidationException::withMessages(["regional_rates.$index.region" => 'This state already has a rate.']);
            }
            if (! empty($data['countries']) && ! in_array($rule['country_code'], $data['countries'], true)) {
                throw ValidationException::withMessages(["regional_rates.$index.country_code" => 'Select this country in the delivery coverage first.']);
            }
            $seen[$key] = true;
        }
        unset($rule);
        if ($data['regions_only'] && empty($data['regional_rates'])) {
            throw ValidationException::withMessages(['regional_rates' => 'Add at least one state rate for restricted delivery.']);
        }
        $data['countries'] = empty($data['countries']) ? null : array_values(array_unique($data['countries']));
        $data['position'] ??= 0;

        if ($method !== null) {
            unset($data['code']); // codes are snapshotted on orders and stay stable
        }

        return $data;
    }
}
