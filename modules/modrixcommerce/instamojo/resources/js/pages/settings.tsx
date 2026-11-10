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

type InstamojoSettings = {
    enabled: boolean;
    has_api_key: boolean;
    has_auth_token: boolean;
    has_private_salt: boolean;
    test_mode: boolean;
    label: string;
    currencies: string[];
    ready: boolean;
};

export default function InstamojoSettingsPage({
    settings,
    webhookUrl,
    responseUrl,
    publicWebhookUrl,
    updateUrl,
    testUrl,
}: {
    settings: InstamojoSettings;
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
        auth_token: '',
        private_salt: '',
    });

    const checks: [boolean, string][] = [
        [settings.has_api_key, 'Instamojo API Key configured'],
        [settings.has_auth_token, 'Instamojo Auth Token configured'],
        [settings.has_private_salt, 'Private Salt for MAC verification saved'],
        [publicWebhookUrl, 'Store is served over secure public HTTPS'],
        [settings.enabled, 'Instamojo enabled at checkout'],
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
            <Head title="Instamojo Payments" />
            <AdminPage>
                <PageHeader
                    title="Instamojo Payments"
                    description="Accept UPI, Cards, Net Banking, and Wallets using Instamojo India."
                    actions={
                        <div className="flex items-center gap-2">
                            <Badge variant={settings.test_mode ? 'secondary' : 'default'}>
                                {settings.test_mode ? 'Test Mode (test.instamojo.com)' : 'Live Mode (instamojo.com)'}
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
                                ? 'Instamojo is ready and accepting payments'
                                : 'Set up Instamojo Payments'}
                        </CardTitle>
                        <CardDescription>
                            Enter your API Key and Auth Token from your Instamojo Dashboard (API &amp; Plugins).
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
                                Generated under API &amp; Plugins in your Instamojo merchant account.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-2">
                            <FormField
                                id="api_key"
                                label="API Key"
                                error={form.errors.api_key}
                                hint={
                                    settings.has_api_key
                                        ? 'API Key saved encrypted. Leave blank to keep existing key.'
                                        : 'Your private Instamojo API Key.'
                                }
                            >
                                <Input
                                    id="api_key"
                                    type="password"
                                    autoComplete="new-password"
                                    value={form.data.api_key}
                                    onChange={(e) => form.setData('api_key', e.target.value)}
                                    placeholder={settings.has_api_key ? '••••••••••••••••' : 'Instamojo API Key'}
                                />
                            </FormField>

                            <FormField
                                id="auth_token"
                                label="Auth Token"
                                error={form.errors.auth_token}
                                hint={
                                    settings.has_auth_token
                                        ? 'Auth Token saved encrypted. Leave blank to keep existing token.'
                                        : 'Your private Instamojo Auth Token.'
                                }
                            >
                                <Input
                                    id="auth_token"
                                    type="password"
                                    autoComplete="new-password"
                                    value={form.data.auth_token}
                                    onChange={(e) => form.setData('auth_token', e.target.value)}
                                    placeholder={settings.has_auth_token ? '••••••••••••••••' : 'Instamojo Auth Token'}
                                />
                            </FormField>

                            <FormField
                                id="private_salt"
                                label="Private Salt (Optional for Webhook MAC)"
                                error={form.errors.private_salt}
                                hint={
                                    settings.has_private_salt
                                        ? 'Private Salt saved encrypted. Leave blank to keep unchanged.'
                                        : 'Used for HMAC-SHA1 verification on incoming webhook notifications.'
                                }
                            >
                                <Input
                                    id="private_salt"
                                    type="password"
                                    autoComplete="new-password"
                                    value={form.data.private_salt}
                                    onChange={(e) => form.setData('private_salt', e.target.value)}
                                    placeholder={settings.has_private_salt ? '••••••••••••••••' : 'Private Salt'}
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
                                        Enable Test Mode (submits requests to test.instamojo.com)
                                    </label>
                                </div>

                                <div className="flex items-center space-x-2">
                                    <Checkbox
                                        id="enabled"
                                        checked={form.data.enabled}
                                        onCheckedChange={(checked) => form.setData('enabled', Boolean(checked))}
                                    />
                                    <label htmlFor="enabled" className="text-sm font-medium leading-none">
                                        Enable Instamojo Payments for customer checkout
                                    </label>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Webhook & Response URLs</CardTitle>
                            <CardDescription>
                                Automatically attached to created Payment Requests and can be registered in your Instamojo portal.
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
