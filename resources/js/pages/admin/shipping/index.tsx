import { Form, Head } from '@inertiajs/react';
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
import { index } from '@/routes/admin/shipping-methods';
import type { Country } from '@/types';

type Method = {
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
}: {
    methods: Method[];
    countries: Country[];
    currency: string;
}) {
    const [editing, setEditing] = useState<number | 'new' | null>(null);

    return (
        <>
            <Head title="Shipping" />
            <AdminPage>
                <PageHeader
                    title="Shipping methods"
                    description={`Flat-rate methods in ${currency}, optionally free above an order value and limited to certain countries.`}
                    actions={
                        editing === null && (
                            <Button onClick={() => setEditing('new')}>
                                Add method
                            </Button>
                        )
                    }
                />
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
                                                {m.countries.length === 0
                                                    ? 'all countries'
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
                                    label={`Rate (${currency})`}
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
                            <FormField
                                id="countries"
                                label="Ships to (leave empty for all countries)"
                                error={errors.countries}
                            >
                                <select
                                    id="countries"
                                    multiple
                                    value={selected}
                                    onChange={(e) =>
                                        setSelected(
                                            Array.from(
                                                e.target.selectedOptions,
                                            ).map((o) => o.value),
                                        )
                                    }
                                    className="h-40 w-full rounded-md border border-input bg-transparent p-2 text-sm dark:bg-input/30"
                                >
                                    {countries.map((c) => (
                                        <option key={c.code} value={c.code}>
                                            {c.name}
                                        </option>
                                    ))}
                                </select>
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
