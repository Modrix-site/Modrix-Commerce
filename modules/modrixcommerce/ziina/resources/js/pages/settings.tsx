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

type ZiinaSettings = {
    enabled: boolean;
    has_api_key: boolean;
    has_webhook_secret: boolean;
    test_mode: boolean;
    label: string;
    currencies: string[];
    ready: boolean;
};

export default function ZiinaSettingsPage({
    settings,
    webhookUrl,
    responseUrl,
    publicWebhookUrl,
    updateUrl,
    testUrl,
}: {
    settings: ZiinaSettings;
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
        test_mode: settings.test_mode,
        label: settings.label,
        currencies: settings.currencies,
        api_key: '',
        webhook_secret: '',
    });

    const checks: [boolean, string][] = [
        [settings.has_api_key, 'Ziina API Token saved encrypted'],
        [publicWebhookUrl, 'Store is served over secure public HTTPS'],
        [settings.enabled, 'Ziina enabled at checkout'],
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
            <Head title="Ziina Payment Gateway" />
            <AdminPage>
                <PageHeader
                    title="Ziina Payment Gateway"
                    description="Licensed by the Central Bank of the UAE. Accept Apple Pay, Google Pay, and cards in AED."
                    actions={
                        <div className="flex items-center gap-2">
                            <Badge variant={settings.test_mode ? 'secondary' : 'default'}>
                                {settings.test_mode ? 'Test Mode' : 'Live Mode'}
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
                                ? 'Ziina is ready and taking payments'
                                : 'Set up Ziina Payment Gateway'}
                        </CardTitle>
                        <CardDescription>
                            Configure your API token from the Ziina Merchant Portal.
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
                                Generated in your Ziina Merchant Dashboard under Settings &gt; Developers.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-2">
                            <FormField
                                id="api_key"
                                label="API Token"
                                error={form.errors.api_key}
                                hint={
                                    settings.has_api_key
                                        ? 'API Token saved encrypted. Leave blank to keep existing token.'
                                        : 'Personal access token used to authenticate requests to Ziina API v2.'
                                }
                            >
                                <Input
                                    id="api_key"
                                    type="password"
                                    autoComplete="new-password"
                                    value={form.data.api_key}
                                    onChange={(e) => form.setData('api_key', e.target.value)}
                                    placeholder={settings.has_api_key ? '••••••••••••••••' : 'Ziina API Token'}
                                />
                            </FormField>

                            <FormField
                                id="webhook_secret"
                                label="Webhook Secret (Optional)"
                                error={form.errors.webhook_secret}
                                hint={
                                    settings.has_webhook_secret
                                        ? 'Webhook secret saved. Leave blank to keep unchanged.'
                                        : 'Shared secret used to verify incoming X-Hmac-Signature headers.'
                                }
                            >
                                <Input
                                    id="webhook_secret"
                                    type="password"
                                    autoComplete="new-password"
                                    value={form.data.webhook_secret}
                                    onChange={(e) => form.setData('webhook_secret', e.target.value)}
                                    placeholder={settings.has_webhook_secret ? '••••••••••••••••' : 'Webhook Secret'}
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
                                    onChange={(e) => form.setData('label', e.target.value)}
                                />
                            </FormField>

                            <FormField
                                id="currencies"
                                label="Currencies"
                                error={form.errors.currencies}
                                hint="Comma-separated currencies (e.g. AED, USD)."
                            >
                                <Input
                                    id="currencies"
                                    value={form.data.currencies.join(', ')}
                                    onChange={(e) =>
                                        form.setData(
                                            'currencies',
                                            e.target.value
                                                .split(',')
                                                .map((c) => c.trim().toUpperCase())
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
                                        onCheckedChange={(checked) => form.setData('test_mode', Boolean(checked))}
                                    />
                                    <label htmlFor="test_mode" className="text-sm font-medium leading-none">
                                        Enable Test Mode (creates payment intents with test=true)
                                    </label>
                                </div>

                                <div className="flex items-center space-x-2">
                                    <Checkbox
                                        id="enabled"
                                        checked={form.data.enabled}
                                        onCheckedChange={(checked) => form.setData('enabled', Boolean(checked))}
                                    />
                                    <label htmlFor="enabled" className="text-sm font-medium leading-none">
                                        Enable Ziina Payment Gateway for customer checkout
                                    </label>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Webhook & Response URLs</CardTitle>
                            <CardDescription>
                                Register these URLs in your Ziina Merchant Dashboard under Webhooks.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <div>
                                <label className="text-xs font-semibold uppercase text-muted-foreground">
                                    Return Response URL
                                </label>
                                <div className="mt-1 flex items-center gap-2">
                                    <Input readOnly value={responseUrl} className="font-mono text-xs" />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => copyResponse(responseUrl)}
                                    >
                                        <Copy className="size-4" />
                                    </Button>
                                </div>
                            </div>

                            <div>
                                <label className="text-xs font-semibold uppercase text-muted-foreground">
                                    Webhook Notification URL
                                </label>
                                <div className="mt-1 flex items-center gap-2">
                                    <Input readOnly value={webhookUrl} className="font-mono text-xs" />
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
