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

type CheckoutComSettings = {
    enabled: boolean;
    api_base: string;
    public_key: string;
    processing_channel_id: string;
    display: 'hosted' | 'flow';
    apple_pay: boolean;
    google_pay: boolean;
    sandbox: boolean | null;
    secret_key_hint: string | null;
    webhook_secret_hint: string | null;
    ready: boolean;
};

export default function CheckoutComSettingsPage({
    settings,
    webhookUrl,
    publicWebhookUrl,
    updateUrl,
    testUrl,
}: {
    settings: CheckoutComSettings;
    webhookUrl: string;
    publicWebhookUrl: boolean;
    updateUrl: string;
    testUrl: string;
}) {
    const [copied, copy] = useClipboard();
    const hasKeys =
        settings.api_base !== '' &&
        settings.secret_key_hint !== null &&
        settings.webhook_secret_hint !== null;
    const checks: [boolean, string][] = [
        [settings.api_base !== '', 'Checkout.com API base URL saved'],
        [settings.secret_key_hint !== null, 'Secret key saved'],
        [settings.webhook_secret_hint !== null, 'Webhook signature key saved'],
        [
            settings.display !== 'flow' || settings.public_key !== '',
            'Public key saved when using Flow',
        ],
        [publicWebhookUrl, 'Store address is a public https:// URL'],
        [settings.enabled, 'Checkout.com enabled at checkout'],
    ];

    return (
        <>
            <Head title="Checkout.com" />
            <AdminPage>
                <PageHeader
                    title="Checkout.com"
                    description="Customers pay on Checkout.com's hosted page, or in Flow on your store. Card details never reach this store, and an order is marked paid only after Checkout.com confirms the captured amount."
                    actions={
                        settings.sandbox === null ? undefined : (
                            <Badge
                                variant={
                                    settings.sandbox ? 'secondary' : 'default'
                                }
                            >
                                {settings.sandbox ? 'Sandbox' : 'Live'}
                            </Badge>
                        )
                    }
                />

                <Card>
                    <CardHeader>
                        <CardTitle>
                            {settings.ready
                                ? 'Checkout.com is taking payments'
                                : 'Set up Checkout.com'}
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
                        <CardTitle>1. Checkout.com account</CardTitle>
                        <CardDescription>
                            In the Checkout.com dashboard, copy the secret API
                            key and, for Flow, the public key. Use a sandbox
                            key (sk_sbox_) with https://api.sandbox.checkout.com.
                            A processing channel id (pc_) is optional; add it
                            when the account has more than one channel.
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
                                        Offer Checkout.com at checkout
                                    </label>
                                    <FormField
                                        id="checkout-com-api-base"
                                        label="API base URL"
                                        error={errors.api_base}
                                        hint="https://api.sandbox.checkout.com or https://api.checkout.com. An account prefix such as https://abc.api.sandbox.checkout.com is also accepted."
                                    >
                                        <Input
                                            id="checkout-com-api-base"
                                            name="api_base"
                                            type="url"
                                            autoComplete="off"
                                            defaultValue={settings.api_base}
                                            placeholder="https://api.sandbox.checkout.com"
                                            required
                                        />
                                    </FormField>
                                    <FormField
                                        id="checkout-com-display"
                                        label="Checkout display"
                                        error={errors.display}
                                        hint="Hosted page sends the customer to Checkout.com. Flow keeps the form on your store. Checkout.com does not allow the hosted page inside an iframe."
                                    >
                                        <select
                                            id="checkout-com-display"
                                            name="display"
                                            defaultValue={settings.display}
                                            className="h-9 rounded-md border bg-transparent px-3 text-sm"
                                        >
                                            <option value="hosted">
                                                Hosted payment page
                                            </option>
                                            <option value="flow">
                                                Flow on this store
                                            </option>
                                        </select>
                                    </FormField>
                                    <FormField
                                        id="checkout-com-public-key"
                                        label="Public key"
                                        error={errors.public_key}
                                        hint="Required for Flow. A pk_sbox_ key is sandbox. This key is used in the browser and is not a secret."
                                    >
                                        <Input
                                            id="checkout-com-public-key"
                                            name="public_key"
                                            autoComplete="off"
                                            defaultValue={settings.public_key}
                                        />
                                    </FormField>
                                    <FormField
                                        id="checkout-com-channel"
                                        label="Processing channel id"
                                        error={errors.processing_channel_id}
                                        hint="Optional. Starts with pc_."
                                    >
                                        <Input
                                            id="checkout-com-channel"
                                            name="processing_channel_id"
                                            autoComplete="off"
                                            defaultValue={
                                                settings.processing_channel_id
                                            }
                                        />
                                    </FormField>
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="hidden"
                                            name="apple_pay"
                                            value="0"
                                        />
                                        <Checkbox
                                            name="apple_pay"
                                            value="1"
                                            defaultChecked={settings.apple_pay}
                                        />
                                        Accept Apple Pay
                                    </label>
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="hidden"
                                            name="google_pay"
                                            value="0"
                                        />
                                        <Checkbox
                                            name="google_pay"
                                            value="1"
                                            defaultChecked={settings.google_pay}
                                        />
                                        Accept Google Pay
                                    </label>
                                    <p className="text-sm text-muted-foreground">
                                        Apple Pay and Google Pay appear when
                                        the switch is on and the same method is
                                        enabled for this processing channel in
                                        the Checkout.com dashboard. Register
                                        this store&apos;s domain for Apple Pay
                                        there. Card details still stay on
                                        Checkout.com.
                                    </p>
                                    <FormField
                                        id="checkout-com-secret-key"
                                        label="Secret key"
                                        error={errors.secret_key}
                                        hint={
                                            settings.secret_key_hint
                                                ? `Saved: ${settings.secret_key_hint}. Leave blank to keep it.`
                                                : 'Stored encrypted. It is never shown again or sent to the browser.'
                                        }
                                    >
                                        <Input
                                            id="checkout-com-secret-key"
                                            name="secret_key"
                                            type="password"
                                            autoComplete="new-password"
                                            required={
                                                settings.secret_key_hint ===
                                                null
                                            }
                                        />
                                    </FormField>
                                    <FormField
                                        id="checkout-com-webhook-secret"
                                        label="Webhook signature key"
                                        error={errors.webhook_secret}
                                        hint={
                                            settings.webhook_secret_hint
                                                ? `Saved: ${settings.webhook_secret_hint}. Leave blank to keep it.`
                                                : 'The signature key from Developers → Webhooks. Stored encrypted.'
                                        }
                                    >
                                        <Input
                                            id="checkout-com-webhook-secret"
                                            name="webhook_secret"
                                            type="password"
                                            autoComplete="new-password"
                                            required={
                                                settings.webhook_secret_hint ===
                                                null
                                            }
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
                            In the Checkout.com dashboard, add a webhook for
                            this URL and subscribe to payment_captured,
                            payment_approved, payment_declined,
                            payment_canceled, payment_expired, payment_voided,
                            payment_refunded and payment_refund_declined. The
                            store still reads the payment from Checkout.com
                            before marking an order paid. Sandbox test card:
                            4242 4242 4242 4242, any future expiry, CVV 100.
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
                                Checkout.com can only reach a public https://
                                address. Set APP_URL to your store&apos;s https
                                address.
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
