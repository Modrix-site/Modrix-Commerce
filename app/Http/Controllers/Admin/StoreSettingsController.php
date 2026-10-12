<?php

namespace App\Http\Controllers\Admin;

use App\Core\Audit\AuditLogger;
use App\Core\Settings\Currencies;
use App\Core\Settings\StoreSettings;
use App\Http\Controllers\Controller;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StoreSettingsController extends Controller
{
    public function edit(StoreSettings $store): Response
    {
        return Inertia::render('admin/settings/store', [
            'settings' => $store->toArray(),
            'currencies' => collect(Currencies::ALL)->map(fn ($c, $code) => ['code' => $code, 'name' => $c['name']])->values(),
            'timezones' => DateTimeZone::listIdentifiers(),
            'weightUnits' => StoreSettings::UNITS_WEIGHT,
            'dimensionUnits' => StoreSettings::UNITS_DIMENSION,
        ]);
    }

    public function update(Request $request, StoreSettings $store, AuditLogger $audit): RedirectResponse
    {
        /** @var array{store_name: string, store_email: string|null, default_currency: string, timezone: string, weight_unit: string, dimension_unit: string} $data */
        $data = $request->validate([
            'store_name' => ['required', 'string', 'max:120'],
            'store_email' => ['nullable', 'string', 'email', 'max:255'],
            'default_currency' => ['required', 'string', Rule::in(Currencies::codes())],
            'timezone' => ['required', 'string', Rule::in(DateTimeZone::listIdentifiers())],
            'weight_unit' => ['required', Rule::in(StoreSettings::UNITS_WEIGHT)],
            'dimension_unit' => ['required', Rule::in(StoreSettings::UNITS_DIMENSION)],
        ]);

        $before = $store->toArray();
        $store->update($data);
        $after = $store->toArray();

        $changes = [];
        foreach ($after as $key => $value) {
            if ($before[$key] !== $value) {
                $changes[$key] = ['from' => $before[$key], 'to' => $value];
            }
        }

        if ($changes !== []) {
            $audit->record('settings.store_updated', null, ['changes' => $changes]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Store settings saved.')]);

        return redirect()->route('admin.settings.store.edit');
    }
}
