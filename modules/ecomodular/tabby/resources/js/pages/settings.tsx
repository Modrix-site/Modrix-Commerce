import { Form, Head } from '@inertiajs/react';
import { CircleAlert, CircleCheck, Copy } from 'lucide-react';
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

type TabbySettings = {
    enabled: boolean;
    public_key: string | null;
    merchant_code: string | null;
    secret_key_hint: string | null;
    mode: 'test' | 'live' | null;
    webhook_registered: boolean;
    currency: string;
    market: string | null;
    dashboard: string | null;
    ready: boolean;
};

export default function TabbySettingsPage({
    settings,
    webhookUrl,
    publicWebhookUrl,
    updateUrl,
    testUrl,
    webhookRegisterUrl,
}: {
    settings: TabbySettings;
    webhookUrl: string;
    publicWebhookUrl: boolean;
    updateUrl: string;
    testUrl: string;
    webhookRegisterUrl: string;
}) {
    const [copied, copy] = useClipboard();
    const hasKeys = settings.secret_key_hint !== null;
    const checks: [boolean, string][] = [
        [
            settings.market !== null,
            settings.market
                ? `Store currency ${settings.currency}: Tabby ${settings.market}`
                : `Store currency ${settings.currency} is not supported. Tabby works with AED (UAE) or SAR (Saudi Arabia).`,
        ],
        [
            hasKeys &&
                settings.public_key !== null &&
                settings.merchant_code !== null,
            'API keys and merchant code saved',
        ],
        [settings.webhook_registered, 'Webhook registered with Tabby'],
        [settings.enabled, 'Tabby enabled at checkout'],
    ];

    return (
        <>
            <Head title="Tabby" />
            <AdminPage>
                <PageHeader
                    title="Tabby"
                    description="Customers split their purchase into payments with Tabby. They pay on Tabby's secure page, and an order is marked paid only after the store confirms and captures the payment with Tabby."
                    actions={
                        settings.mode && (
                            <Badge
                                variant={
                                    settings.mode === 'live'
                                        ? 'default'
                                        : 'secondary'
                                }
                            >
                                {settings.mode === 'live'
                                    ? 'Live mode'
                                    : 'Test mode'}
                            </Badge>
                        )
                    }
                />

                <Card>
                    <CardHeader>
                        <CardTitle>
                            {settings.ready
                                ? 'Tabby is taking payments'
                                : 'Set up Tabby'}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul className="grid gap-2 text-sm">
                            {checks.map(([done, label]) => (
                                <li key={label} className="flex gap-2">
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

                <Card>
                    <CardHeader>
                        <CardTitle>1. API keys</CardTitle>
                        <CardDescription>
                            Find your keys and merchant code in the Tabby
                            merchant dashboard
                            {settings.dashboard && (
                                <>
                                    {' '}
                                    (
                                    <a
                                        href={settings.dashboard}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="underline"
                                    >
                                        {settings.dashboard.replace(
                                            'https://',
                                            '',
                                        )}
                                    </a>
                                    )
                                </>
                            )}
                            . Use test keys (pk_test_…, sk_test_…) until you
                            have completed a test purchase.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form
                            action={updateUrl}
                            method="put"
                            options={{ preserveScroll: true }}
                            className="grid gap-4"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="hidden"
                                            name="enabled"
                                            value="0"
                                        />
                                        <Checkbox
                                            name="enabled"
                                            value="1"
                                            defaultChecked={settings.enabled}
                                        />
                                        Offer Tabby at checkout
                                    </label>
                                    <FormField
                                        id="tabby-public-key"
                                        label="Public key"
                                        error={errors.public_key}
                                    >
                                        <Input
                                            id="tabby-public-key"
                                            name="public_key"
                                            defaultValue={
                                                settings.public_key ?? ''
                                            }
                                            autoComplete="off"
                                            placeholder="pk_test_…"
                                            required
                                        />
                                    </FormField>
                                    <FormField
                                        id="tabby-secret-key"
                                        label="Secret key"
                                        error={errors.secret_key}
                                        hint={
                                            settings.secret_key_hint
                                                ? `Saved: ${settings.secret_key_hint}. Leave blank to keep it.`
                                                : 'Stored encrypted. It is never shown again or sent to the browser.'
                                        }
                                    >
                                        <Input
                                            id="tabby-secret-key"
                                            name="secret_key"
                                            type="password"
                                            autoComplete="new-password"
                                            placeholder="sk_test_…"
                                            required={!hasKeys}
                                        />
                                    </FormField>
                                    <FormField
                                        id="tabby-merchant-code"
                                        label="Merchant code"
                                        error={errors.merchant_code}
                                        hint={`The merchant code for your ${settings.currency} store.`}
                                    >
                                        <Input
                                            id="tabby-merchant-code"
                                            name="merchant_code"
                                            defaultValue={
                                                settings.merchant_code ?? ''
                                            }
                                            autoComplete="off"
                                            required
                                        />
                                    </FormField>
                                    <div>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Save
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>2. Webhook</CardTitle>
                        <CardDescription>
                            Tabby tells the store about every payment change,
                            even when the customer closes the browser before
                            returning. Registering creates a new shared secret
                            that verifies each delivery.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3">
                        <div className="flex gap-2">
                            <Input
                                readOnly
                                value={webhookUrl}
                                aria-label="Webhook endpoint URL"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => copy(webhookUrl)}
                            >
                                <Copy />
                                {copied ? 'Copied' : 'Copy'}
                            </Button>
                        </div>
                        {!publicWebhookUrl && (
                            <p className="text-sm text-destructive">
                                Tabby can only reach a public https:// address.
                                Set APP_URL to your store's https address before
                                registering.
                            </p>
                        )}
                        <div className="flex flex-wrap gap-2">
                            <Form
                                action={webhookRegisterUrl}
                                method="post"
                                options={{ preserveScroll: true }}
                            >
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        disabled={
                                            processing ||
                                            !hasKeys ||
                                            !publicWebhookUrl
                                        }
                                    >
                                        {settings.webhook_registered
                                            ? 'Register again'
                                            : 'Register webhook'}
                                    </Button>
                                )}
                            </Form>
                            <Form
                                action={testUrl}
                                method="post"
                                options={{ preserveScroll: true }}
                            >
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        disabled={processing || !hasKeys}
                                    >
                                        Test connection
                                    </Button>
                                )}
                            </Form>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>3. Snippets and limits</CardTitle>
                        <CardDescription>
                            Where Tabby's messaging appears (product page, cart,
                            checkout), the eligibility check and order limits
                            are set in Extensions → Tabby. Refunds are made from
                            the order page and sent to Tabby automatically.
                        </CardDescription>
                    </CardHeader>
                </Card>
            </AdminPage>
        </>
    );
}
