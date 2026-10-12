<?php

namespace App\Http\Controllers\Admin;

use App\Core\Audit\AuditLogger;
use App\Core\Settings\Countries;
use App\Domains\Tax\Models\TaxRateRow;
use App\Domains\Tax\TaxSettings;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TaxController extends Controller
{
    public function index(TaxSettings $settings): Response
    {
        return Inertia::render('admin/taxes/index', [
            'rates' => TaxRateRow::query()->orderBy('country_code')->orderBy('region')->get()->map(fn (TaxRateRow $r) => [
                ...$r->only(['id', 'name', 'country_code', 'region', 'rate', 'is_active']),
                'country_name' => Countries::name($r->country_code),
            ]),
            'settings' => [
                'prices_include_tax' => $settings->pricesIncludeTax(),
                'shipping_taxable' => $settings->taxShipping(),
            ],
            'countries' => Countries::options(),
        ]);
    }

    public function updateSettings(Request $request, TaxSettings $settings, AuditLogger $audit): RedirectResponse
    {
        $settings->update($request->boolean('prices_include_tax'), $request->boolean('shipping_taxable'));
        $audit->record('tax.settings_updated', null, [
            'prices_include_tax' => $request->boolean('prices_include_tax'),
            'shipping_taxable' => $request->boolean('shipping_taxable'),
        ]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tax settings saved.')]);

        return redirect()->route('admin.tax-rates.index');
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $rate = TaxRateRow::query()->create($this->validated($request));
        $audit->record('tax_rate.created', $rate, $rate->only(['country_code', 'region', 'rate']));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tax rate added.')]);

        return redirect()->route('admin.tax-rates.index');
    }

    public function update(Request $request, TaxRateRow $rate, AuditLogger $audit): RedirectResponse
    {
        $rate->fill($this->validated($request))->save();
        $audit->record('tax_rate.updated', $rate, $rate->only(['country_code', 'region', 'rate']));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tax rate saved.')]);

        return redirect()->route('admin.tax-rates.index');
    }

    public function destroy(TaxRateRow $rate, AuditLogger $audit): RedirectResponse
    {
        $audit->record('tax_rate.deleted', $rate, $rate->only(['country_code', 'region', 'rate']));
        $rate->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tax rate removed.')]);

        return redirect()->route('admin.tax-rates.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $request->merge(['is_active' => $request->boolean('is_active', true)]);

        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'country_code' => ['required', 'string', 'size:2', Rule::in(Countries::codes())],
            'region' => ['nullable', 'string', 'max:120'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100', 'regex:/^\d{1,3}(\.\d{1,4})?$/'],
            'is_active' => ['boolean'],
        ]);
    }
}
