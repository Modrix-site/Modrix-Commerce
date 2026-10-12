import { Form, Head } from '@inertiajs/react';
import StoreSettingsController from '@/actions/App/Http/Controllers/Admin/StoreSettingsController';
import { FormField, NativeSelect } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { edit } from '@/routes/admin/settings/store';
import type { CurrencyOption } from '@/types';

type Props = {
    settings: {
        store_name: string;
        store_email: string | null;
        default_currency: string;
        timezone: string;
        weight_unit: string;
        dimension_unit: string;
    };
    currencies: CurrencyOption[];
    timezones: string[];
    weightUnits: string[];
    dimensionUnits: string[];
};

export default function StoreSettings({
    settings,
    currencies,
    timezones,
    weightUnits,
    dimensionUnits,
}: Props) {
    return (
        <>
            <Head title="Store settings" />
            <AdminPage>
                <PageHeader
                    title="Store settings"
                    description="Store-wide defaults. Changes are recorded in the audit log."
                />

                <Form
                    {...StoreSettingsController.update.form()}
                    options={{ preserveScroll: true }}
                    className="grid gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <Card>
                                <CardHeader>
                                    <CardTitle>General</CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-4 sm:grid-cols-2">
                                    <FormField
                                        id="store_name"
                                        label="Store name"
                                        error={errors.store_name}
                                    >
                                        <Input
                                            id="store_name"
                                            name="store_name"
                                            defaultValue={settings.store_name}
                                            required
                                        />
                                    </FormField>
                                    <FormField
                                        id="store_email"
                                        label="Store contact email"
                                        error={errors.store_email}
                                        hint="Used as the reply-to address for customer emails."
                                    >
                                        <Input
                                            id="store_email"
                                            name="store_email"
                                            type="email"
                                            defaultValue={
                                                settings.store_email ?? ''
                                            }
                                        />
                                    </FormField>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Regional</CardTitle>
                                    <CardDescription>
                                        Prices are stored with an explicit
                                        currency. Changing the default currency
                                        does not convert existing prices.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-4 sm:grid-cols-2">
                                    <FormField
                                        id="default_currency"
                                        label="Default currency"
                                        error={errors.default_currency}
                                    >
                                        <NativeSelect
                                            id="default_currency"
                                            name="default_currency"
                                            defaultValue={
                                                settings.default_currency
                                            }
                                            required
                                        >
                                            {currencies.map((c) => (
                                                <option
                                                    key={c.code}
                                                    value={c.code}
                                                >
                                                    {c.code} — {c.name}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                    </FormField>
                                    <FormField
                                        id="timezone"
                                        label="Timezone"
                                        error={errors.timezone}
                                        hint="Used for reports and order dates."
                                    >
                                        <NativeSelect
                                            id="timezone"
                                            name="timezone"
                                            defaultValue={settings.timezone}
                                            required
                                        >
                                            {timezones.map((tz) => (
                                                <option key={tz} value={tz}>
                                                    {tz}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                    </FormField>
                                    <FormField
                                        id="weight_unit"
                                        label="Weight unit"
                                        error={errors.weight_unit}
                                    >
                                        <NativeSelect
                                            id="weight_unit"
                                            name="weight_unit"
                                            defaultValue={settings.weight_unit}
                                            required
                                        >
                                            {weightUnits.map((u) => (
                                                <option key={u} value={u}>
                                                    {u}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                    </FormField>
                                    <FormField
                                        id="dimension_unit"
                                        label="Dimension unit"
                                        error={errors.dimension_unit}
                                    >
                                        <NativeSelect
                                            id="dimension_unit"
                                            name="dimension_unit"
                                            defaultValue={
                                                settings.dimension_unit
                                            }
                                            required
                                        >
                                            {dimensionUnits.map((u) => (
                                                <option key={u} value={u}>
                                                    {u}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                    </FormField>
                                </CardContent>
                            </Card>

                            <div>
                                <Button type="submit" disabled={processing}>
                                    Save settings
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </AdminPage>
        </>
    );
}

StoreSettings.layout = {
    breadcrumbs: [{ title: 'Store settings', href: edit() }],
};
