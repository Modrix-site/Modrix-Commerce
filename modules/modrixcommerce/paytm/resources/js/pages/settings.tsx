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

type PaytmSettings = {
    enabled: boolean;
    mid: string | null;
    has_merchant_key: boolean;
    test_mode: boolean;
    website: string;
    label: string;
    currencies: string[];
    ready: boolean;
};

export default function PaytmSettingsPage({
    settings,
    webhookUrl,
    responseUrl,
    publicWebhookUrl,
    updateUrl,
    testUrl,
}: {
    settings: PaytmSettings;
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
        mid: settings.mid ?? '',
        test_mode: settings.test_mode,
        website: settings.website,
        label: settings.label,
        currencies: settings.currencies,
        merchant_key: '',
    });

    const checks: [boolean, string][] = [
        [Boolean(settings.mid), 'Paytm Merchant ID (MID) configured'],
        [settings.has_merchant_key, 'Merchant Key saved encrypted'],
        [publicWebhookUrl, 'Store is served over secure public HTTPS'],
        [settings.enabled, 'Paytm enabled at checkout'],
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
            <Head title="Paytm Payment Gateway" />
            <AdminPage>
                <PageHeader
                    title="Paytm Payment Gateway"
                    description="Accept Paytm UPI, Paytm Wallet, Net Banking, and Cards with Paytm's All-in-One Payment Gateway."
                    actions={
                        <div className="flex items-center gap-2">
                            <Badge
                                variant={
                                    settings.test_mode ? 'secondary' : 'default'
                                }
                            >
                                {settings.test_mode
                                    ? 'Staging / Test Mode'
                                    : 'Production Mode'}
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
                                ? 'Paytm is ready and accepting payments'
                                : 'Set up Paytm Payment Gateway'}
                        </CardTitle>
                        <CardDescription>
                            Configure your Merchant ID (MID) and Merchant Key
                            from the Paytm Dashboard.
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
                                Supplied in your Paytm Dashboard under Developer
                                Settings &gt; API Keys.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-2">
                            <FormField
                                id="mid"
                                label="Merchant ID (MID)"
                                error={form.errors.mid}
                                hint="Your unique Paytm Merchant ID (e.g. DIY12345678901234)."
                            >
                                <Input
                                    id="mid"
                                    value={form.data.mid}
                                    onChange={(e) =>
                                        form.setData('mid', e.target.value)
                                    }
                                    placeholder="e.g. YOUR_MID_HERE"
                                />
                            </FormField>

                            <FormField
                                id="merchant_key"
                                label="Merchant Key"
                                error={form.errors.merchant_key}
                                hint={
                                    settings.has_merchant_key
                                        ? 'Merchant Key saved encrypted. Leave blank to keep existing key.'
                                        : 'Secret Merchant Key used for generating and verifying PaytmChecksum.'
                                }
                            >
                                <Input
                                    id="merchant_key"
                                    type="password"
                                    autoComplete="new-password"
                                    value={form.data.merchant_key}
                                    onChange={(e) =>
                                        form.setData(
                                            'merchant_key',
                                            e.target.value,
                                        )
                                    }
                                    placeholder={
                                        settings.has_merchant_key
                                            ? '••••••••••••••••'
                                            : 'Paytm Merchant Key'
                                    }
                                />
                            </FormField>

                            <FormField
                                id="website"
                                label="Website Name"
                                error={form.errors.website}
                                hint="Typically WEBSTAGING for test accounts or DEFAULT for production."
                            >
                                <Input
                                    id="website"
                                    value={form.data.website}
                                    onChange={(e) =>
                                        form.setData('website', e.target.value)
                                    }
                                    placeholder="WEBSTAGING or DEFAULT"
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
                                hint="Comma-separated currencies (e.g. INR)."
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
                                        Enable Staging Mode (tests against
                                        securegw-stage.paytm.in)
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
                                        Enable Paytm Payment Gateway for
                                        customer checkout
                                    </label>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Webhook & Response URLs</CardTitle>
                            <CardDescription>
                                Register these URLs in your Paytm Dashboard
                                under Webhooks / Notifications.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <div>
                                <label className="text-xs font-semibold text-muted-foreground uppercase">
                                    Callback / Response URL
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
