import { useState } from 'react';
import { FormField, NativeSelect } from '@/components/admin/form-field';
import RegionCombobox from '@/components/storefront/region-combobox';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { regionsFor } from '@/lib/regions';
import type { Country } from '@/types';

export type DeliveryAreaSettings = { mode: string; countries: string[] };

export function StoreLocationFields({
    profile,
    delivery,
    countries,
    errors,
}: {
    profile: Record<string, string | null>;
    delivery: DeliveryAreaSettings;
    countries: Country[];
    errors: Record<string, string>;
}) {
    const [country, setCountry] = useState(profile.country ?? '');
    const [region, setRegion] = useState(profile.region ?? '');
    const [mode, setMode] = useState(delivery.mode);
    const [selected, setSelected] = useState(delivery.countries);
    const [search, setSearch] = useState('');

    return (
        <div className="grid gap-6 border-t pt-6 sm:col-span-2">
            <div className="grid gap-4 sm:grid-cols-3">
                <div className="sm:col-span-3">
                    <h3 className="font-semibold">Store location</h3>
                    <p className="text-sm text-muted-foreground">
                        Your business location and the starting point for local
                        delivery restrictions.
                    </p>
                </div>
                <FormField
                    id="profile-country"
                    label="Store country"
                    error={errors['profile.country']}
                >
                    <NativeSelect
                        id="profile-country"
                        name="profile[country]"
                        value={country}
                        onChange={(e) => {
                            setCountry(e.target.value);
                            setRegion('');
                        }}
                    >
                        <option value="">Select country</option>
                        {countries.map((c) => (
                            <option key={c.code} value={c.code}>
                                {c.name}
                            </option>
                        ))}
                    </NativeSelect>
                </FormField>
                <FormField
                    id="profile-region"
                    label="State / province"
                    error={errors['profile.region']}
                >
                    <RegionCombobox
                        id="profile-region"
                        name="profile[region]"
                        value={region}
                        onChange={setRegion}
                        options={regionsFor(country)}
                    />
                </FormField>
                <FormField
                    id="profile-city"
                    label="City"
                    error={errors['profile.city']}
                >
                    <Input
                        id="profile-city"
                        name="profile[city]"
                        defaultValue={profile.city ?? ''}
                    />
                </FormField>
            </div>
            <div className="grid gap-4 rounded-lg border p-4">
                <FormField
                    id="delivery-mode"
                    label="Deliver to"
                    error={errors['delivery.mode']}
                    hint="Every shipping method and carrier must respect this delivery area. Configure prices and narrower region rules under Shipping."
                >
                    <NativeSelect
                        id="delivery-mode"
                        name="delivery[mode]"
                        value={mode}
                        onChange={(e) => setMode(e.target.value)}
                    >
                        <option value="shipping_methods">
                            Destinations configured in shipping methods
                        </option>
                        <option value="store_country">
                            Store country only
                        </option>
                        <option value="store_region">
                            Store state / province only
                        </option>
                        <option value="store_city">Store city only</option>
                        <option value="selected_countries">
                            Selected countries
                        </option>
                    </NativeSelect>
                </FormField>
                {mode === 'selected_countries' && (
                    <div className="grid gap-3">
                        <div className="flex flex-wrap items-center justify-between gap-2 text-sm">
                            <span>{selected.length} countries selected</span>
                            <div className="flex gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        setSelected(
                                            countries.map((c) => c.code),
                                        )
                                    }
                                >
                                    Select all
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => setSelected([])}
                                >
                                    Clear
                                </Button>
                            </div>
                        </div>
                        <Input
                            aria-label="Search delivery countries"
                            placeholder="Search countries..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                        <div className="grid max-h-52 gap-2 overflow-y-auto sm:grid-cols-3">
                            {countries
                                .filter((c) =>
                                    `${c.name} ${c.code}`
                                        .toLowerCase()
                                        .includes(search.toLowerCase()),
                                )
                                .map((c) => (
                                    <label
                                        key={c.code}
                                        className="flex items-center gap-2 text-sm"
                                    >
                                        <input
                                            type="checkbox"
                                            className="size-4 accent-primary"
                                            checked={selected.includes(c.code)}
                                            onChange={(event) =>
                                                setSelected((current) =>
                                                    event.target.checked
                                                        ? Array.from(
                                                              new Set([
                                                                  ...current,
                                                                  c.code,
                                                              ]),
                                                          )
                                                        : current.filter(
                                                              (code) =>
                                                                  code !==
                                                                  c.code,
                                                          ),
                                                )
                                            }
                                        />
                                        {c.name}
                                    </label>
                                ))}
                        </div>
                        {selected.map((code) => (
                            <input
                                key={code}
                                type="hidden"
                                name="delivery[countries][]"
                                value={code}
                            />
                        ))}
                        {Object.entries(errors)
                            .filter(([key]) =>
                                key.startsWith('delivery.countries'),
                            )
                            .map(([key, error]) => (
                                <p
                                    key={key}
                                    className="text-sm text-destructive"
                                >
                                    {error}
                                </p>
                            ))}
                    </div>
                )}
                <a
                    href="/admin/shipping-methods"
                    className="w-fit text-sm underline underline-offset-4"
                >
                    Manage shipping methods and location rates
                </a>
            </div>
        </div>
    );
}
