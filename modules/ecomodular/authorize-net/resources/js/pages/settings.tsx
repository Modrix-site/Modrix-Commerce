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

type AuthorizeNetSettings = {
    enabled: boolean;
    mode: 'sandbox' | 'live';
    api_login_id: string;
    transaction_key_hint: string | null;
    signature_key_hint: string | null;
    ready: boolean;
};

export default function AuthorizeNetSettingsPage({
    settings,
    webhookUrl,
    publicWebhookUrl,
    updateUrl,
    testUrl,
}: {
    settings: AuthorizeNetSettings;
    webhookUrl: string;
    publicWebhookUrl: boolean;
    updateUrl: string;
    testUrl: string;
}) {
    const [copied, copy] = useClipboard();
    const hasKeys =
        settings.api_login_id !== '' && settings.transaction_key_hint !== null;
    const checks: [boolean, string][] = [
        [settings.api_login_id !== '', 'API Login ID saved'],
        [settings.transaction_key_hint !== null, 'Transaction key saved'],
        [settings.signature_key_hint !== null, 'Signature key saved'],
        [publicWebhookUrl, 'Store address is a public https:// URL'],
        [settings.enabled, 'Authorize.Net enabled at checkout'],
    ];

    return (
        <>
            <Head title="Authorize.Net" />
            <AdminPage>
                <PageHeader
                    title="Authorize.Net"
                    description="Customers pay on Authorize.Net's hosted form. This store marks the order paid only after Authorize.Net confirms the transaction, and card details never reach this store."
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
                                ? 'Authorize.Net is taking payments'
                                : 'Set up Authorize.Net'}
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
                        <CardTitle>1. API credentials</CardTitle>
                        <CardDescription>
                            In the Authorize.Net Merchant Interface, open
                            Account, Settings, Security Settings, API
                            Credentials and Keys. Use a sandbox account while
                            testing. Live mode charges real cards and requires
                            https. The store currency must be the currency of
                            this merchant account.
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
                                        Offer Authorize.Net at checkout
                                    </label>
                                    <FormField
                                        id="anet-mode"
                                        label="Mode"
                                        error={errors.mode}
                                        hint="Sandbox uses apitest.authorize.net. Live uses api.authorize.net."
                                    >
                                        <select
                                            id="anet-mode"
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
                                        id="anet-login"
                                        label="API Login ID"
                                        error={errors.api_login_id}
                                        hint="From API Credentials and Keys. This is not the signature key."
                                    >
                                        <Input
                                            id="anet-login"
                                            name="api_login_id"
                                            autoComplete="off"
                                            defaultValue={settings.api_login_id}
                                            required
                                        />
                                    </FormField>
                                    <FormField
                                        id="anet-transaction-key"
                                        label="Transaction key"
                                        error={errors.transaction_key}
                                        hint={
                                            settings.transaction_key_hint
                                                ? `Saved: ${settings.transaction_key_hint}. Leave blank to keep it.`
                                                : 'Stored encrypted. It is never shown again or sent to the browser.'
                                        }
                                    >
                                        <Input
                                            id="anet-transaction-key"
                                            name="transaction_key"
                                            type="password"
                                            autoComplete="new-password"
                                            required={
                                                settings.transaction_key_hint ===
                                                null
                                            }
                                        />
                                    </FormField>
                                    <FormField
                                        id="anet-signature-key"
                                        label="Signature key"
                                        error={errors.signature_key}
                                        hint={
                                            settings.signature_key_hint
                                                ? `Saved: ${settings.signature_key_hint}. Leave blank to keep it.`
                                                : 'The hexadecimal key used to verify webhooks. Generate it under API Credentials and Keys.'
                                        }
                                    >
                                        <Input
                                            id="anet-signature-key"
                                            name="signature_key"
                                            type="password"
                                            autoComplete="new-password"
                                            required={
                                                settings.signature_key_hint ===
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
                            In Account, Settings, Webhooks, add an endpoint for
                            this URL. Subscribe to payment authcapture created,
                            fraud held, fraud approved, fraud declined, refund
                            created and void created. The store still reads the
                            transaction from Authorize.Net before marking it
                            paid.
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
                                Authorize.Net can only reach a public https://
                                address. Set APP_URL to your store&apos;s https
                                address before relying on webhooks. The
                                customer&apos;s return from Authorize.Net still
                                works on this address.
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
