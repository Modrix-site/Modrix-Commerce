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

type Settings = {
    enabled: boolean;
    test_mode: boolean;
    callbacks_enabled: boolean;
    refunds_enabled: boolean;
    send_billing: boolean;
    send_shipping: boolean;
    merchant_id: string;
    label: string;
    currencies: string[];
    language: string;
    session_minutes: number;
    access_code_saved: boolean;
    working_key_saved: boolean;
    ready: boolean;
};

const toggles = [
    [
        'enabled',
        'Enable CCAvenue at checkout',
        'Offer CCAvenue when credentials are saved and the order currency is enabled.',
    ],
    [
        'test_mode',
        'Test mode',
        'Use CCAvenue’s test environment. Turn off only after verifying your live credentials.',
    ],
    [
        'callbacks_enabled',
        'Enable payment callbacks',
        'Receive encrypted responses and verify the payment through CCAvenue’s API. Keep enabled for customer returns.',
    ],
    [
        'refunds_enabled',
        'Enable API refunds',
        'Allow authorized staff to request full or partial refunds. Your CCAvenue account needs refund API permission.',
    ],
    [
        'send_billing',
        'Prefill billing details',
        'Send the order’s billing address and email to CCAvenue.',
    ],
    [
        'send_shipping',
        'Prefill shipping details',
        'Send the delivery address to CCAvenue.',
    ],
] as const;

export default function CCAvenueSettingsPage({
    settings,
    currencies,
    updateUrl,
    callbackUrl,
    webhookUrl,
}: {
    settings: Settings;
    currencies: string[];
    updateUrl: string;
    callbackUrl: string;
    webhookUrl: string;
}) {
    const form = useForm({
        enabled: settings.enabled,
        test_mode: settings.test_mode,
        callbacks_enabled: settings.callbacks_enabled,
        refunds_enabled: settings.refunds_enabled,
        send_billing: settings.send_billing,
        send_shipping: settings.send_shipping,
        merchant_id: settings.merchant_id,
        label: settings.label,
        currencies: settings.currencies,
        language: settings.language,
        session_minutes: settings.session_minutes,
        access_code: '',
        working_key: '',
    });

    return (
        <>
            <Head title="CCAvenue" />
            <AdminPage>
                <PageHeader
                    title="CCAvenue"
                    description="CCAvenue hosted checkout by Modrix team. Payments are verified with the provider before the core confirms an order."
                    actions={
                        <Badge variant="secondary">
                            {settings.test_mode ? 'Test mode' : 'Live mode'} ·{' '}
                            {settings.ready ? 'Enabled' : 'Setup required'}
                        </Badge>
                    }
                />
                <form
                    className="grid gap-6"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put(updateUrl, {
                            preserveScroll: true,
                            onSuccess: () =>
                                form.reset('access_code', 'working_key'),
                        });
                    }}
                >
                    <Card>
                        <CardHeader>
                            <CardTitle>Merchant account</CardTitle>
                            <CardDescription>
                                For CCAvenue India accounts using ccavenue.com.
                                Get credentials from M.A.R.S → Settings → API
                                Keys. Enable the status API and allowlist your
                                server IP with CCAvenue.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-2">
                            <FormField
                                id="merchant_id"
                                label="Merchant ID"
                                error={form.errors.merchant_id}
                            >
                                <Input
                                    id="merchant_id"
                                    value={form.data.merchant_id}
                                    onChange={(e) =>
                                        form.setData(
                                            'merchant_id',
                                            e.target.value,
                                        )
                                    }
                                    inputMode="numeric"
                                />
                            </FormField>
                            <FormField
                                id="label"
                                label="Checkout label"
                                error={form.errors.label}
                            >
                                <Input
                                    id="label"
                                    value={form.data.label}
                                    maxLength={60}
                                    onChange={(e) =>
                                        form.setData('label', e.target.value)
                                    }
                                />
                            </FormField>
                            {(['access_code', 'working_key'] as const).map(
                                (key) => (
                                    <FormField
                                        key={key}
                                        id={key}
                                        label={
                                            key === 'access_code'
                                                ? 'Access code'
                                                : 'Working key'
                                        }
                                        error={form.errors[key]}
                                        hint={
                                            settings[`${key}_saved`]
                                                ? 'Saved securely. Leave blank to keep the current credential.'
                                                : 'No credential saved.'
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
                            <CardTitle>Features</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-5">
                            {toggles.map(([key, label, hint]) => (
                                <div key={key} className="grid gap-1">
                                    <label className="flex items-center gap-3 text-sm font-medium">
                                        <Checkbox
                                            checked={form.data[key]}
                                            onCheckedChange={(value) =>
                                                form.setData(
                                                    key,
                                                    value === true,
                                                )
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
                            <CardTitle>Checkout preferences</CardTitle>
                            <CardDescription>
                                Select only currencies enabled on your CCAvenue
                                merchant account.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <fieldset className="grid gap-3">
                                <legend className="mb-2 text-sm font-medium">
                                    Accepted currencies
                                </legend>
                                <div className="flex flex-wrap gap-5">
                                    {currencies.map((currency) => (
                                        <label
                                            key={currency}
                                            className="flex items-center gap-2 text-sm"
                                        >
                                            <Checkbox
                                                checked={form.data.currencies.includes(
                                                    currency,
                                                )}
                                                onCheckedChange={(checked) =>
                                                    form.setData(
                                                        'currencies',
                                                        checked === true
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
                                    <option value="EN">English</option>
                                    <option value="HI">Hindi</option>
                                </NativeSelect>
                            </FormField>
                            <FormField
                                id="session_minutes"
                                label="Local checkout link lifetime (minutes)"
                                error={form.errors.session_minutes}
                                hint="This controls the store handoff link. A payment page already opened at CCAvenue may still be payable."
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
                            <CardTitle>Connection details</CardTitle>
                            <CardDescription>
                                The store must be reachable by CCAvenue over
                                HTTPS. The core reconciliation schedule also
                                checks pending payments.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-3 text-sm">
                            <FormField
                                id="callback_url"
                                label="Customer response URL"
                                hint="Sent automatically as both redirect_url and cancel_url."
                            >
                                <Input
                                    id="callback_url"
                                    readOnly
                                    value={callbackUrl}
                                    onFocus={(e) => e.target.select()}
                                />
                            </FormField>
                            <FormField
                                id="webhook_url"
                                label="Payment notification URL"
                                hint="Configure in CCAvenue only for notifications using the encrypted encResp format."
                            >
                                <Input
                                    id="webhook_url"
                                    readOnly
                                    value={webhookUrl}
                                    onFocus={(e) => e.target.select()}
                                />
                            </FormField>
                            <p>
                                Refund requests accepted by CCAvenue remain
                                pending while the bank processes them. Confirm
                                settlement in your merchant dashboard.
                            </p>
                        </CardContent>
                    </Card>
                    <div>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing
                                ? 'Saving…'
                                : 'Save CCAvenue settings'}
                        </Button>
                    </div>
                </form>
            </AdminPage>
        </>
    );
}
