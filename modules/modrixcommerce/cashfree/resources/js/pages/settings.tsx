import { Head, router, useForm } from '@inertiajs/react';
import { CircleAlert, CircleCheck, Copy, RefreshCw } from 'lucide-react';
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
import { useClipboard } from '@/hooks/use-clipboard';

type CashfreeSettings = {
    enabled: boolean;
    app_id: string;
    test_mode: boolean;
    label: string;
    currencies: string[];
    secret_key_hint: string | null;
    ready: boolean;
};

const SUPPORTED_CURRENCIES = [
    { code: 'INR', label: 'INR (Indian Rupee - UPI, Netbanking, Cards)' },
    { code: 'USD', label: 'USD (US Dollar - International)' },
    { code: 'EUR', label: 'EUR (Euro - International)' },
    { code: 'GBP', label: 'GBP (British Pound)' },
    { code: 'AED', label: 'AED (UAE Dirham)' },
    { code: 'SGD', label: 'SGD (Singapore Dollar)' },
];

export default function CashfreeSettingsPage({
    settings,
    webhookUrl,
    responseUrl,
    publicWebhookUrl,
    updateUrl,
    testUrl,
}: {
    settings: CashfreeSettings;
    webhookUrl: string;
    responseUrl: string;
    publicWebhookUrl: boolean;
    updateUrl: string;
    testUrl: string;
}) {
    const [, copyWebhook] = useClipboard();
    const [, copyResponse] = useClipboard();

    const form = useForm({
        enabled: settings.enabled,
        app_id: settings.app_id,
        test_mode: settings.test_mode,
        label: settings.label,
        currencies: settings.currencies,
        secret_key: '',
    });

    const checks: [boolean, string][] = [
        [settings.app_id !== '', 'Cashfree App ID configured'],
        [settings.secret_key_hint !== null, 'Cashfree Secret Key saved encrypted'],
        [publicWebhookUrl, 'Store is served over secure public HTTPS'],
        [settings.enabled, 'Cashfree Payments enabled for checkout'],
    ];

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.put(updateUrl, { preserveScroll: true });
    };

    const handleTest = () => {
        router.post(testUrl, {}, { preserveScroll: true });
    };

    const toggleCurrency = (currency: string) => {
        const current = form.data.currencies;
        if (current.includes(currency)) {
            if (current.length > 1) {
                form.setData('currencies', current.filter((c) => c !== currency));
            }
        } else {
            form.setData('currencies', [...current, currency]);
        }
    };

    return (
        <>
            <Head title="Cashfree Payments" />
            <AdminPage>
                <PageHeader
                    title="Cashfree Payments"
                    description="Accept UPI (Google Pay, PhonePe, Paytm, BHIM), Net Banking across all major Indian banks, and Cards with Cashfree's developer-first payments platform."
                    actions={
                        <div className="flex items-center gap-2">
                            <Badge variant={settings.test_mode ? 'secondary' : 'default'}>
                                {settings.test_mode ? 'Sandbox mode' : 'Production mode'}
                            </Badge>
                            {settings.ready && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={handleTest}
                                    disabled={form.processing}
                                >
                                    <RefreshCw className="mr-1.5 size-4" />
                                    Test Connection
                                </Button>
                            )}
                        </div>
                    }
                />

                <Card>
                    <CardHeader>
                        <CardTitle>
                            {settings.ready
                                ? 'Cashfree Payments is taking payments'
                                : 'Set up Cashfree Payments'}
                        </CardTitle>
                        <CardDescription>
                            Configure your App ID and Secret Key from the Cashfree Merchant Dashboard.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ul className="grid gap-2 text-sm">
                            {checks.map(([done, label]) => (
                                <li key={label} className="flex items-center gap-2">
                                    {done ? (
                                        <CircleCheck className="size-4 shrink-0 text-primary" />
                                    ) : (
                                        <CircleAlert className="size-4 shrink-0 text-muted-foreground" />
                                    )}
                                    <span>{label}</span>
                                </li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>

                <form onSubmit={handleSubmit} className="grid gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>API Credentials</CardTitle>
                            <CardDescription>
                                Generated under Developers → API Keys in your Cashfree Merchant Dashboard.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-2">
                            <FormField
                                id="app_id"
                                label="App ID (Client ID)"
                                error={form.errors.app_id}
                                hint="Your Cashfree merchant App ID."
                            >
                                <Input
                                    id="app_id"
                                    value={form.data.app_id}
                                    onChange={(e) => form.setData('app_id', e.target.value)}
                                    placeholder="e.g. 1234567890abcdef"
                                />
                            </FormField>

                            <FormField
                                id="secret_key"
                                label="Secret Key (Client Secret)"
                                error={form.errors.secret_key}
                                hint={
                                    settings.secret_key_hint
                                        ? 'Secret Key saved encrypted. Leave blank to keep current.'
                                        : 'Required to sign checkout and verify webhooks.'
                                }
                            >
                                <Input
                                    id="secret_key"
                                    type="password"
                                    autoComplete="new-password"
                                    value={form.data.secret_key}
                                    onChange={(e) => form.setData('secret_key', e.target.value)}
                                    placeholder={settings.secret_key_hint ?? 'Cashfree Secret Key'}
                                />
                            </FormField>

                            <FormField
                                id="label"
                                label="Checkout Label"
                                error={form.errors.label}
                                hint="Payment option title displayed to shoppers."
                            >
                                <Input
                                    id="label"
                                    value={form.data.label}
                                    onChange={(e) => form.setData('label', e.target.value)}
                                />
                            </FormField>

                            <div className="flex flex-col justify-end space-y-4">
                                <div className="flex items-center space-x-2">
                                    <Checkbox
                                        id="test_mode"
                                        checked={form.data.test_mode}
                                        onCheckedChange={(checked) => form.setData('test_mode', Boolean(checked))}
                                    />
                                    <label
                                        htmlFor="test_mode"
                                        className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                                    >
                                        Sandbox Mode (test transactions on sandbox.cashfree.com)
                                    </label>
                                </div>

                                <div className="flex items-center space-x-2">
                                    <Checkbox
                                        id="enabled"
                                        checked={form.data.enabled}
                                        onCheckedChange={(checked) => form.setData('enabled', Boolean(checked))}
                                    />
                                    <label
                                        htmlFor="enabled"
                                        className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                                    >
                                        Enable Cashfree Payments at checkout
                                    </label>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Supported Currencies</CardTitle>
                            <CardDescription>
                                Enable currencies supported by your Cashfree account.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-2 sm:grid-cols-2 md:grid-cols-3">
                            {SUPPORTED_CURRENCIES.map((c) => {
                                const active = form.data.currencies.includes(c.code);
                                return (
                                    <label
                                        key={c.code}
                                        className="flex cursor-pointer items-center space-x-2 rounded border p-3 hover:bg-muted/50"
                                    >
                                        <Checkbox
                                            checked={active}
                                            onCheckedChange={() => toggleCurrency(c.code)}
                                        />
                                        <span className="text-sm font-medium">{c.label}</span>
                                    </label>
                                );
                            })}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Webhook & Return URLs</CardTitle>
                            <CardDescription>
                                Configure these endpoints in your Cashfree Developer dashboard.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <div>
                                <label className="text-xs font-semibold uppercase text-muted-foreground">
                                    Webhook URL (Notify URL)
                                </label>
                                <div className="mt-1 flex items-center gap-2">
                                    <Input readOnly value={webhookUrl} className="font-mono text-xs" />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        onClick={() => copyWebhook(webhookUrl)}
                                    >
                                        <Copy className="size-4" />
                                    </Button>
                                </div>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Add this endpoint under <strong>Developers → Webhooks</strong> with HMAC-SHA256 verification.
                                </p>
                            </div>

                            <div>
                                <label className="text-xs font-semibold uppercase text-muted-foreground">
                                    Return URL (Response)
                                </label>
                                <div className="mt-1 flex items-center gap-2">
                                    <Input readOnly value={responseUrl} className="font-mono text-xs" />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        onClick={() => copyResponse(responseUrl)}
                                    >
                                        <Copy className="size-4" />
                                    </Button>
                                </div>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    The module passes this dynamically per order; no manual dashboard configuration required.
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <div className="flex items-center justify-end gap-3">
                        <Button type="submit" disabled={form.processing}>
                            Save Settings
                        </Button>
                    </div>
                </form>
            </AdminPage>
        </>
    );
}
