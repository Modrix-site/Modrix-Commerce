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
import { Textarea } from '@/components/ui/textarea';
import { useClipboard } from '@/hooks/use-clipboard';

type PaybySettings = {
    enabled: boolean;
    partner_id: string | null;
    has_private_key: boolean;
    has_payby_public_key: boolean;
    test_mode: boolean;
    label: string;
    currencies: string[];
    ready: boolean;
};

export default function PaybySettingsPage({
    settings,
    webhookUrl,
    responseUrl,
    publicWebhookUrl,
    updateUrl,
    testUrl,
}: {
    settings: PaybySettings;
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
        partner_id: settings.partner_id ?? '',
        test_mode: settings.test_mode,
        label: settings.label,
        currencies: settings.currencies,
        private_key: '',
        payby_public_key: '',
    });

    const checks: [boolean, string][] = [
        [Boolean(settings.partner_id), 'PayBy Partner ID configured'],
        [settings.has_private_key, 'Merchant RSA Private Key saved encrypted'],
        [publicWebhookUrl, 'Store is served over secure public HTTPS'],
        [settings.enabled, 'PayBy enabled at checkout'],
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
            <Head title="PayBy (Astra Tech)" />
            <AdminPage>
                <PageHeader
                    title="PayBy (Astra Tech)"
                    description="Accept cards, Botim wallet, Apple Pay, and QR code payments in the UAE with PayBy."
                    actions={
                        <div className="flex items-center gap-2">
                            <Badge
                                variant={
                                    settings.test_mode ? 'secondary' : 'default'
                                }
                            >
                                {settings.test_mode
                                    ? 'UAT / Sandbox'
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
                                ? 'PayBy is active and accepting payments'
                                : 'Set up PayBy (Astra Tech)'}
                        </CardTitle>
                        <CardDescription>
                            Configure your Partner ID and RSA cryptographic keys
                            from the PayBy Merchant Portal.
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
                            <CardTitle>
                                Merchant Credentials &amp; RSA Keys
                            </CardTitle>
                            <CardDescription>
                                Generated under API Keys in your PayBy merchant
                                dashboard.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-2">
                            <FormField
                                id="partner_id"
                                label="Partner ID"
                                error={form.errors.partner_id}
                                hint="Your numeric PayBy merchant Partner ID (e.g. 200000018128)."
                            >
                                <Input
                                    id="partner_id"
                                    value={form.data.partner_id}
                                    onChange={(e) =>
                                        form.setData(
                                            'partner_id',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="e.g. 200000018128"
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

                            <div className="md:col-span-2">
                                <FormField
                                    id="private_key"
                                    label="Merchant RSA Private Key"
                                    error={form.errors.private_key}
                                    hint={
                                        settings.has_private_key
                                            ? 'Private Key saved encrypted. Leave blank to keep existing key.'
                                            : 'Used to generate RSA-SHA256 signatures for outgoing requests.'
                                    }
                                >
                                    <Textarea
                                        id="private_key"
                                        rows={4}
                                        value={form.data.private_key}
                                        onChange={(e) =>
                                            form.setData(
                                                'private_key',
                                                e.target.value,
                                            )
                                        }
                                        placeholder={
                                            settings.has_private_key
                                                ? '••••••••••••••••'
                                                : '-----BEGIN RSA PRIVATE KEY-----...'
                                        }
                                        className="font-mono text-xs"
                                    />
                                </FormField>
                            </div>

                            <div className="md:col-span-2">
                                <FormField
                                    id="payby_public_key"
                                    label="PayBy Public Key (Optional)"
                                    error={form.errors.payby_public_key}
                                    hint={
                                        settings.has_payby_public_key
                                            ? 'PayBy Public Key saved. Leave blank to keep unchanged.'
                                            : 'Used to verify incoming signatures on PayBy webhooks.'
                                    }
                                >
                                    <Textarea
                                        id="payby_public_key"
                                        rows={3}
                                        value={form.data.payby_public_key}
                                        onChange={(e) =>
                                            form.setData(
                                                'payby_public_key',
                                                e.target.value,
                                            )
                                        }
                                        placeholder={
                                            settings.has_payby_public_key
                                                ? '••••••••••••••••'
                                                : '-----BEGIN PUBLIC KEY-----...'
                                        }
                                        className="font-mono text-xs"
                                    />
                                </FormField>
                            </div>

                            <FormField
                                id="currencies"
                                label="Supported Currencies"
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
                                        Enable UAT / Sandbox Mode (tests against
                                        uat.test2pay.com)
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
                                        Enable PayBy payment gateway for
                                        customer checkout
                                    </label>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Webhook &amp; Return URLs</CardTitle>
                            <CardDescription>
                                Register these URLs in your PayBy developer
                                settings or application profile.
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
