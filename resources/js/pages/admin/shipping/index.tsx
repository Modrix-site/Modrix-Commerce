import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import ShippingMethodController from '@/actions/App/Http/Controllers/Admin/ShippingMethodController';
import { EmptyState } from '@/components/admin/empty-state';
import { FormField } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { trimDecimal } from '@/lib/decimal';
import { regionsFor } from '@/lib/regions';
import { index } from '@/routes/admin/shipping-methods';
import RegionCombobox from '@/components/storefront/region-combobox';
import type { Country } from '@/types';

type RegionalRate = {
    country_code: string;
    region: string;
    rate: string;
    free_over: string | null;
};
const gccCountries = ['AE', 'SA', 'BH', 'KW', 'OM', 'QA'];
type Method = {
    regional_rates: RegionalRate[];
    regions_only: boolean;
    delivery_estimate: string | null;
    id: number;
    code: string;
    name: string;
    description: string | null;
    rate: string;
    currency: string;
    free_over: string | null;
    countries: string[];
    is_active: boolean;
    position: number;
};

export default function ShippingIndex({
    methods,
    countries,
    currency,
    deliveryArea,
    locationWarnings,
}: {
    methods: Method[];
    countries: Country[];
    currency: string;
    deliveryArea: string;
    locationWarnings: string[];
}) {
    const [editing, setEditing] = useState<number | 'new' | null>(null);

    return (
        <>
            <Head title="Shipping" />
            <AdminPage>
                <PageHeader
                    title="Shipping methods"
                    description={`Configure delivery coverage, regional prices and free-shipping thresholds in ${currency}.`}
                    actions={
                        editing === null && (
                            <Button onClick={() => setEditing('new')}>
                                Add method
                            </Button>
                        )
                    }
                />
                <div className="grid gap-2 rounded-lg border bg-muted/30 p-4 text-sm">
                    <p>
                        <strong>Store delivery area:</strong> {deliveryArea}
                    </p>
                    <p className="text-muted-foreground">
                        All methods below respect this area. Country and region
                        rules can narrow it further.
                    </p>
                    <Link
                        href="/admin/settings/store#regional"
                        className="w-fit underline underline-offset-4"
                    >
                        Change store location and delivery area
                    </Link>
                </div>
                {locationWarnings.length > 0 && (
                    <div
                        role="status"
                        className="grid gap-2 rounded-lg border p-4 text-sm"
                    >
                        <p className="font-semibold">
                            Check duplicate delivery locations
                        </p>
                        {locationWarnings.map((warning) => (
                            <p key={warning}>{warning}</p>
                        ))}
                    </div>
                )}
                {editing === 'new' && (
                    <MethodForm
                        countries={countries}
                        currency={currency}
                        onDone={() => setEditing(null)}
                    />
                )}
                {methods.length === 0 && editing === null ? (
                    <EmptyState
                        title="No shipping methods"
                        description="Customers cannot check out until at least one active method ships to their country."
                    />
                ) : (
                    <div className="grid gap-4">
                        {methods.map((m) =>
                            editing === m.id ? (
                                <MethodForm
                                    key={m.id}
                                    method={m}
                                    countries={countries}
                                    currency={currency}
                                    onDone={() => setEditing(null)}
                                />
                            ) : (
                                <Card key={m.id}>
                                    <CardHeader className="flex flex-row items-start justify-between gap-4">
                                        <div className="grid gap-1">
                                            <CardTitle className="flex items-center gap-2">
                                                {m.name}
                                                {!m.is_active && (
                                                    <Badge variant="outline">
                                                        Inactive
                                                    </Badge>
                                                )}
                                            </CardTitle>
                                            <p className="text-sm text-muted-foreground">
                                                {trimDecimal(m.rate)}{' '}
                                                {m.currency}
                                                {m.free_over !== null &&
                                                    ` · free over ${trimDecimal(m.free_over)} ${m.currency}`}
                                                {' · '}
                                                {m.regional_rates.length > 0 &&
                                                    `${m.regional_rates.length} state rates · `}
                                                {m.countries.length === 0
                                                    ? 'all permitted delivery countries'
                                                    : m.countries.join(', ')}
                                            </p>
                                        </div>
                                        <div className="flex gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => setEditing(m.id)}
                                            >
                                                Edit
                                            </Button>
                                            <Form
                                                {...ShippingMethodController.destroy.form(
                                                    m.id,
                                                )}
                                                onBefore={() =>
                                                    window.confirm(
                                                        `Delete ${m.name}?`,
                                                    )
                                                }
                                            >
                                                <Button
                                                    type="submit"
                                                    variant="ghost"
                                                    size="sm"
                                                >
                                                    Delete
                                                </Button>
                                            </Form>
                                        </div>
                                    </CardHeader>
                                </Card>
                            ),
                        )}
                    </div>
                )}
            </AdminPage>
        </>
    );
}

function MethodForm({
    method,
    countries,
    currency,
    onDone,
}: {
    method?: Method;
    countries: Country[];
    currency: string;
    onDone: () => void;
}) {
    const [regionalRates, setRegionalRates] = useState<RegionalRate[]>(
        method?.regional_rates ?? [],
    );
    const [countrySearch, setCountrySearch] = useState('');
    const [selected, setSelected] = useState<string[]>(method?.countries ?? []);
    const form = method
        ? ShippingMethodController.update.form(method.id)
        : ShippingMethodController.store.form();

    return (
        <Card>
            <CardHeader>
                <CardTitle>
                    {method ? `Edit ${method.name}` : 'New shipping method'}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <Form
                    {...form}
                    transform={(data) => ({
                        ...data,
                        regional_rates: regionalRates,
                    })}
                    onSuccess={onDone}
                    options={{ preserveScroll: true }}
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <FormField
                                    id="name"
                                    label="Name"
                                    error={errors.name}
                                >
                                    <Input
                                        id="name"
                                        name="name"
                                        defaultValue={method?.name}
                                        required
                                    />
                                </FormField>
                                <FormField
                                    id="description"
                                    label="Description (optional)"
                                    error={errors.description}
                                >
                                    <Input
                                        id="description"
                                        name="description"
                                        defaultValue={method?.description ?? ''}
                                    />
                                </FormField>
                                <FormField
                                    id="rate"
                                    label={`Default rate (${currency})`}
                                    error={errors.rate}
                                >
                                    <Input
                                        id="rate"
                                        name="rate"
                                        inputMode="decimal"
                                        defaultValue={trimDecimal(method?.rate)}
                                        required
                                    />
                                </FormField>
                                <FormField
                                    id="free_over"
                                    label={`Free when merchandise total is at least (${currency}, optional)`}
                                    error={errors.free_over}
                                >
                                    <Input
                                        id="free_over"
                                        name="free_over"
                                        inputMode="decimal"
                                        defaultValue={trimDecimal(
                                            method?.free_over,
                                        )}
                                    />
                                </FormField>
                                <FormField
                                    id="position"
                                    label="Sort position"
                                    error={errors.position}
                                >
                                    <Input
                                        id="position"
                                        name="position"
                                        type="number"
                                        min={0}
                                        defaultValue={method?.position ?? 0}
                                    />
                                </FormField>
                                <label className="flex items-center gap-2 self-end pb-2 text-sm">
                                    <Checkbox
                                        name="is_active"
                                        value="1"
                                        defaultChecked={
                                            method?.is_active ?? true
                                        }
                                    />
                                    Active
                                </label>
                            </div>
                            <div className="grid gap-3 rounded-lg border p-4">
                                <h3 className="font-semibold">
                                    Delivery coverage
                                </h3>
                                <p className="text-sm text-muted-foreground">
                                    Leave countries unselected to use the
                                    store's delivery area. Regional prices
                                    override the default rate.
                                </p>
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() =>
                                            setSelected(
                                                Array.from(
                                                    new Set([
                                                        ...selected,
                                                        ...gccCountries,
                                                    ]),
                                                ),
                                            )
                                        }
                                    >
                                        Select GCC countries
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={() => setSelected([])}
                                    >
                                        Use store delivery area
                                    </Button>
                                </div>
                                <Input
                                    aria-label="Search countries"
                                    placeholder="Search countries..."
                                    value={countrySearch}
                                    onChange={(e) =>
                                        setCountrySearch(e.target.value)
                                    }
                                />
                                <div className="grid max-h-44 gap-2 overflow-y-auto sm:grid-cols-3">
                                    {countries
                                        .filter((c) =>
                                            c.name
                                                .toLowerCase()
                                                .includes(
                                                    countrySearch.toLowerCase(),
                                                ),
                                        )
                                        .map((c) => (
                                            <label
                                                key={c.code}
                                                className="flex items-center gap-2 text-sm"
                                            >
                                                <Checkbox
                                                    checked={selected.includes(
                                                        c.code,
                                                    )}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        setSelected(
                                                            checked
                                                                ? [
                                                                      ...selected,
                                                                      c.code,
                                                                  ]
                                                                : selected.filter(
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
                                <p className="text-sm text-muted-foreground">
                                    {selected.length
                                        ? selected.join(', ')
                                        : 'All countries'}
                                </p>
                                {errors.countries && (
                                    <p className="text-sm text-destructive">
                                        {errors.countries}
                                    </p>
                                )}
                            </div>
                            <div className="grid gap-4 rounded-lg border p-4">
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <h3 className="font-semibold">
                                            Regional shipping prices
                                        </h3>
                                        <p className="text-sm text-muted-foreground">
                                            Each row replaces the default rate
                                            for that country and region. Use any
                                            worldwide destination. A blank free
                                            threshold inherits the method
                                            threshold.
                                        </p>
                                    </div>
                                </div>
                                <label className="flex items-center gap-2 text-sm">
                                    <Checkbox
                                        name="regions_only"
                                        value="1"
                                        defaultChecked={
                                            method?.regions_only ?? false
                                        }
                                    />
                                    Deliver only to the regions listed below
                                </label>
                                <p className="text-xs text-muted-foreground">
                                    Otherwise, destinations without a matching
                                    row use the default rate.
                                </p>
                                {regionalRates.map((rule, i) => (
                                    <div
                                        key={i}
                                        className="grid items-start gap-3 rounded-lg bg-muted/40 p-3 sm:grid-cols-2 xl:grid-cols-5"
                                    >
                                        <FormField
                                            id={`region-country-${i}`}
                                            label="Country"
                                            error={
                                                errors[
                                                    `regional_rates.${i}.country_code`
                                                ]
                                            }
                                        >
                                            <select
                                                id={`region-country-${i}`}
                                                className="h-9 w-full rounded-md border bg-background px-2 text-sm"
                                                value={rule.country_code}
                                                onChange={(e) =>
                                                    setRegionalRates(
                                                        regionalRates.map(
                                                            (r, n) =>
                                                                n === i
                                                                    ? {
                                                                          ...r,
                                                                          country_code:
                                                                              e
                                                                                  .target
                                                                                  .value,
                                                                          // A region for another country no longer applies.
                                                                          region: regionsFor(
                                                                              e
                                                                                  .target
                                                                                  .value,
                                                                          ).includes(
                                                                              r.region,
                                                                          )
                                                                              ? r.region
                                                                              : '',
                                                                      }
                                                                    : r,
                                                        ),
                                                    )
                                                }
                                            >
                                                <option value="">
                                                    Choose country...
                                                </option>
                                                {countries.map((c) => (
                                                    <option
                                                        key={c.code}
                                                        value={c.code}
                                                    >
                                                        {c.name}
                                                    </option>
                                                ))}
                                            </select>
                                        </FormField>
                                        <FormField
                                            id={`region-name-${i}`}
                                            label="State / province / region"
                                            error={
                                                errors[
                                                    `regional_rates.${i}.region`
                                                ]
                                            }
                                        >
                                            {(() => {
                                                const regions = regionsFor(
                                                    rule.country_code,
                                                );
                                                const update = (
                                                    region: string,
                                                ) =>
                                                    setRegionalRates(
                                                        regionalRates.map(
                                                            (r, n) =>
                                                                n === i
                                                                    ? {
                                                                          ...r,
                                                                          region,
                                                                      }
                                                                    : r,
                                                        ),
                                                    );

                                                // Pick the country's states
                                                // when we know them; otherwise a
                                                // free-text region.
                                                if (regions.length > 0) {
                                                    return (
                                                        <RegionCombobox
                                                            id={`region-name-${i}`}
                                                            value={rule.region}
                                                            onChange={update}
                                                            options={regions}
                                                            placeholder="Start typing…"
                                                        />
                                                    );
                                                }

                                                return (
                                                    <Input
                                                        id={`region-name-${i}`}
                                                        value={rule.region}
                                                        placeholder="Enter state, province or region"
                                                        onChange={(e) =>
                                                            update(
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                );
                                            })()}
                                        </FormField>
                                        <FormField
                                            id={`region-rate-${i}`}
                                            label={`Rate (${currency})`}
                                            error={
                                                errors[
                                                    `regional_rates.${i}.rate`
                                                ]
                                            }
                                        >
                                            <Input
                                                id={`region-rate-${i}`}
                                                inputMode="decimal"
                                                value={rule.rate}
                                                onChange={(e) =>
                                                    setRegionalRates(
                                                        regionalRates.map(
                                                            (r, n) =>
                                                                n === i
                                                                    ? {
                                                                          ...r,
                                                                          rate: e
                                                                              .target
                                                                              .value,
                                                                      }
                                                                    : r,
                                                        ),
                                                    )
                                                }
                                            />
                                        </FormField>
                                        <FormField
                                            id={`region-free-${i}`}
                                            label="Free over (optional)"
                                            error={
                                                errors[
                                                    `regional_rates.${i}.free_over`
                                                ]
                                            }
                                        >
                                            <Input
                                                id={`region-free-${i}`}
                                                inputMode="decimal"
                                                value={rule.free_over ?? ''}
                                                placeholder="Inherit"
                                                onChange={(e) =>
                                                    setRegionalRates(
                                                        regionalRates.map(
                                                            (r, n) =>
                                                                n === i
                                                                    ? {
                                                                          ...r,
                                                                          free_over:
                                                                              e
                                                                                  .target
                                                                                  .value,
                                                                      }
                                                                    : r,
                                                        ),
                                                    )
                                                }
                                            />
                                        </FormField>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            className="self-end"
                                            onClick={() =>
                                                setRegionalRates(
                                                    regionalRates.filter(
                                                        (_, n) => n !== i,
                                                    ),
                                                )
                                            }
                                        >
                                            Remove
                                        </Button>
                                    </div>
                                ))}

                                {errors.regional_rates && (
                                    <p className="text-sm text-destructive">
                                        {errors.regional_rates}
                                    </p>
                                )}
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="w-fit"
                                    disabled={regionalRates.length >= 100}
                                    onClick={() =>
                                        setRegionalRates([
                                            ...regionalRates,
                                            {
                                                country_code:
                                                    selected.length === 1
                                                        ? selected[0]
                                                        : '',
                                                region: '',
                                                rate: '',
                                                free_over: '',
                                            },
                                        ])
                                    }
                                >
                                    Add regional rate
                                </Button>
                            </div>
                            <FormField
                                id="delivery_estimate"
                                label="Estimated delivery (optional)"
                                error={errors.delivery_estimate}
                            >
                                <Input
                                    id="delivery_estimate"
                                    name="delivery_estimate"
                                    placeholder="1-2 business days"
                                    defaultValue={
                                        method?.delivery_estimate ?? ''
                                    }
                                />
                            </FormField>
                            {selected.map((code) => (
                                <input
                                    key={code}
                                    type="hidden"
                                    name="countries[]"
                                    value={code}
                                />
                            ))}
                            <div className="flex gap-2">
                                <Button type="submit" disabled={processing}>
                                    Save
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={onDone}
                                >
                                    Cancel
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </CardContent>
        </Card>
    );
}

ShippingIndex.layout = {
    breadcrumbs: [{ title: 'Shipping', href: index() }],
};
