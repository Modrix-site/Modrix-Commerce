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

type PhonePeSettings = {
    enabled: boolean;
    merchant_id: string;
    salt_index: string;
    test_mode: boolean;
    label: string;
    currencies: string[];
    salt_key_hint: string | null;
    ready: boolean;
};

export default function PhonePeSettingsPage({
    settings,
    webhookUrl,
    responseUrl,
    publicWebhookUrl,
    updateUrl,
    testUrl,
}: {
    settings: PhonePeSettings;
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
        merchant_id: settings.merchant_id,
        salt_index: settings.salt_index,
        test_mode: settings.test_mode,
        label: settings.label,
        currencies: settings.currencies,
        salt_key: '',
    });

    const checks: [boolean, string][] = [
        [settings.merchant_id !== '', 'PhonePe Merchant ID configured'],
        [settings.salt_key_hint !== null, 'Salt Key saved encrypted'],
        [publicWebhookUrl, 'Store is served over secure public HTTPS'],
        [settings.enabled, 'PhonePe enabled at checkout'],
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
            <Head title="PhonePe Payment Gateway" />
            <AdminPage>
                <PageHeader
                    title="PhonePe Payment Gateway"
                    description="India's leading payments platform. Accept UPI (PhonePe Intent, QR code, Collect), Google Pay, Net Banking across all banks, and Cards."
                    actions={
                        <div className="flex items-center gap-2">
                            <Badge
                                variant={
                                    settings.test_mode ? 'secondary' : 'default'
                                }
                            >
                                {settings.test_mode
                                    ? 'UAT / Sandbox mode'
                                    : 'Production mode'}
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
                                ? 'PhonePe is taking payments'
                                : 'Set up PhonePe'}
                        </CardTitle>
                        <CardDescription>
                            Configure your Merchant ID and Salt Key from the
                            PhonePe Business dashboard.
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
                            <CardTitle>Account Credentials</CardTitle>
                            <CardDescription>
                                Supplied by PhonePe Merchant Onboarding or
                                Developer Settings.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-2">
                            <FormField
                                id="merchant_id"
                                label="Merchant ID"
                                error={form.errors.merchant_id}
                                hint="Your PhonePe merchant identifier (e.g. PGTESTPAYUAT)."
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
                                    placeholder="e.g. PGTESTPAYUAT"
                                />
                            </FormField>

                            <FormField
                                id="salt_key"
                                label="Salt Key"
                                error={form.errors.salt_key}
                                hint={
                                    settings.salt_key_hint
                                        ? 'Salt Key saved encrypted. Leave blank to keep current.'
                                        : 'Secret salt key used to generate X-VERIFY checksums.'
                                }
                            >
                                <Input
                                    id="salt_key"
                                    type="password"
                                    autoComplete="new-password"
                                    value={form.data.salt_key}
                                    onChange={(e) =>
                                        form.setData('salt_key', e.target.value)
                                    }
                                    placeholder={
                                        settings.salt_key_hint ??
                                        'PhonePe Salt Key'
                                    }
                                />
                            </FormField>

                            <FormField
                                id="salt_index"
                                label="Salt Index"
                                error={form.errors.salt_index}
                                hint="Salt index assigned with your salt key (default is 1)."
                            >
                                <Input
                                    id="salt_index"
                                    value={form.data.salt_index}
                                    onChange={(e) =>
                                        form.setData(
                                            'salt_index',
                                            e.target.value,
                                        )
                                    }
                                />
                            </FormField>

                            <FormField
                                id="label"
                                label="Checkout Label"
                                error={form.errors.label}
                                hint="Method name displayed to shoppers during checkout."
                            >
                                <Input
                                    id="label"
                                    value={form.data.label}
                                    onChange={(e) =>
                                        form.setData('label', e.target.value)
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
                                        className="text-sm leading-none font-medium peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                                    >
                                        Sandbox / UAT Mode (uses
                                        api-preprod.phonepe.com for testing)
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
                                        className="text-sm leading-none font-medium peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                                    >
                                        Enable PhonePe at checkout
                                    </label>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Webhook & Return URLs</CardTitle>
                            <CardDescription>
                                Set these endpoints in your PhonePe merchant
                                profile.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <div>
                                <label className="text-xs font-semibold text-muted-foreground uppercase">
                                    Server-to-Server Callback URL (Webhook)
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
                                        size="icon"
                                        onClick={() => copyWebhook(webhookUrl)}
                                    >
                                        <Copy className="size-4" />
                                    </Button>
                                </div>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Register this in the PhonePe Portal as your
                                    server callback notification endpoint.
                                </p>
                            </div>

                            <div>
                                <label className="text-xs font-semibold text-muted-foreground uppercase">
                                    Browser Redirect / Return URL
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
                                        size="icon"
                                        onClick={() =>
                                            copyResponse(responseUrl)
                                        }
                                    >
                                        <Copy className="size-4" />
                                    </Button>
                                </div>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    The module passes this dynamically per
                                    checkout; no manual configuration needed.
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
