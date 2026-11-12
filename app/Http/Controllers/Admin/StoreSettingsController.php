<?php

namespace App\Http\Controllers\Admin;

use App\Core\Audit\AuditLogger;
use App\Core\Localization\Locales;
use App\Core\Settings\Countries;
use App\Core\Settings\Currencies;
use App\Core\Settings\SettingsRepository;
use App\Core\Settings\StoreSettings;
use App\Domains\Downloads\DownloadSettings;
use App\Domains\Orders\Documents\DocumentPreferences;
use App\Domains\Shipping\DeliveryArea;
use App\Http\Controllers\Controller;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StoreSettingsController extends Controller
{
    public function edit(StoreSettings $store, SettingsRepository $repository): Response
    {
        return Inertia::render('admin/settings/store', [
            'settings' => $store->toArray(),
            'downloads' => ['limit' => app(DownloadSettings::class)->limit(), 'expiry_days' => app(DownloadSettings::class)->expiryDays()],
            'documents' => app(DocumentPreferences::class)->props(),
            'profile' => array_combine(['phone', 'address', 'country', 'region', 'city', 'business_hours', 'tagline', 'meta_title', 'meta_description'], array_map(fn ($key) => $repository->get('store.'.$key, ''), ['phone', 'address', 'country', 'region', 'city', 'business_hours', 'tagline', 'meta_title', 'meta_description'])),
            'delivery' => app(DeliveryArea::class)->toArray(),
            'displayCurrencies' => $repository->get('store.display_currencies', []),
            'countries' => Countries::options(),
            'currencies' => collect(Currencies::ALL)->map(fn ($c, $code) => ['code' => $code, 'name' => $c['name']])->values(),
            'timezones' => DateTimeZone::listIdentifiers(),
            'weightUnits' => StoreSettings::UNITS_WEIGHT,
            'dimensionUnits' => StoreSettings::UNITS_DIMENSION,
        ]);
    }

    public function update(Request $request, StoreSettings $store, AuditLogger $audit, SettingsRepository $repository, Locales $locales): RedirectResponse
    {
        /** @var array{store_name: string, store_email: string|null, default_currency: string, timezone: string, weight_unit: string, dimension_unit: string, profile?: array<string, string|null>, delivery?: array{mode: string, countries?: list<string>}, display_currencies?: list<array{code: string, rate: string}>, language_default?: string, language_additional?: list<string>} $data */
        $data = $request->validate([
            'profile' => ['sometimes', 'array:phone,address,country,region,city,business_hours,tagline,meta_title,meta_description'],
            'profile.phone' => ['nullable', 'string', 'max:50'],
            'profile.address' => ['nullable', 'string', 'max:1500'],
            'profile.country' => [Rule::requiredIf(in_array($request->input('delivery.mode'), ['store_country', 'store_region', 'store_city'], true)), 'nullable', Rule::in(Countries::codes())],
            'profile.region' => ['required_if:delivery.mode,store_region', 'nullable', 'string', 'max:120'],
            'profile.city' => ['required_if:delivery.mode,store_city', 'nullable', 'string', 'max:120'],
            'delivery' => ['sometimes', 'array:mode,countries'],
            'delivery.mode' => ['required_with:delivery', Rule::in(DeliveryArea::MODES)],
            'delivery.countries' => ['required_if:delivery.mode,selected_countries', 'array', 'list', 'max:250'],
            'delivery.countries.*' => ['required', 'string', 'distinct', Rule::in(Countries::codes())],
            'profile.business_hours' => ['nullable', 'string', 'max:500'],
            'profile.tagline' => ['nullable', 'string', 'max:180'],
            'profile.meta_title' => ['nullable', 'string', 'max:180'],
            'profile.meta_description' => ['nullable', 'string', 'max:500'],
            'display_currencies' => ['sometimes', 'array', 'list', 'max:50'],
            'display_currencies.*' => ['array:code,rate'],
            'display_currencies.*.code' => ['required', 'distinct', Rule::in(Currencies::codes())],
            'display_currencies.*.rate' => ['required', 'string', 'regex:/^\d{1,9}(\.\d{1,8})?$/', 'numeric', 'gt:0'],
            'language_default' => ['sometimes', 'required', Rule::in(array_keys(Locales::AVAILABLE))],
            'language_additional' => ['sometimes', 'array'],
            'language_additional.*' => [Rule::in(array_keys(Locales::AVAILABLE))],
            'store_name' => ['required', 'string', 'max:120'],
            'store_email' => ['nullable', 'string', 'email', 'max:255'],
            'default_currency' => ['required', 'string', Rule::in(Currencies::codes())],
            'timezone' => ['required', 'string', Rule::in(DateTimeZone::listIdentifiers())],
            'weight_unit' => ['required', Rule::in(StoreSettings::UNITS_WEIGHT)],
            'dimension_unit' => ['required', Rule::in(StoreSettings::UNITS_DIMENSION)],
        ]);

        try {
            DB::transaction(function () use ($request, $store, $data, $repository, $locales, $audit) {
                if ($request->has('invoice_prefix')) {
                    app(DocumentPreferences::class)->save($request);
                }
                $before = $store->toArray();
                if ($before['default_currency'] !== $data['default_currency'] && ! array_key_exists('display_currencies', $data)) {
                    $data['display_currencies'] = [];
                }
                $store->update($data);
                $extraChanges = [];
                foreach ($data['profile'] ?? [] as $key => $value) {
                    $beforeValue = $repository->get('store.'.$key);
                    $repository->set('store.'.$key, $value);
                    if ($beforeValue !== $value) {
                        $extraChanges[$key] = ['from' => $beforeValue, 'to' => $value];
                    }
                }
                if (isset($data['delivery'])) {
                    $delivery = $data['delivery'];
                    $countries = $delivery['mode'] === 'selected_countries' ? ($delivery['countries'] ?? []) : [];
                    $extraChanges['delivery'] = ['from' => app(DeliveryArea::class)->toArray(), 'to' => ['mode' => $delivery['mode'], 'countries' => $countries]];
                    $repository->setMany(['store.delivery_mode' => $delivery['mode'], 'store.delivery_countries' => $countries]);
                }
                if (array_key_exists('display_currencies', $data)) {
                    $extraChanges['display_currencies'] = ['from' => $repository->get('store.display_currencies', []), 'to' => $data['display_currencies']];
                    $repository->set('store.display_currencies', $data['display_currencies']);
                }
                if (isset($data['language_default'])) {
                    $extraChanges['languages'] = ['from' => $locales->enabled(), 'to' => [$data['language_default'], ...($data['language_additional'] ?? [])]];
                    $locales->update($data['language_default'], $data['language_additional'] ?? []);
                }
                $after = $store->toArray();

                $changes = $extraChanges;
                foreach ($after as $key => $value) {
                    if ($before[$key] !== $value) {
                        $changes[$key] = ['from' => $before[$key], 'to' => $value];
                    }
                }

                if ($changes !== []) {
                    $audit->record('settings.store_updated', null, ['changes' => $changes]);
                }

            });

        } finally {
            $repository->flush();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Store settings saved.')]);

        return redirect()->route('admin.settings.store.edit');
    }
}
