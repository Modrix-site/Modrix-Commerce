import { Form, Head } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import TaxController from '@/actions/App/Http/Controllers/Admin/TaxController';
import { FormField, NativeSelect } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { trimDecimal } from '@/lib/decimal';
import { index } from '@/routes/admin/tax-rates';
import type { Country } from '@/types';

type Rate = {
    id: number;
    name: string;
    country_code: string;
    country_name: string;
    region: string | null;
    rate: string;
    is_active: boolean;
};

type Props = {
    rates: Rate[];
    settings: { prices_include_tax: boolean; shipping_taxable: boolean };
    countries: Country[];
};

export default function TaxesIndex({ rates, settings, countries }: Props) {
    return (
        <>
            <Head title="Taxes" />
            <AdminPage>
                <PageHeader
                    title="Taxes"
                    description="Rates are applied by shipping destination. A region-specific rate takes precedence over the country rate."
                />
                <Alert>
                    <TriangleAlert />
                    <AlertDescription>
                        EcoModular does not determine your tax obligations.
                        Configure the rates and rules required where you sell,
                        and consult a tax professional.
                    </AlertDescription>
                </Alert>

                <Card>
                    <CardHeader>
                        <CardTitle>Settings</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...TaxController.updateSettings.form()}
                            options={{ preserveScroll: true }}
                            className="grid gap-3"
                        >
                            {({ processing }) => (
                                <>
                                    <label className="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            name="prices_include_tax"
                                            value="1"
                                            defaultChecked={
                                                settings.prices_include_tax
                                            }
                                        />
                                        Catalog prices include tax
                                    </label>
                                    <label className="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            name="shipping_taxable"
                                            value="1"
                                            defaultChecked={
                                                settings.shipping_taxable
                                            }
                                        />
                                        Charge tax on shipping
                                    </label>
                                    <div>
                                        <Button
                                            type="submit"
                                            size="sm"
                                            disabled={processing}
                                        >
                                            Save settings
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Rates</CardTitle>
                        <CardDescription>
                            Destinations without a matching rate are not taxed.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-6">
                        {rates.length > 0 && (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Name</TableHead>
                                        <TableHead>Destination</TableHead>
                                        <TableHead>Rate</TableHead>
                                        <TableHead>
                                            <span className="sr-only">
                                                Actions
                                            </span>
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {rates.map((r) => (
                                        <TableRow key={r.id}>
                                            <TableCell>
                                                {r.name}
                                                {!r.is_active && (
                                                    <span className="ml-2 text-xs text-muted-foreground">
                                                        (inactive)
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {r.country_name}
                                                {r.region && ` · ${r.region}`}
                                            </TableCell>
                                            <TableCell className="tabular-nums">
                                                {trimDecimal(r.rate)}%
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Form
                                                    {...TaxController.destroy.form(
                                                        r.id,
                                                    )}
                                                    onBefore={() =>
                                                        window.confirm(
                                                            `Remove ${r.name}?`,
                                                        )
                                                    }
                                                >
                                                    <Button
                                                        type="submit"
                                                        variant="ghost"
                                                        size="sm"
                                                    >
                                                        Remove
                                                    </Button>
                                                </Form>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                        <Form
                            {...TaxController.store.form()}
                            options={{ preserveScroll: true }}
                            resetOnSuccess
                            className="grid gap-3 sm:grid-cols-5 sm:items-end"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <FormField
                                        id="name"
                                        label="Name"
                                        error={errors.name}
                                    >
                                        <Input
                                            id="name"
                                            name="name"
                                            placeholder="VAT"
                                            required
                                        />
                                    </FormField>
                                    <FormField
                                        id="country_code"
                                        label="Country"
                                        error={errors.country_code}
                                    >
                                        <NativeSelect
                                            id="country_code"
                                            name="country_code"
                                            defaultValue=""
                                            required
                                        >
                                            <option value="" disabled>
                                                Choose…
                                            </option>
                                            {countries.map((c) => (
                                                <option
                                                    key={c.code}
                                                    value={c.code}
                                                >
                                                    {c.name}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                    </FormField>
                                    <FormField
                                        id="region"
                                        label="Region (optional)"
                                        error={errors.region}
                                    >
                                        <Input id="region" name="region" />
                                    </FormField>
                                    <FormField
                                        id="rate"
                                        label="Rate (%)"
                                        error={errors.rate}
                                    >
                                        <Input
                                            id="rate"
                                            name="rate"
                                            inputMode="decimal"
                                            required
                                        />
                                    </FormField>
                                    <Button type="submit" disabled={processing}>
                                        Add rate
                                    </Button>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </AdminPage>
        </>
    );
}

TaxesIndex.layout = {
    breadcrumbs: [{ title: 'Taxes', href: index() }],
};
