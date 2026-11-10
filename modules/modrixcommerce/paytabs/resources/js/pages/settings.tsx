import { Head, useForm } from '@inertiajs/react';
import { FormField, NativeSelect } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
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

type Config = {
    enabled: boolean;
    test_mode: boolean;
    callbacks_enabled: boolean;
    refunds_enabled: boolean;
    send_billing: boolean;
    send_shipping: boolean;
    hide_shipping: boolean;
    all_methods: boolean;
    profile_id: string;
    test_profile_id: string;
    region: string;
    label: string;
    language: string;
    currencies: string[];
    payment_methods: string[];
    session_minutes: number;
};
type Settings = Config & {
    server_key_saved: boolean;
    test_server_key_saved: boolean;
    ready: boolean;
};

const switches = [
    [
        'enabled',
        'Enable PayTabs at checkout',
        'Offer PayTabs for the currencies selected below once the active profile has credentials.',
    ],
    [
        'test_mode',
        'Use test profile',
        'Uses the separate test profile ID and server key below. PayTabs determines test/live behavior from the profile.',
    ],
    [
        'callbacks_enabled',
        'Enable callbacks and IPN',
        'Request signed server notifications. Customer returns and scheduled payment reconciliation also verify payments.',
    ],
    [
        'refunds_enabled',
        'Enable full and partial API refunds',
        'Allow authorized staff to issue refunds through PayTabs. Merchant profile permission is required.',
    ],
    [
        'send_billing',
        'Prefill billing details',
        'Send the order’s billing address and email to the secure payment page.',
    ],
    [
        'send_shipping',
        'Prefill shipping details',
        'Send the order’s delivery address to PayTabs.',
    ],
    [
        'hide_shipping',
        'Hide address fields on PayTabs',
        'PayTabs honors this only when complete, valid customer details are supplied.',
    ],
] as const;

export default function PayTabsSettingsPage({
    settings,
    regions,
    currencies,
    methods,
    updateUrl,
    webhookUrl,
    reconcileUrl,
}: {
    settings: Settings;
    regions: Record<string, string>;
    currencies: string[];
    methods: string[];
    updateUrl: string;
    webhookUrl: string;
    reconcileUrl: string;
}) {
    const form = useForm({
        enabled: settings.enabled,
        test_mode: settings.test_mode,
        callbacks_enabled: settings.callbacks_enabled,
        refunds_enabled: settings.refunds_enabled,
        send_billing: settings.send_billing,
        send_shipping: settings.send_shipping,
        hide_shipping: settings.hide_shipping,
        all_methods: settings.all_methods,
        profile_id: settings.profile_id,
        test_profile_id: settings.test_profile_id,
        region: settings.region,
        label: settings.label,
        language: settings.language,
        currencies: settings.currencies,
        payment_methods: settings.payment_methods,
        session_minutes: settings.session_minutes,
        server_key: '',
        test_server_key: '',
    });
    const refund = useForm({ payment_id: '', reference: '' });

    return (
        <>
            <Head title="PayTabs" />
            <AdminPage>
                <PageHeader
                    title="PayTabs"
                    description="Hosted payments by Modrix team. Card details stay on PayTabs; verified provider facts flow through the core order, invoice and inventory workflow."
                    actions={
                        <Badge variant="secondary">
                            {settings.test_mode
                                ? 'Test profile'
                                : 'Live profile'}{' '}
                            · {settings.ready ? 'Enabled' : 'Setup required'}
                        </Badge>
                    }
                />
                <form
                    className="grid gap-6"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.put(updateUrl, {
                            preserveScroll: true,
                            onSuccess: () =>
                                form.reset('server_key', 'test_server_key'),
                        });
                    }}
                >
                    <Card>
                        <CardHeader>
                            <CardTitle>Merchant profiles</CardTitle>
                            <CardDescription>
                                Copy the profile ID and API server key from the
                                PayTabs dashboard. Choose the region where your
                                account is registered. Both profiles use that
                                regional host.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-2">
                            <FormField
                                id="region"
                                label="Account region"
                                error={form.errors.region}
                            >
                                <NativeSelect
                                    id="region"
                                    value={form.data.region}
                                    onChange={(e) =>
                                        form.setData('region', e.target.value)
                                    }
                                >
                                    {Object.entries(regions).map(
                                        ([code, label]) => (
                                            <option key={code} value={code}>
                                                {label}
                                            </option>
                                        ),
                                    )}
                                </NativeSelect>
                            </FormField>
                            <FormField
                                id="label"
                                label="Checkout label"
                                error={form.errors.label}
                            >
                                <Input
                                    id="label"
                                    maxLength={60}
                                    value={form.data.label}
                                    onChange={(e) =>
                                        form.setData('label', e.target.value)
                                    }
                                />
                            </FormField>
                            {(['test_profile_id', 'profile_id'] as const).map(
                                (key) => (
                                    <FormField
                                        key={key}
                                        id={key}
                                        label={
                                            key === 'profile_id'
                                                ? 'Live profile ID'
                                                : 'Test profile ID'
                                        }
                                        error={form.errors[key]}
                                    >
                                        <Input
                                            id={key}
                                            inputMode="numeric"
                                            value={form.data[key]}
                                            onChange={(e) =>
                                                form.setData(
                                                    key,
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </FormField>
                                ),
                            )}
                            {(['test_server_key', 'server_key'] as const).map(
                                (key) => (
                                    <FormField
                                        key={key}
                                        id={key}
                                        label={
                                            key === 'server_key'
                                                ? 'Live server key'
                                                : 'Test server key'
                                        }
                                        error={form.errors[key]}
                                        hint={
                                            settings[`${key}_saved`]
                                                ? 'Saved encrypted. Leave blank to keep the current key.'
                                                : 'No key saved.'
                                        }
                                    >
                                        <Input
                                            id={key}
                                            type="password"
                                            autoComplete="new-password"
                                            value={form.data[key]}
                                            onChange={(e) =>
                                                form.setData(
                                                    key,
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </FormField>
                                ),
                            )}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Feature controls</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-5">
                            {switches.map(([key, label, hint]) => (
                                <div key={key} className="grid gap-1">
                                    <label className="flex items-center gap-3 text-sm font-medium">
                                        <Checkbox
                                            checked={form.data[key]}
                                            onCheckedChange={(v) =>
                                                form.setData(key, v === true)
                                            }
                                        />
                                        {label}
                                    </label>
                                    <p className="pl-7 text-xs text-muted-foreground">
                                        {hint}
                                    </p>
                                    {form.errors[key] && (
                                        <p className="text-sm text-destructive">
                                            {form.errors[key]}
                                        </p>
                                    )}
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Payment methods</CardTitle>
                            <CardDescription>
                                Only methods enabled on your PayTabs merchant
                                profile can be offered. Availability also
                                depends on region and currency.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <label className="flex items-center gap-3 text-sm font-medium">
                                <Checkbox
                                    checked={form.data.all_methods}
                                    onCheckedChange={(v) =>
                                        form.setData('all_methods', v === true)
                                    }
                                />
                                Allow all methods configured on the profile
                            </label>
                            <fieldset
                                disabled={form.data.all_methods}
                                className="grid gap-3 disabled:opacity-50 sm:grid-cols-3"
                            >
                                {methods.map((method) => (
                                    <label
                                        key={method}
                                        className="flex items-center gap-2 text-sm"
                                    >
                                        <Checkbox
                                            checked={form.data.payment_methods.includes(
                                                method,
                                            )}
                                            onCheckedChange={(v) =>
                                                form.setData(
                                                    'payment_methods',
                                                    v === true
                                                        ? [
                                                              ...form.data
                                                                  .payment_methods,
                                                              method,
                                                          ]
                                                        : form.data.payment_methods.filter(
                                                              (m) =>
                                                                  m !== method,
                                                          ),
                                                )
                                            }
                                        />
                                        {method}
                                    </label>
                                ))}
                            </fieldset>
                            {form.errors.payment_methods && (
                                <p className="text-sm text-destructive">
                                    {form.errors.payment_methods}
                                </p>
                            )}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Checkout preferences</CardTitle>
                            <CardDescription>
                                Select only currencies activated on your
                                merchant profile.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <fieldset className="grid gap-3">
                                <legend className="mb-2 text-sm font-medium">
                                    Accepted currencies
                                </legend>
                                <div className="flex flex-wrap gap-4">
                                    {currencies.map((currency) => (
                                        <label
                                            key={currency}
                                            className="flex items-center gap-2 text-sm"
                                        >
                                            <Checkbox
                                                checked={form.data.currencies.includes(
                                                    currency,
                                                )}
                                                onCheckedChange={(v) =>
                                                    form.setData(
                                                        'currencies',
                                                        v === true
                                                            ? [
                                                                  ...form.data
                                                                      .currencies,
                                                                  currency,
                                                              ]
                                                            : form.data.currencies.filter(
                                                                  (c) =>
                                                                      c !==
                                                                      currency,
                                                              ),
                                                    )
                                                }
                                            />
                                            {currency}
                                        </label>
                                    ))}
                                </div>
                                {form.errors.currencies && (
                                    <p className="text-sm text-destructive">
                                        {form.errors.currencies}
                                    </p>
                                )}
                            </fieldset>
                            <FormField
                                id="language"
                                label="Payment page language"
                                error={form.errors.language}
                            >
                                <NativeSelect
                                    id="language"
                                    value={form.data.language}
                                    onChange={(e) =>
                                        form.setData('language', e.target.value)
                                    }
                                >
                                    <option value="en">English</option>
                                    <option value="ar">Arabic</option>
                                </NativeSelect>
                            </FormField>
                            <FormField
                                id="session_minutes"
                                label="Local session lifetime (minutes)"
                                error={form.errors.session_minutes}
                                hint="Controls reuse by the core. It cannot revoke a PayTabs page already opened by the customer."
                            >
                                <Input
                                    id="session_minutes"
                                    type="number"
                                    min={5}
                                    max={60}
                                    value={form.data.session_minutes}
                                    onChange={(e) =>
                                        form.setData(
                                            'session_minutes',
                                            Number(e.target.value),
                                        )
                                    }
                                />
                            </FormField>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Callbacks and connection</CardTitle>
                            <CardDescription>
                                PayTabs requires a public HTTPS store. Configure
                                this same URL as the profile’s IPN listener for
                                later payment and refund status changes.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-3 text-sm">
                            <FormField
                                id="webhook_url"
                                label="Signed callback / IPN URL"
                            >
                                <Input
                                    id="webhook_url"
                                    readOnly
                                    value={webhookUrl}
                                    onFocus={(e) => e.target.select()}
                                />
                            </FormField>
                            <p>
                                Customer return URLs are generated
                                automatically. The core scheduler and queue
                                worker should be running. Switching test/live
                                mode preserves the original profile for existing
                                payments; keep their credentials saved.
                            </p>
                        </CardContent>
                    </Card>
                    <div>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing
                                ? 'Saving…'
                                : 'Save PayTabs settings'}
                        </Button>
                    </div>
                </form>
                <Card>
                    <CardHeader>
                        <CardTitle>Reconcile a refund</CardTitle>
                        <CardDescription>
                            If a refund request timed out, check the PayTabs
                            dashboard before retrying. Enter the core payment ID
                            and the PayTabs refund transaction reference to
                            verify its status and update the order. This also
                            resolves an uncertain local refund reservation.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form
                            className="grid gap-4"
                            onSubmit={(e) => {
                                e.preventDefault();
                                refund.post(reconcileUrl, {
                                    preserveScroll: true,
                                });
                            }}
                        >
                            <FormField
                                id="refund_payment_id"
                                label="Core payment ID"
                                error={refund.errors.payment_id}
                            >
                                <Input
                                    id="refund_payment_id"
                                    type="number"
                                    min={1}
                                    value={refund.data.payment_id}
                                    onChange={(e) =>
                                        refund.setData(
                                            'payment_id',
                                            e.target.value,
                                        )
                                    }
                                />
                            </FormField>
                            <FormField
                                id="refund_reference"
                                label="PayTabs refund transaction reference"
                                error={refund.errors.reference}
                            >
                                <Input
                                    id="refund_reference"
                                    value={refund.data.reference}
                                    onChange={(e) =>
                                        refund.setData(
                                            'reference',
                                            e.target.value,
                                        )
                                    }
                                />
                            </FormField>
                            <div>
                                <Button
                                    variant="outline"
                                    type="submit"
                                    disabled={refund.processing}
                                >
                                    {refund.processing
                                        ? 'Checking…'
                                        : 'Verify refund status'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </AdminPage>
        </>
    );
}
