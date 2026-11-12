import {
    DocumentSettingsFields,
    DocumentBusinessFields,
    type DocumentSettingsProps,
} from '@/components/admin/document-settings-fields';
import { Tabs } from '@/components/admin/tabs';
import InputError from '@/components/input-error';
import { useState } from 'react';
import { StoreExtraSettings } from '@/components/admin/store-extra-settings';
import {
    StoreLocationFields,
    type DeliveryAreaSettings,
} from '@/components/admin/store-location-fields';
import { Form, Head, Link } from '@inertiajs/react';
import StoreSettingsController from '@/actions/App/Http/Controllers/Admin/StoreSettingsController';
import { DownloadSettingsCard } from '@/components/admin/download-settings-card';
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
import { edit as generalSettings } from '@/routes/admin/settings/general';
import { edit } from '@/routes/admin/settings/store';
import type { CurrencyOption } from '@/types';

type Props = {
    customerAccounts: 'optional' | 'required';
    registrationEnabled: boolean;
    documents: DocumentSettingsProps;
    profile: Record<string, string | null>;
    delivery: DeliveryAreaSettings;
    displayCurrencies: { code: string; rate: string }[];
    countries: { code: string; name: string }[];
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
    customerAccounts,
    registrationEnabled,
    documents,
    profile,
    delivery,
    displayCurrencies,
    countries,
    settings,
    currencies,
    timezones,
    weightUnits,
    dimensionUnits,
}: Props) {
    const [rates, setRates] = useState(displayCurrencies);
    const [baseCurrency, setBaseCurrency] = useState(settings.default_currency);
    const [section, setSection] = useState('general');
    const [accounts, setAccounts] = useState(customerAccounts);
    return (
        <>
            <Head title="Store settings" />
            <AdminPage>
                <PageHeader
                    title="Store settings"
                    actions={
                        <Button variant="outline" asChild>
                            <Link href="/admin/settings/languages">
                                Manage languages
                            </Link>
                        </Button>
                    }
                    description="Store-wide defaults. Changes are recorded in the audit log."
                />

                <Form
                    {...StoreSettingsController.update.form()}
                    noValidate
                    transform={(data) => ({
                        ...data,
                        display_currencies: rates,
                    })}
                    options={{ preserveScroll: true }}
                    className="grid gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <Tabs
                                label="Store settings sections"
                                onChange={setSection}
                                items={[
                                    {
                                        id: 'general',
                                        label: 'General',
                                        hasError: Boolean(
                                            errors.store_name ||
                                            errors.store_email ||
                                            errors.customer_accounts,
                                        ),
                                        content: (
                                            <div className="grid gap-6">
                                                <Card>
                                                    <CardHeader>
                                                        <CardTitle>
                                                            General
                                                        </CardTitle>
                                                    </CardHeader>
                                                    <CardContent className="grid gap-4 sm:grid-cols-2">
                                                        <FormField
                                                            id="store_name"
                                                            label="Store name"
                                                            error={
                                                                errors.store_name
                                                            }
                                                        >
                                                            <Input
                                                                id="store_name"
                                                                name="store_name"
                                                                defaultValue={
                                                                    settings.store_name
                                                                }
                                                                required
                                                            />
                                                        </FormField>
                                                        <FormField
                                                            id="store_email"
                                                            label="Store contact email"
                                                            error={
                                                                errors.store_email
                                                            }
                                                            hint="Used as the reply-to address for customer emails."
                                                        >
                                                            <Input
                                                                id="store_email"
                                                                name="store_email"
                                                                type="email"
                                                                defaultValue={
                                                                    settings.store_email ??
                                                                    ''
                                                                }
                                                            />
                                                        </FormField>
                                                    </CardContent>
                                                </Card>
                                                <Card>
                                                    <CardHeader>
                                                        <CardTitle>
                                                            Customer accounts
                                                        </CardTitle>
                                                        <CardDescription>
                                                            Choose whether
                                                            shoppers can order
                                                            as a guest or must
                                                            have an account.
                                                        </CardDescription>
                                                    </CardHeader>
                                                    <CardContent>
                                                        <fieldset className="grid gap-3">
                                                            <legend className="sr-only">
                                                                Customer
                                                                accounts
                                                            </legend>
                                                            <label className="flex items-start gap-2 text-sm">
                                                                <input
                                                                    type="radio"
                                                                    name="customer_accounts"
                                                                    value="optional"
                                                                    className="mt-0.5"
                                                                    checked={
                                                                        accounts ===
                                                                        'optional'
                                                                    }
                                                                    onChange={() =>
                                                                        setAccounts(
                                                                            'optional',
                                                                        )
                                                                    }
                                                                />
                                                                <span className="grid gap-0.5">
                                                                    <span className="font-medium">
                                                                        Guests
                                                                        can
                                                                        order
                                                                        without
                                                                        an
                                                                        account
                                                                    </span>
                                                                    <span className="text-muted-foreground">
                                                                        Shoppers
                                                                        check
                                                                        out with
                                                                        an email
                                                                        address.
                                                                        They can
                                                                        still
                                                                        sign in
                                                                        or
                                                                        create
                                                                        an
                                                                        account
                                                                        if they
                                                                        want
                                                                        one.
                                                                    </span>
                                                                </span>
                                                            </label>
                                                            <label className="flex items-start gap-2 text-sm">
                                                                <input
                                                                    type="radio"
                                                                    name="customer_accounts"
                                                                    value="required"
                                                                    className="mt-0.5"
                                                                    checked={
                                                                        accounts ===
                                                                        'required'
                                                                    }
                                                                    onChange={() =>
                                                                        setAccounts(
                                                                            'required',
                                                                        )
                                                                    }
                                                                />
                                                                <span className="grid gap-0.5">
                                                                    <span className="font-medium">
                                                                        Customers
                                                                        need an
                                                                        account
                                                                        to order
                                                                    </span>
                                                                    <span className="text-muted-foreground">
                                                                        Shoppers
                                                                        can
                                                                        browse
                                                                        and fill
                                                                        their
                                                                        cart,
                                                                        then
                                                                        sign in
                                                                        or
                                                                        create
                                                                        an
                                                                        account
                                                                        before
                                                                        checkout.
                                                                        Their
                                                                        cart is
                                                                        kept.
                                                                    </span>
                                                                </span>
                                                            </label>
                                                            <InputError
                                                                message={
                                                                    errors.customer_accounts
                                                                }
                                                            />
                                                            {accounts ===
                                                                'required' &&
                                                                !registrationEnabled && (
                                                                    <p
                                                                        role="alert"
                                                                        className="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100"
                                                                    >
                                                                        Customer
                                                                        sign-up
                                                                        is
                                                                        turned
                                                                        off, so
                                                                        new
                                                                        shoppers
                                                                        will not
                                                                        be able
                                                                        to
                                                                        order.
                                                                        Turn it
                                                                        on in{' '}
                                                                        <Link
                                                                            href={generalSettings()}
                                                                            className="font-medium underline"
                                                                        >
                                                                            General
                                                                            settings
                                                                        </Link>
                                                                        .
                                                                    </p>
                                                                )}
                                                        </fieldset>
                                                    </CardContent>
                                                </Card>
                                            </div>
                                        ),
                                    },
                                    {
                                        id: 'regional',
                                        label: 'Regional',
                                        hasError: Boolean(
                                            errors.default_currency ||
                                            errors.timezone ||
                                            errors.weight_unit ||
                                            errors.dimension_unit ||
                                            errors.display_currencies ||
                                            Object.keys(errors).some(
                                                (key) =>
                                                    key.startsWith(
                                                        'delivery.',
                                                    ) ||
                                                    key.startsWith(
                                                        'display_currencies',
                                                    ) ||
                                                    [
                                                        'profile.country',
                                                        'profile.region',
                                                        'profile.city',
                                                    ].includes(key),
                                            ),
                                        ),
                                        content: (
                                            <Card>
                                                <CardHeader>
                                                    <CardTitle>
                                                        Regional
                                                    </CardTitle>
                                                    <CardDescription>
                                                        Prices are stored with
                                                        an explicit currency.
                                                        Changing the default
                                                        currency does not
                                                        convert existing prices.
                                                    </CardDescription>
                                                </CardHeader>
                                                <CardContent className="grid gap-4 sm:grid-cols-2">
                                                    <FormField
                                                        id="default_currency"
                                                        label="Default currency"
                                                        error={
                                                            errors.default_currency
                                                        }
                                                    >
                                                        <NativeSelect
                                                            id="default_currency"
                                                            name="default_currency"
                                                            value={baseCurrency}
                                                            onChange={(e) => {
                                                                setBaseCurrency(
                                                                    e.target
                                                                        .value,
                                                                );
                                                                setRates([]);
                                                            }}
                                                            required
                                                        >
                                                            {currencies.map(
                                                                (c) => (
                                                                    <option
                                                                        key={
                                                                            c.code
                                                                        }
                                                                        value={
                                                                            c.code
                                                                        }
                                                                    >
                                                                        {c.code}{' '}
                                                                        —{' '}
                                                                        {c.name}
                                                                    </option>
                                                                ),
                                                            )}
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
                                                            defaultValue={
                                                                settings.timezone
                                                            }
                                                            required
                                                        >
                                                            {timezones.map(
                                                                (tz) => (
                                                                    <option
                                                                        key={tz}
                                                                        value={
                                                                            tz
                                                                        }
                                                                    >
                                                                        {tz}
                                                                    </option>
                                                                ),
                                                            )}
                                                        </NativeSelect>
                                                    </FormField>
                                                    <FormField
                                                        id="weight_unit"
                                                        label="Weight unit"
                                                        error={
                                                            errors.weight_unit
                                                        }
                                                    >
                                                        <NativeSelect
                                                            id="weight_unit"
                                                            name="weight_unit"
                                                            defaultValue={
                                                                settings.weight_unit
                                                            }
                                                            required
                                                        >
                                                            {weightUnits.map(
                                                                (u) => (
                                                                    <option
                                                                        key={u}
                                                                        value={
                                                                            u
                                                                        }
                                                                    >
                                                                        {u}
                                                                    </option>
                                                                ),
                                                            )}
                                                        </NativeSelect>
                                                    </FormField>
                                                    <FormField
                                                        id="dimension_unit"
                                                        label="Dimension unit"
                                                        error={
                                                            errors.dimension_unit
                                                        }
                                                    >
                                                        <NativeSelect
                                                            id="dimension_unit"
                                                            name="dimension_unit"
                                                            defaultValue={
                                                                settings.dimension_unit
                                                            }
                                                            required
                                                        >
                                                            {dimensionUnits.map(
                                                                (u) => (
                                                                    <option
                                                                        key={u}
                                                                        value={
                                                                            u
                                                                        }
                                                                    >
                                                                        {u}
                                                                    </option>
                                                                ),
                                                            )}
                                                        </NativeSelect>
                                                    </FormField>
                                                    <div className="border-t pt-4 sm:col-span-2">
                                                        <StoreExtraSettings
                                                            section="currencies"
                                                            profile={profile}
                                                            countries={
                                                                countries
                                                            }
                                                            currencies={
                                                                currencies
                                                            }
                                                            rates={rates}
                                                            setRates={setRates}
                                                            baseCurrency={
                                                                baseCurrency
                                                            }
                                                            errors={errors}
                                                        />
                                                    </div>
                                                    <StoreLocationFields
                                                        profile={profile}
                                                        delivery={delivery}
                                                        countries={countries}
                                                        errors={errors}
                                                    />
                                                </CardContent>
                                            </Card>
                                        ),
                                    },
                                    {
                                        id: 'business',
                                        label: 'Business',
                                        hasError: Object.keys(errors).some(
                                            (key) =>
                                                [
                                                    'seller_address',
                                                    'tax_id',
                                                    'logo_media_id',
                                                    'footer',
                                                ].includes(key) ||
                                                (key.startsWith('profile.') &&
                                                    ![
                                                        'profile.country',
                                                        'profile.region',
                                                        'profile.city',
                                                    ].includes(key) &&
                                                    !key.startsWith(
                                                        'profile.meta_',
                                                    )),
                                        ),
                                        content: (
                                            <div className="grid gap-6">
                                                <StoreExtraSettings
                                                    section="business"
                                                    profile={profile}
                                                    countries={countries}
                                                    currencies={currencies}
                                                    rates={rates}
                                                    setRates={setRates}
                                                    baseCurrency={baseCurrency}
                                                    errors={errors}
                                                />
                                                <DocumentBusinessFields
                                                    settings={
                                                        documents.settings
                                                    }
                                                    logos={documents.logos}
                                                    address={profile.address}
                                                    errors={errors}
                                                />
                                            </div>
                                        ),
                                    },
                                    {
                                        id: 'seo',
                                        label: 'SEO',
                                        hasError: Boolean(
                                            errors['profile.meta_title'] ||
                                            errors['profile.meta_description'],
                                        ),
                                        content: (
                                            <StoreExtraSettings
                                                section="seo"
                                                profile={profile}
                                                countries={countries}
                                                currencies={currencies}
                                                rates={rates}
                                                setRates={setRates}
                                                baseCurrency={baseCurrency}
                                                errors={errors}
                                            />
                                        ),
                                    },
                                    {
                                        id: 'documents',
                                        label: 'Orders & documents',
                                        hasError: Object.keys(errors).some(
                                            (key) =>
                                                [
                                                    'order_prefix',
                                                    'order_padding',
                                                    'next_order_number',
                                                    'invoice_prefix',
                                                    'invoice_padding',
                                                    'next_invoice_number',
                                                ].includes(key) ||
                                                key.startsWith('emails.'),
                                        ),
                                        content: (
                                            <DocumentSettingsFields
                                                {...documents}
                                                errors={errors}
                                            />
                                        ),
                                    },
                                ]}
                            />
                            <div className="sticky bottom-0 z-10 flex items-center justify-between gap-3 border-t bg-background/95 py-4 backdrop-blur">
                                <p className="text-sm text-muted-foreground">
                                    Save changes across all tabs.
                                </p>
                                <Button type="submit" disabled={processing}>
                                    Save settings
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
                <div hidden={section !== 'general'}>
                    <DownloadSettingsCard />
                </div>
            </AdminPage>
        </>
    );
}

StoreSettings.layout = {
    breadcrumbs: [{ title: 'Store settings', href: edit() }],
};
