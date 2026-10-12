import { Form, Head, Link } from '@inertiajs/react';
import { Copy } from 'lucide-react';
import StripeSettingsController from '@/actions/App/Http/Controllers/Admin/StripeSettingsController';
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
import { settings as settingsRoute } from '@/routes/admin/payments';
import { edit } from '@/routes/admin/payments/stripe';

type StripeSettings = {
    enabled: boolean;
    label: string;
    mode: 'test' | 'live' | null;
    secret_key_hint: string | null;
    webhook_secret_hint: string | null;
};

export default function StripeSettingsPage({
    stripe,
    webhookUrl,
    events,
}: {
    stripe: StripeSettings;
    webhookUrl: string;
    events: string[];
}) {
    const [copied, copy] = useClipboard();

    return (
        <>
            <Head title="Stripe" />
            <AdminPage>
                <PageHeader
                    title="Stripe"
                    description="Customers pay on Stripe's secure page; card details never reach this store. Orders are marked paid only when Stripe confirms the payment."
                    actions={
                        stripe.mode && (
                            <Badge
                                variant={
                                    stripe.mode === 'live'
                                        ? 'default'
                                        : 'secondary'
                                }
                            >
                                {stripe.mode === 'live'
                                    ? 'Live mode'
                                    : 'Test mode'}
                            </Badge>
                        )
                    }
                />

                <Card>
                    <CardHeader>
                        <CardTitle>
                            1. Add a webhook endpoint in Stripe
                        </CardTitle>
                        <CardDescription>
                            In the Stripe Dashboard, open Developers → Webhooks,
                            add this endpoint URL and select the events below.
                            Then copy its signing secret into the form.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3">
                        <div className="flex gap-2">
                            <Input
                                readOnly
                                value={webhookUrl}
                                aria-label="Webhook endpoint URL"
                                className="font-mono text-xs"
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
                        <ul className="grid gap-1 font-mono text-xs text-muted-foreground">
                            {events.map((e) => (
                                <li key={e}>{e}</li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>

                <Form
                    {...StripeSettingsController.update.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess={['secret_key', 'webhook_secret']}
                    className="grid gap-6"
                >
                    {({ processing, errors }) => (
                        <Card>
                            <CardHeader>
                                <CardTitle>2. Credentials</CardTitle>
                                <CardDescription>
                                    Keys are stored encrypted and are never
                                    shown again. Leave a field empty to keep the
                                    current value. Use test keys (sk_test_…)
                                    until you have completed a test purchase.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <FormField
                                    id="secret_key"
                                    label="Secret key"
                                    error={errors.secret_key}
                                    hint={
                                        stripe.secret_key_hint
                                            ? `Current key: ${stripe.secret_key_hint}`
                                            : 'No key saved yet.'
                                    }
                                >
                                    <Input
                                        id="secret_key"
                                        name="secret_key"
                                        type="password"
                                        autoComplete="off"
                                        placeholder="sk_test_…"
                                    />
                                </FormField>
                                <FormField
                                    id="webhook_secret"
                                    label="Webhook signing secret"
                                    error={errors.webhook_secret}
                                    hint={
                                        stripe.webhook_secret_hint
                                            ? `Current secret: ${stripe.webhook_secret_hint}`
                                            : 'No signing secret saved yet.'
                                    }
                                >
                                    <Input
                                        id="webhook_secret"
                                        name="webhook_secret"
                                        type="password"
                                        autoComplete="off"
                                        placeholder="whsec_…"
                                    />
                                </FormField>
                                <FormField
                                    id="label"
                                    label="Name shown to customers"
                                    error={errors.label}
                                >
                                    <Input
                                        id="label"
                                        name="label"
                                        defaultValue={stripe.label}
                                        required
                                    />
                                </FormField>
                                <label className="flex items-center gap-2 text-sm">
                                    <Checkbox
                                        name="enabled"
                                        value="1"
                                        defaultChecked={stripe.enabled}
                                    />
                                    Offer card payments at checkout
                                </label>
                                <div className="flex flex-wrap gap-2">
                                    <Button type="submit" disabled={processing}>
                                        Save
                                    </Button>
                                    <Button variant="ghost" asChild>
                                        <Link href={settingsRoute()}>
                                            Back to payments
                                        </Link>
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </Form>

                {stripe.secret_key_hint && (
                    <Form {...StripeSettingsController.test.form()}>
                        {({ processing }) => (
                            <Button
                                type="submit"
                                variant="outline"
                                disabled={processing}
                            >
                                Test connection
                            </Button>
                        )}
                    </Form>
                )}
            </AdminPage>
        </>
    );
}

StripeSettingsPage.layout = {
    breadcrumbs: [
        { title: 'Payments', href: settingsRoute() },
        { title: 'Stripe', href: edit() },
    ],
};
