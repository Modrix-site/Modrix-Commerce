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

type MyFatoorahSettings = {
    enabled: boolean;
    has_api_key: boolean;
    has_webhook_secret: boolean;
    test_mode: boolean;
    label: string;
    currencies: string[];
    ready: boolean;
};

export default function MyFatoorahSettingsPage({
    settings,
    webhookUrl,
    responseUrl,
    publicWebhookUrl,
    updateUrl,
    testUrl,
}: {
    settings: MyFatoorahSettings;
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
        [settings.has_api_key, 'MyFatoorah API Key saved encrypted'],
        [
            settings.has_webhook_secret,
            'Webhook secret configured for HMAC verification',
        ],
        [publicWebhookUrl, 'Store is served over secure public HTTPS'],
        [settings.enabled, 'MyFatoorah enabled at checkout'],
    ];

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.put(updateUrl);
    };

    const handleTest = () => {
        router.post(testUrl);
    };

    return (
        <>
            <Head title="MyFatoorah Payment Settings" />

            <AdminPage>
                <PageHeader
                    title="MyFatoorah Payments"
                    description="Configure MyFatoorah payment gateway for UAE & GCC supporting Cards, Apple Pay, Google Pay, Mada, and KNET."
                    actions={
                        <div className="flex items-center gap-2">
                            {settings.ready ? (
                                <Badge
                                    variant="outline"
                                    className="border-emerald-500/40 text-emerald-600"
                                >
                                    Ready
                                </Badge>
                            ) : (
                                <Badge
                                    variant="outline"
                                    className="text-muted-foreground"
                                >
                                    Incomplete
                                </Badge>
                            )}
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={handleTest}
                                disabled={!settings.ready}
                            >
                                <RefreshCw className="mr-2 size-4" />
                                Test Connection
                            </Button>
                        </div>
                    }
                />

                <Card className="mb-6">
                    <CardHeader>
                        <CardTitle>
                            {settings.ready
                                ? 'MyFatoorah is ready and accepting payments'
                                : 'Set up MyFatoorah Payments'}
                        </CardTitle>
                        <CardDescription>
                            Configure your API Token from the MyFatoorah
                            Merchant Portal.
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
                                Found in your MyFatoorah Portal under
                                Integration Settings / API Keys.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-2">
                            <FormField
                                id="api_key"
                                label="API Token (Key)"
                                error={form.errors.api_key}
                                hint={
                                    settings.has_api_key
                                        ? 'API Key saved encrypted. Leave blank to keep existing key.'
                                        : 'Bearer API Token generated in MyFatoorah portal.'
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
                                            : 'MyFatoorah API Token'
                                    }
                                />
                            </FormField>

                            <FormField
                                id="webhook_secret"
                                label="Webhook Secret (Optional)"
                                error={form.errors.webhook_secret}
                                hint={
                                    settings.has_webhook_secret
                                        ? 'Secret saved encrypted. Leave blank to keep existing secret.'
                                        : 'Used to verify MyFatoorah-Signature header on webhook events.'
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
                                            : 'Webhook Secret'
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
                                hint="Comma-separated ISO currencies (e.g. AED, SAR, KWD, USD)."
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
                                        to apitest.myfatoorah.com)
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
                                        Enable MyFatoorah Payments for customer
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
                                Set these URLs in your MyFatoorah Portal or rely
                                on automatic redirection.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <div>
                                <label className="text-xs font-semibold text-muted-foreground uppercase">
                                    Callback / Response URL (CallBackUrl /
                                    ErrorUrl)
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
