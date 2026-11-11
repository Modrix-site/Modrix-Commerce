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

type PaypalSettings = {
    enabled: boolean;
    mode: 'sandbox' | 'live';
    client_id: string;
    webhook_id: string;
    client_secret_hint: string | null;
    ready: boolean;
};

export default function PaypalSettingsPage({
    settings,
    webhookUrl,
    publicWebhookUrl,
    updateUrl,
    testUrl,
}: {
    settings: PaypalSettings;
    webhookUrl: string;
    publicWebhookUrl: boolean;
    updateUrl: string;
    testUrl: string;
}) {
    const [copied, copy] = useClipboard();
    const hasKeys =
        settings.client_id !== '' && settings.client_secret_hint !== null;
    const checks: [boolean, string][] = [
        [settings.client_id !== '', 'PayPal client ID saved'],
        [settings.client_secret_hint !== null, 'Secret saved'],
        [settings.webhook_id !== '', 'Webhook ID saved'],
        [publicWebhookUrl, 'Store address is a public https:// URL'],
        [settings.enabled, 'PayPal enabled at checkout'],
    ];

    return (
        <>
            <Head title="PayPal" />
            <AdminPage>
                <PageHeader
                    title="PayPal"
                    description="Customers approve the payment on PayPal. This store captures the order only after PayPal confirms it, and card details never reach this store."
                    actions={
                        <Badge
                            variant={
                                settings.mode === 'sandbox'
                                    ? 'secondary'
                                    : 'default'
                            }
                        >
                            {settings.mode === 'sandbox' ? 'Sandbox' : 'Live'}
                        </Badge>
                    }
                />

                <Card>
                    <CardHeader>
                        <CardTitle>
                            {settings.ready
                                ? 'PayPal is taking payments'
                                : 'Set up PayPal'}
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
                        <CardTitle>1. PayPal app</CardTitle>
                        <CardDescription>
                            In the PayPal developer dashboard, open your REST
                            app and copy the client ID and secret. Use a sandbox
                            app while testing. Live mode charges real payments
                            and requires https.
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
                                        Offer PayPal at checkout
                                    </label>
                                    <FormField
                                        id="paypal-mode"
                                        label="Mode"
                                        error={errors.mode}
                                        hint="Sandbox uses api-m.sandbox.paypal.com. Live uses api-m.paypal.com."
                                    >
                                        <select
                                            id="paypal-mode"
                                            name="mode"
                                            defaultValue={settings.mode}
                                            className="h-9 rounded-md border bg-transparent px-3 text-sm"
                                        >
                                            <option value="sandbox">
                                                Sandbox
                                            </option>
                                            <option value="live">Live</option>
                                        </select>
                                    </FormField>
                                    <FormField
                                        id="paypal-client-id"
                                        label="Client ID"
                                        error={errors.client_id}
                                        hint="From the REST app. This is not a secret."
                                    >
                                        <Input
                                            id="paypal-client-id"
                                            name="client_id"
                                            autoComplete="off"
                                            defaultValue={settings.client_id}
                                            required
                                        />
                                    </FormField>
                                    <FormField
                                        id="paypal-secret"
                                        label="Secret"
                                        error={errors.client_secret}
                                        hint={
                                            settings.client_secret_hint
                                                ? `Saved: ${settings.client_secret_hint}. Leave blank to keep it.`
                                                : 'Stored encrypted. It is never shown again or sent to the browser.'
                                        }
                                    >
                                        <Input
                                            id="paypal-secret"
                                            name="client_secret"
                                            type="password"
                                            autoComplete="new-password"
                                            required={
                                                settings.client_secret_hint ===
                                                null
                                            }
                                        />
                                    </FormField>
                                    <FormField
                                        id="paypal-webhook-id"
                                        label="Webhook ID"
                                        error={errors.webhook_id}
                                        hint="The ID PayPal shows after you add the webhook below. It is not the secret."
                                    >
                                        <Input
                                            id="paypal-webhook-id"
                                            name="webhook_id"
                                            autoComplete="off"
                                            defaultValue={settings.webhook_id}
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
                        <CardTitle>2. Webhooks</CardTitle>
                        <CardDescription>
                            In the same PayPal app, add a webhook for this URL
                            and subscribe to CHECKOUT.ORDER.APPROVED,
                            PAYMENT.CAPTURE.COMPLETED, PAYMENT.CAPTURE.DENIED,
                            PAYMENT.CAPTURE.PENDING and
                            PAYMENT.CAPTURE.REFUNDED. Copy the webhook ID into
                            the field above. The store still reads the order
                            from PayPal before marking it paid.
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
                                PayPal can only reach a public https:// address.
                                Set APP_URL to your store&apos;s https address
                                before relying on webhooks. The customer&apos;s
                                return from PayPal still works on this address.
                            </p>
                        )}
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
                    </CardContent>
                </Card>
            </AdminPage>
        </>
    );
}
