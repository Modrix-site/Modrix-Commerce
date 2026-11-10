import { Head, useForm } from '@inertiajs/react';
import { FormField } from '@/components/admin/form-field';
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
    webhooks_enabled: boolean;
    refunds_enabled: boolean;
    capture_authorized: boolean;
    prefill_customer: boolean;
    retry_enabled: boolean;
    confirm_close: boolean;
    auto_open: boolean;
    all_methods: boolean;
    payment_methods: string[];
    key_id: string;
    test_key_id: string;
    label: string;
    brand_name: string;
    theme_color: string;
    currencies: string[];
    session_minutes: number;
};
type Settings = Config & {
    key_secret_saved: boolean;
    test_key_secret_saved: boolean;
    webhook_secret_saved: boolean;
    test_webhook_secret_saved: boolean;
    ready: boolean;
};

const toggles = [
    [
        'enabled',
        'Enable Razorpay at checkout',
        'Offer Razorpay when the active mode has credentials and the order currency is selected below.',
    ],
    [
        'test_mode',
        'Use test mode',
        'Use the separate rzp_test_ API credentials. Live mode uses the rzp_live_ credentials.',
    ],
    [
        'webhooks_enabled',
        'Enable signed webhooks',
        'Receive payment and refund updates through the core webhook inbox. Keep the scheduler and queue worker running.',
    ],
    [
        'refunds_enabled',
        'Enable full and partial API refunds',
        'Allow authorized staff to issue normal refunds with local retry protection and provider idempotency.',
    ],
    [
        'capture_authorized',
        'Capture verified authorizations automatically',
        'Capture the full verified amount for open, unexpired orders. Otherwise, use Razorpay dashboard capture settings. Only captured payments confirm the order.',
    ],
    [
        'prefill_customer',
        'Prefill customer details',
        'Send the customer name, email and contact number to Razorpay Checkout.',
    ],
    [
        'retry_enabled',
        'Allow retries in Razorpay Checkout',
        'Let the customer try another payment attempt on the same Razorpay order.',
    ],
    [
        'confirm_close',
        'Confirm before closing checkout',
        'Ask the customer to confirm before closing the payment window.',
    ],
    [
        'auto_open',
        'Open checkout automatically',
        'Open Razorpay when the customer reaches the payment page. A pay button remains available.',
    ],
] as const;

export default function RazorpaySettingsPage({
    settings,
    currencies,
    methods,
    updateUrl,
    webhookUrl,
    reconcileUrl,
}: {
    settings: Settings;
    currencies: string[];
    methods: string[];
    updateUrl: string;
    webhookUrl: string;
    reconcileUrl: string;
}) {
    const form = useForm({
        enabled: settings.enabled,
        test_mode: settings.test_mode,
        webhooks_enabled: settings.webhooks_enabled,
        refunds_enabled: settings.refunds_enabled,
        capture_authorized: settings.capture_authorized,
        prefill_customer: settings.prefill_customer,
        retry_enabled: settings.retry_enabled,
        confirm_close: settings.confirm_close,
        auto_open: settings.auto_open,
        all_methods: settings.all_methods,
        payment_methods: settings.payment_methods,
        key_id: settings.key_id,
        test_key_id: settings.test_key_id,
        label: settings.label,
        brand_name: settings.brand_name,
        theme_color: settings.theme_color,
        currencies: settings.currencies,
        session_minutes: settings.session_minutes,
        key_secret: '',
        test_key_secret: '',
        webhook_secret: '',
        test_webhook_secret: '',
    });
    const refund = useForm({ payment_id: '', reference: '' });

    return (
        <>
            <Head title="Razorpay" />
            <AdminPage>
                <PageHeader
                    title="Razorpay"
                    description="Standard Checkout by Modrix team. Server-created Razorpay Orders and verified captures connect to the core payment, order, inventory and invoice workflow."
                    actions={
                        <Badge variant="secondary">
                            {settings.test_mode ? 'Test mode' : 'Live mode'} ·{' '}
                            {settings.ready ? 'Enabled' : 'Setup required'}
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
                                form.reset(
                                    'key_secret',
                                    'test_key_secret',
                                    'webhook_secret',
                                    'test_webhook_secret',
                                ),
                        });
                    }}
                >
                    <Card>
                        <CardHeader>
                            <CardTitle>API credentials</CardTitle>
                            <CardDescription>
                                Generate separate test and live API keys in your
                                Razorpay dashboard. API secrets and webhook
                                secrets are encrypted and never returned to the
                                browser.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-2">
                            {(['test_key_id', 'key_id'] as const).map((key) => (
                                <FormField
                                    key={key}
                                    id={key}
                                    label={
                                        key === 'key_id'
                                            ? 'Live key ID'
                                            : 'Test key ID'
                                    }
                                    error={form.errors[key]}
                                >
                                    <Input
                                        id={key}
                                        placeholder={
                                            key === 'key_id'
                                                ? 'rzp_live_…'
                                                : 'rzp_test_…'
                                        }
                                        value={form.data[key]}
                                        onChange={(e) =>
                                            form.setData(key, e.target.value)
                                        }
                                    />
                                </FormField>
                            ))}
                            {(
                                [
                                    'test_key_secret',
                                    'key_secret',
                                    'test_webhook_secret',
                                    'webhook_secret',
                                ] as const
                            ).map((key) => (
                                <FormField
                                    key={key}
                                    id={key}
                                    label={
                                        {
                                            test_key_secret: 'Test API secret',
                                            key_secret: 'Live API secret',
                                            test_webhook_secret:
                                                'Test webhook secret',
                                            webhook_secret:
                                                'Live webhook secret',
                                        }[key]
                                    }
                                    error={form.errors[key]}
                                    hint={
                                        settings[`${key}_saved`]
                                            ? 'Saved encrypted. Leave blank to keep the existing secret.'
                                            : 'No secret saved.'
                                    }
                                >
                                    <Input
                                        id={key}
                                        type="password"
                                        autoComplete="new-password"
                                        value={form.data[key]}
                                        onChange={(e) =>
                                            form.setData(key, e.target.value)
                                        }
                                    />
                                </FormField>
                            ))}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Feature controls</CardTitle>
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
                            <CardTitle>Payment methods</CardTitle>
                            <CardDescription>
                                Checkout can display all methods activated on
                                your Razorpay account, or the selected methods.
                                Method availability depends on the account and
                                currency. COD is hidden because this gateway
                                requires captured funds.
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
                                Show all account-enabled methods
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
                                Select only currencies supported by your
                                account. Three-decimal currencies must have the
                                last minor-unit digit set to zero; amounts are
                                never silently rounded.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <FormField
                                id="label"
                                label="Checkout method label"
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
                            <FormField
                                id="brand_name"
                                label="Business name on Razorpay"
                                error={form.errors.brand_name}
                            >
                                <Input
                                    id="brand_name"
                                    value={form.data.brand_name}
                                    maxLength={80}
                                    onChange={(e) =>
                                        form.setData(
                                            'brand_name',
                                            e.target.value,
                                        )
                                    }
                                />
                            </FormField>
                            <FormField
                                id="theme_color"
                                label="Checkout accent color"
                                error={form.errors.theme_color}
                            >
                                <Input
                                    id="theme_color"
                                    type="color"
                                    value={form.data.theme_color}
                                    onChange={(e) =>
                                        form.setData(
                                            'theme_color',
                                            e.target.value,
                                        )
                                    }
                                />
                            </FormField>
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
                                id="session_minutes"
                                label="Local payment page lifetime (minutes)"
                                error={form.errors.session_minutes}
                                hint="Controls local checkout access and session reuse. It cannot cancel a Razorpay Order or guarantee that a provider page already opened has expired."
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
                            <CardTitle>Webhook connection</CardTitle>
                            <CardDescription>
                                Configure this public HTTPS URL separately in
                                test and live mode, using the matching webhook
                                secrets above. Allowlist the store’s callback
                                domain in Razorpay.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-3 text-sm">
                            <FormField
                                id="webhook_url"
                                label="Signed webhook URL"
                            >
                                <Input
                                    id="webhook_url"
                                    readOnly
                                    value={webhookUrl}
                                    onFocus={(e) => e.target.select()}
                                />
                            </FormField>
                            <p>
                                Subscribe to payment.authorized,
                                payment.captured, payment.failed, order.paid,
                                refund.created, refund.processed and
                                refund.failed. Keep the core scheduler and queue
                                worker running.
                            </p>
                            <p>
                                Switching test/live mode preserves the original
                                mode for existing orders. Keep their API
                                credentials saved until all payments and refunds
                                have settled.
                            </p>
                        </CardContent>
                    </Card>
                    <div>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing
                                ? 'Saving…'
                                : 'Save Razorpay settings'}
                        </Button>
                    </div>
                </form>
                <Card>
                    <CardHeader>
                        <CardTitle>Reconcile a refund</CardTitle>
                        <CardDescription>
                            If a refund times out, check the dashboard before
                            trying again. Enter the core payment ID and Razorpay
                            refund ID to query its status, update the order and
                            resolve an uncertain local request.
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
                                label="Razorpay refund ID"
                                error={refund.errors.reference}
                            >
                                <Input
                                    id="refund_reference"
                                    placeholder="rfnd_…"
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
                                    type="submit"
                                    variant="outline"
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
