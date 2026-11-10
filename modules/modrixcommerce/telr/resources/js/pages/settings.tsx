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

type TelrSettings = {
    enabled: boolean;
    store_id: string;
    test_mode: boolean;
    display: 'hosted' | 'iframe';
    auth_key_hint: string | null;
    remote_auth_key_hint: string | null;
    webhook_secret_hint: string | null;
    ready: boolean;
};

export default function TelrSettingsPage({
    settings,
    webhookUrl,
    publicWebhookUrl,
    updateUrl,
    testUrl,
}: {
    settings: TelrSettings;
    webhookUrl: string;
    publicWebhookUrl: boolean;
    updateUrl: string;
    testUrl: string;
}) {
    const [copied, copy] = useClipboard();
    const hasKeys =
        settings.store_id !== '' &&
        settings.auth_key_hint !== null &&
        settings.webhook_secret_hint !== null;
    const checks: [boolean, string][] = [
        [settings.store_id !== '', 'Telr store ID saved'],
        [settings.auth_key_hint !== null, 'Authentication key saved'],
        [
            settings.webhook_secret_hint !== null,
            'Webhook secret saved',
        ],
        [publicWebhookUrl, 'Store address is a public https:// URL'],
        [settings.enabled, 'Telr enabled at checkout'],
    ];

    return (
        <>
            <Head title="Telr" />
            <AdminPage>
                <PageHeader
                    title="Telr"
                    description="Customers pay on Telr's secure page, or in an iframe on your store. Card details never reach this store, and an order is marked paid only after Telr confirms the captured amount."
                    actions={
                        <Badge
                            variant={
                                settings.test_mode ? 'secondary' : 'default'
                            }
                        >
                            {settings.test_mode ? 'Test mode' : 'Live mode'}
                        </Badge>
                    }
                />

                <Card>
                    <CardHeader>
                        <CardTitle>
                            {settings.ready
                                ? 'Telr is taking payments'
                                : 'Set up Telr'}
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
                        <CardTitle>1. Telr account</CardTitle>
                        <CardDescription>
                            In the Telr merchant admin, open Integrations and
                            copy the store ID and the hosted payment page
                            authentication key. The secret key is the value
                            Telr uses to sign transaction advice. Leave the
                            remote key blank when Telr gave you one
                            authentication key for both the payment page and
                            refunds.
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
                                        Offer Telr at checkout
                                    </label>
                                    <FormField
                                        id="telr-store-id"
                                        label="Store ID"
                                        error={errors.store_id}
                                    >
                                        <Input
                                            id="telr-store-id"
                                            name="store_id"
                                            inputMode="numeric"
                                            autoComplete="off"
                                            defaultValue={settings.store_id}
                                            required
                                        />
                                    </FormField>
                                    <FormField
                                        id="telr-mode"
                                        label="Mode"
                                        error={errors.test_mode}
                                        hint="Test mode sends order.test = 1. Switch to live only after a real Telr account is approved."
                                    >
                                        <select
                                            id="telr-mode"
                                            name="test_mode"
                                            defaultValue={
                                                settings.test_mode ? '1' : '0'
                                            }
                                            className="h-9 rounded-md border bg-transparent px-3 text-sm"
                                        >
                                            <option value="1">
                                                Test
                                            </option>
                                            <option value="0">Live</option>
                                        </select>
                                    </FormField>
                                    <FormField
                                        id="telr-display"
                                        label="Checkout display"
                                        error={errors.display}
                                        hint="Hosted page sends the customer to Telr. Iframe keeps them on your store. Iframe requires https."
                                    >
                                        <select
                                            id="telr-display"
                                            name="display"
                                            defaultValue={settings.display}
                                            className="h-9 rounded-md border bg-transparent px-3 text-sm"
                                        >
                                            <option value="hosted">
                                                Hosted payment page
                                            </option>
                                            <option value="iframe">
                                                Iframe on this store
                                            </option>
                                        </select>
                                    </FormField>
                                    <FormField
                                        id="telr-auth-key"
                                        label="Authentication key"
                                        error={errors.auth_key}
                                        hint={
                                            settings.auth_key_hint
                                                ? `Saved: ${settings.auth_key_hint}. Leave blank to keep it.`
                                                : 'Stored encrypted. It is never shown again or sent to the browser.'
                                        }
                                    >
                                        <Input
                                            id="telr-auth-key"
                                            name="auth_key"
                                            type="password"
                                            autoComplete="new-password"
                                            required={
                                                settings.auth_key_hint === null
                                            }
                                        />
                                    </FormField>
                                    <FormField
                                        id="telr-remote-key"
                                        label="Remote authentication key"
                                        error={errors.remote_auth_key}
                                        hint={
                                            settings.remote_auth_key_hint
                                                ? `Saved: ${settings.remote_auth_key_hint}. Leave blank to keep it.`
                                                : 'Optional. Used for refunds when Telr issued a separate remote key.'
                                        }
                                    >
                                        <Input
                                            id="telr-remote-key"
                                            name="remote_auth_key"
                                            type="password"
                                            autoComplete="new-password"
                                        />
                                    </FormField>
                                    <FormField
                                        id="telr-webhook-secret"
                                        label="Webhook secret"
                                        error={errors.webhook_secret}
                                        hint={
                                            settings.webhook_secret_hint
                                                ? `Saved: ${settings.webhook_secret_hint}. Leave blank to keep it.`
                                                : 'The secret key Telr uses in the SHA-1 tran_check hash. Stored encrypted.'
                                        }
                                    >
                                        <Input
                                            id="telr-webhook-secret"
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
                        <CardTitle>2. Transaction advice</CardTitle>
                        <CardDescription>
                            In the Telr merchant admin, set the transaction
                            advice URL to the address below. Telr posts each
                            sale and refund here. The store still asks Telr
                            for the order status before marking an order paid.
                            Logo, colours and CSS for the payment page are set
                            in the Telr merchant admin, not in this store.
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
                                Telr can only reach a public https:// address.
                                Set APP_URL to your store&apos;s https address.
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
