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

type TapSettings = {
    enabled: boolean;
    mode: 'sandbox' | 'live' | 'unset';
    source: 'card' | 'all';
    merchant_id: string;
    secret_hint: string | null;
    ready: boolean;
};

export default function TapSettingsPage({
    settings,
    webhookUrl,
    publicWebhookUrl,
    updateUrl,
    testUrl,
}: {
    settings: TapSettings;
    webhookUrl: string;
    publicWebhookUrl: boolean;
    updateUrl: string;
    testUrl: string;
}) {
    const [copied, copy] = useClipboard();
    const hasKey = settings.secret_hint !== null;
    const checks: [boolean, string][] = [
        [hasKey, 'Secret key saved'],
        [
            settings.mode !== 'live' || publicWebhookUrl,
            'Live keys use a public https:// store address',
        ],
        [settings.enabled, 'Tap enabled at checkout'],
    ];

    return (
        <>
            <Head title="Tap Payments" />
            <AdminPage>
                <PageHeader
                    title="Tap Payments"
                    description="Customers pay on Tap's hosted page. This store marks the order paid only after Tap reports the charge as captured, and card details never reach this store."
                    actions={
                        <Badge
                            variant={
                                settings.mode === 'live'
                                    ? 'default'
                                    : 'secondary'
                            }
                        >
                            {settings.mode === 'live'
                                ? 'Live'
                                : settings.mode === 'sandbox'
                                  ? 'Sandbox'
                                  : 'Not configured'}
                        </Badge>
                    }
                />

                <Card>
                    <CardHeader>
                        <CardTitle>
                            {settings.ready
                                ? 'Tap is taking payments'
                                : 'Set up Tap'}
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
                        <CardTitle>1. Tap account</CardTitle>
                        <CardDescription>
                            In the Tap dashboard, copy the secret key. A key
                            starting with sk_test_ uses the test account. A key
                            starting with sk_live_ charges real payments and
                            requires https. The public key is not used: the card
                            form stays on Tap.
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
                                        Offer Tap at checkout
                                    </label>
                                    <FormField
                                        id="tap-source"
                                        label="Payment methods"
                                        error={errors.source}
                                        hint="Cards only uses src_card. All enabled methods uses src_all, including KNET, mada and Benefit when Tap has enabled them."
                                    >
                                        <select
                                            id="tap-source"
                                            name="source"
                                            defaultValue={settings.source}
                                            className="h-9 rounded-md border bg-transparent px-3 text-sm"
                                        >
                                            <option value="all">
                                                All methods enabled on Tap
                                            </option>
                                            <option value="card">
                                                Cards only
                                            </option>
                                        </select>
                                    </FormField>
                                    <FormField
                                        id="tap-merchant"
                                        label="Merchant ID"
                                        error={errors.merchant_id}
                                        hint="Optional. From the Tap dashboard. Leave blank unless Tap asks for it on the charge."
                                    >
                                        <Input
                                            id="tap-merchant"
                                            name="merchant_id"
                                            autoComplete="off"
                                            defaultValue={settings.merchant_id}
                                        />
                                    </FormField>
                                    <FormField
                                        id="tap-secret"
                                        label="Secret key"
                                        error={errors.secret_key}
                                        hint={
                                            settings.secret_hint
                                                ? `Saved: ${settings.secret_hint}. Leave blank to keep it.`
                                                : 'Stored encrypted. It is never shown again or sent to the browser.'
                                        }
                                    >
                                        <Input
                                            id="tap-secret"
                                            name="secret_key"
                                            type="password"
                                            autoComplete="new-password"
                                            required={
                                                settings.secret_hint === null
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
                            Tap posts each charge and refund to the address sent
                            with the charge. This store also sends the URL below
                            when it is https. Tap signs the body with
                            hashstring, using the same secret key. The store
                            still reads the charge from Tap before marking it
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
                                Tap can only reach a public https:// address.
                                Set APP_URL to your store&apos;s https address
                                before relying on webhooks. The customer&apos;s
                                return from Tap still works on this address.
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
                                    disabled={processing || !hasKey}
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
