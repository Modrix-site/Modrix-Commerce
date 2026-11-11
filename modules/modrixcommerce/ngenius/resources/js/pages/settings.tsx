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

type NGeniusSettings = {
    enabled: boolean;
    outlet_id: string | null;
    has_api_key: boolean;
    has_webhook_secret: boolean;
    test_mode: boolean;
    action: string;
    label: string;
    currencies: string[];
    ready: boolean;
};

export default function NGeniusSettingsPage({
    settings,
    webhookUrl,
    responseUrl,
    publicWebhookUrl,
    updateUrl,
    testUrl,
}: {
    settings: NGeniusSettings;
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
        outlet_id: settings.outlet_id ?? '',
        test_mode: settings.test_mode,
        action: settings.action,
        label: settings.label,
        currencies: settings.currencies,
        api_key: '',
        webhook_secret: '',
    });

    const checks: [boolean, string][] = [
        [
            Boolean(settings.outlet_id),
            'N-Genius Outlet Reference ID configured',
        ],
        [settings.has_api_key, 'Service Account API Key saved securely'],
        [publicWebhookUrl, 'Store is served over secure public HTTPS'],
        [settings.enabled, 'Network International enabled at checkout'],
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
            <Head title="Network International (N-Genius)" />
            <AdminPage>
                <PageHeader
                    title="Network International (N-Genius)"
                    description="Network International N-Genius payment gateway for UAE and the Middle East with hosted checkout, 3DS, Apple Pay, card payments, and refunds."
                    actions={
                        <div className="flex items-center gap-2">
                            <Badge
                                variant={
                                    settings.test_mode ? 'secondary' : 'default'
                                }
                            >
                                {settings.test_mode
                                    ? 'Sandbox / Test Mode'
                                    : 'Live / Production'}
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
                                ? 'N-Genius is active and taking payments'
                                : 'Set up Network International (N-Genius)'}
                        </CardTitle>
                        <CardDescription>
                            Enter your Outlet Reference ID and Service Account
                            API Key from the Network International portal.
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
                            <CardTitle>API Credentials & Outlet</CardTitle>
                            <CardDescription>
                                Supplied in your Network International merchant
                                portal under Developer / Integration settings.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-2">
                            <FormField
                                id="outlet_id"
                                label="Outlet Reference ID"
                                error={form.errors.outlet_id}
                                hint="Your UUID outlet reference from N-Genius (e.g. 5240212f-9d95-46f3-a9d2-7c...)."
                            >
                                <Input
                                    id="outlet_id"
                                    value={form.data.outlet_id}
                                    onChange={(e) =>
                                        form.setData(
                                            'outlet_id',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="e.g. 5240212f-9d95-46f3-..."
                                />
                            </FormField>

                            <FormField
                                id="api_key"
                                label="Service Account API Key"
                                error={form.errors.api_key}
                                hint={
                                    settings.has_api_key
                                        ? 'API Key saved encrypted. Leave blank to keep existing key.'
                                        : 'Service Account API key used to authenticate with N-Genius identity endpoint.'
                                }
                            >
                                <Input
                                    id="api_key"
                                    type="password"
                                    autoComplete="new-password"
                                    value={form.data.api_key}
                                    onChange={(e) =>
                                        form.setData('api_key', e.target.value)
                                    }
                                    placeholder={
                                        settings.has_api_key
                                            ? '••••••••••••••••'
                                            : 'N-Genius API Key'
                                    }
                                />
                            </FormField>

                            <FormField
                                id="webhook_secret"
                                label="Webhook Secret (Optional)"
                                error={form.errors.webhook_secret}
                                hint={
                                    settings.has_webhook_secret
                                        ? 'Webhook secret saved. Leave blank to keep unchanged.'
                                        : 'Optional shared secret to verify HMAC signature on incoming webhooks.'
                                }
                            >
                                <Input
                                    id="webhook_secret"
                                    type="password"
                                    autoComplete="new-password"
                                    value={form.data.webhook_secret}
                                    onChange={(e) =>
                                        form.setData(
                                            'webhook_secret',
                                            e.target.value,
                                        )
                                    }
                                    placeholder={
                                        settings.has_webhook_secret
                                            ? '••••••••••••••••'
                                            : 'Optional Webhook Secret'
                                    }
                                />
                            </FormField>

                            <FormField
                                id="action"
                                label="Payment Action"
                                error={form.errors.action}
                                hint="PURCHASE directly captures the funds; AUTH authorizes for later capture."
                            >
                                <Input
                                    id="action"
                                    value={form.data.action}
                                    onChange={(e) =>
                                        form.setData('action', e.target.value)
                                    }
                                    placeholder="PURCHASE or AUTH"
                                />
                            </FormField>

                            <FormField
                                id="label"
                                label="Checkout Label"
                                error={form.errors.label}
                                hint="Customer-facing payment method label displayed at checkout."
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
                                label="Supported Currencies"
                                error={form.errors.currencies}
                                hint="Comma-separated ISO-4217 currencies (e.g. AED, SAR, USD, EUR, OMR, BHD, KWD)."
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
                                        Enable Sandbox Mode (tests against
                                        api-gateway.sandbox.ngenius-payments.com)
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
                                        Enable Network International (N-Genius)
                                        payment gateway for customer checkout
                                    </label>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Webhook & Response URLs</CardTitle>
                            <CardDescription>
                                Register these URLs in your Network
                                International portal under Outlet / Webhook
                                notifications.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <div>
                                <label className="text-xs font-semibold text-muted-foreground uppercase">
                                    Return Response URL
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
