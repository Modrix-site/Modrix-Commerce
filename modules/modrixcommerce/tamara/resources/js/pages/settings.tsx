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

type TamaraSettings = {
    enabled: boolean;
    environment: 'sandbox' | 'production';
    api_token_hint: string | null;
    notification_token_hint: string | null;
    currency: string;
    market: string | null;
    dashboard: string | null;
    ready: boolean;
};

export default function TamaraSettingsPage({
    settings,
    webhookUrl,
    publicWebhookUrl,
    updateUrl,
    testUrl,
}: {
    settings: TamaraSettings;
    webhookUrl: string;
    publicWebhookUrl: boolean;
    updateUrl: string;
    testUrl: string;
}) {
    const [copied, copy] = useClipboard();
    const hasTokens =
        settings.api_token_hint !== null &&
        settings.notification_token_hint !== null;
    const checks: [boolean, string][] = [
        [
            settings.market !== null,
            settings.market
                ? `Store currency ${settings.currency}: Tamara ${settings.market}`
                : `Store currency ${settings.currency} is not supported. Tamara works with GCC currencies (AED, SAR, KWD, BHD, OMR, QAR).`,
        ],
        [hasTokens, 'API token and notification token saved'],
        [settings.enabled, 'Tamara enabled at checkout'],
    ];

    return (
        <>
            <Head title="Tamara" />
            <AdminPage>
                <PageHeader
                    title="Tamara"
                    description="Customers split their purchase into payments with Tamara. They pay on Tamara's secure page, and an order is marked paid only after the store authorises and captures the payment with Tamara."
                    actions={
                        <Badge
                            variant={
                                settings.environment === 'production'
                                    ? 'default'
                                    : 'secondary'
                            }
                        >
                            {settings.environment === 'production'
                                ? 'Live mode'
                                : 'Sandbox'}
                        </Badge>
                    }
                />

                <Card>
                    <CardHeader>
                        <CardTitle>
                            {settings.ready
                                ? 'Tamara is taking payments'
                                : 'Set up Tamara'}
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
                            Find your API token and notification token in the
                            Tamara merchant dashboard
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
                            . Use sandbox tokens until you have completed a test
                            purchase.
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
                                        Offer Tamara at checkout
                                    </label>
                                    <FormField
                                        id="tamara-environment"
                                        label="Environment"
                                        error={errors.environment}
                                    >
                                        <select
                                            id="tamara-environment"
                                            name="environment"
                                            defaultValue={settings.environment}
                                            className="h-9 rounded-md border bg-transparent px-3 text-sm"
                                        >
                                            <option value="sandbox">
                                                Sandbox
                                            </option>
                                            <option value="production">
                                                Production
                                            </option>
                                        </select>
                                    </FormField>
                                    <FormField
                                        id="tamara-api-token"
                                        label="API token"
                                        error={errors.api_token}
                                        hint={
                                            settings.api_token_hint
                                                ? `Saved: ${settings.api_token_hint}. Leave blank to keep it.`
                                                : 'Stored encrypted. It is never shown again or sent to the browser.'
                                        }
                                    >
                                        <Input
                                            id="tamara-api-token"
                                            name="api_token"
                                            type="password"
                                            autoComplete="new-password"
                                            required={
                                                settings.api_token_hint === null
                                            }
                                        />
                                    </FormField>
                                    <FormField
                                        id="tamara-notification-token"
                                        label="Notification token"
                                        error={errors.notification_token}
                                        hint={
                                            settings.notification_token_hint
                                                ? `Saved: ${settings.notification_token_hint}. Leave blank to keep it.`
                                                : 'The token Tamara sends in the tamara-token header on every webhook. Stored encrypted.'
                                        }
                                    >
                                        <Input
                                            id="tamara-notification-token"
                                            name="notification_token"
                                            type="password"
                                            autoComplete="new-password"
                                            required={
                                                settings.notification_token_hint ===
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
                        <CardTitle>2. Webhook</CardTitle>
                        <CardDescription>
                            In the Tamara dashboard, point the order
                            notification URL at the address below and set its
                            notification token to the one saved here. Tamara
                            tells the store about every payment change, even
                            when the customer closes the browser before
                            returning.
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
                                Tamara can only reach a public https:// address.
                                Set APP_URL to your store's https address.
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
                                    disabled={processing || !hasTokens}
                                >
                                    Test connection
                                </Button>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>3. Snippets and limits</CardTitle>
                        <CardDescription>
                            Where Tamara's messaging appears (product page,
                            cart, checkout), the availability check and order
                            limits are set in Extensions → Tamara. Refunds are
                            made from the order page and sent to Tamara
                            automatically.
                        </CardDescription>
                    </CardHeader>
                </Card>
            </AdminPage>
        </>
    );
}
