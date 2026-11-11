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

type PayuSettings = {
    enabled: boolean;
    merchant_key: string | null;
    has_salt: boolean;
    test_mode: boolean;
    label: string;
    currencies: string[];
    ready: boolean;
};

export default function PayuSettingsPage({
    settings,
    webhookUrl,
    responseUrl,
    publicWebhookUrl,
    updateUrl,
    testUrl,
}: {
    settings: PayuSettings;
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
        merchant_key: settings.merchant_key ?? '',
        test_mode: settings.test_mode,
        label: settings.label,
        currencies: settings.currencies,
        salt: '',
    });

    const checks: [boolean, string][] = [
        [Boolean(settings.merchant_key), 'PayU Merchant Key configured'],
        [settings.has_salt, 'Merchant Salt saved encrypted'],
        [publicWebhookUrl, 'Store is served over secure public HTTPS'],
        [settings.enabled, 'PayU enabled at checkout'],
    ];

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.put(updateUrl, { preserveScroll: true });
    };

    const handleTest = () => {
        router.post(testUrl, {}, { preserveScroll: true });
    };

    return (
        <>
            <Head title="PayU Payments" />
            <AdminPage>
                <PageHeader
                    title="PayU Payments"
                    description="Accept cards, UPI, Net Banking across all Indian banks, and Wallets using PayU India."
                    actions={
                        <div className="flex items-center gap-2">
                            <Badge
                                variant={
                                    settings.test_mode ? 'secondary' : 'default'
                                }
                            >
                                {settings.test_mode
                                    ? 'Test Mode (test.payu.in)'
                                    : 'Production (secure.payu.in)'}
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
                                ? 'PayU is ready and accepting payments'
                                : 'Set up PayU Payments'}
                        </CardTitle>
                        <CardDescription>
                            Configure your Merchant Key and Salt from the PayU
                            Dashboard.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ul className="grid gap-2 text-sm">
                            {checks.map(([done, label]) => (
                                <li
                                    key={label}
                                    className="flex items-center gap-2"
                                >
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
                            <CardTitle>Merchant Credentials</CardTitle>
                            <CardDescription>
                                Found in your PayU Dashboard under Integration
                                Details / API Credentials.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-2">
                            <FormField
                                id="merchant_key"
                                label="Merchant Key"
                                error={form.errors.merchant_key}
                                hint="Your PayU Key (e.g. gtKFFx or similar)."
                            >
                                <Input
                                    id="merchant_key"
                                    value={form.data.merchant_key}
                                    onChange={(e) =>
                                        form.setData(
                                            'merchant_key',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="e.g. YOUR_MERCHANT_KEY"
                                />
                            </FormField>

                            <FormField
                                id="salt"
                                label="Merchant Salt"
                                error={form.errors.salt}
                                hint={
                                    settings.has_salt
                                        ? 'Salt saved encrypted. Leave blank to keep existing salt.'
                                        : 'Secret Salt used for calculating and validating SHA-512 hashes.'
                                }
                            >
                                <Input
                                    id="salt"
                                    type="password"
                                    autoComplete="new-password"
                                    value={form.data.salt}
                                    onChange={(e) =>
                                        form.setData('salt', e.target.value)
                                    }
                                    placeholder={
                                        settings.has_salt
                                            ? '••••••••••••••••'
                                            : 'PayU Merchant Salt'
                                    }
                                />
                            </FormField>

                            <FormField
                                id="label"
                                label="Checkout Label"
                                error={form.errors.label}
                                hint="Payment method name presented to customers during checkout."
                            >
                                <Input
                                    id="label"
                                    value={form.data.label}
                                    onChange={(e) =>
                                        form.setData('label', e.target.value)
                                    }
                                />
                            </FormField>

                            <FormField
                                id="currencies"
                                label="Currencies"
                                error={form.errors.currencies}
                                hint="Comma-separated ISO currencies (e.g. INR)."
                            >
                                <Input
                                    id="currencies"
                                    value={form.data.currencies.join(', ')}
                                    onChange={(e) =>
                                        form.setData(
                                            'currencies',
                                            e.target.value
                                                .split(',')
                                                .map((c) =>
                                                    c.trim().toUpperCase(),
                                                )
                                                .filter(Boolean),
                                        )
                                    }
                                />
                            </FormField>

                            <div className="flex flex-col justify-end space-y-4 md:col-span-2">
                                <div className="flex items-center space-x-2">
                                    <Checkbox
                                        id="test_mode"
                                        checked={form.data.test_mode}
                                        onCheckedChange={(checked) =>
                                            form.setData(
                                                'test_mode',
                                                Boolean(checked),
                                            )
                                        }
                                    />
                                    <label
                                        htmlFor="test_mode"
                                        className="text-sm leading-none font-medium"
                                    >
                                        Enable Test Mode (submits transactions
                                        to test.payu.in)
                                    </label>
                                </div>

                                <div className="flex items-center space-x-2">
                                    <Checkbox
                                        id="enabled"
                                        checked={form.data.enabled}
                                        onCheckedChange={(checked) =>
                                            form.setData(
                                                'enabled',
                                                Boolean(checked),
                                            )
                                        }
                                    />
                                    <label
                                        htmlFor="enabled"
                                        className="text-sm leading-none font-medium"
                                    >
                                        Enable PayU Payments for customer
                                        checkout
                                    </label>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Return & Webhook URLs</CardTitle>
                            <CardDescription>
                                Set these URLs in your PayU Dashboard or use
                                default callback routing.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <div>
                                <label className="text-xs font-semibold text-muted-foreground uppercase">
                                    Callback / Response URL (surl / furl)
                                </label>
                                <div className="mt-1 flex items-center gap-2">
                                    <Input
                                        readOnly
                                        value={responseUrl}
                                        className="font-mono text-xs"
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            copyResponse(responseUrl)
                                        }
                                    >
                                        <Copy className="size-4" />
                                    </Button>
                                </div>
                            </div>

                            <div>
                                <label className="text-xs font-semibold text-muted-foreground uppercase">
                                    Webhook Notification URL
                                </label>
                                <div className="mt-1 flex items-center gap-2">
                                    <Input
                                        readOnly
                                        value={webhookUrl}
                                        className="font-mono text-xs"
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => copyWebhook(webhookUrl)}
                                    >
                                        <Copy className="size-4" />
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <div className="flex justify-end gap-3">
                        <Button type="submit" disabled={form.processing}>
                            Save Changes
                        </Button>
                    </div>
                </form>
            </AdminPage>
        </>
    );
}
