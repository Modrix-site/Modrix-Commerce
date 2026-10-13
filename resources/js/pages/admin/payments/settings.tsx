import { Form, Head, Link } from '@inertiajs/react';
import PaymentSettingsController from '@/actions/App/Http/Controllers/Admin/PaymentSettingsController';
import { FormField } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Tabs } from '@/components/admin/tabs';
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
import { settings as settingsRoute } from '@/routes/admin/payments';
import { edit as stripeEdit } from '@/routes/admin/payments/stripe';
import { index as webhooks } from '@/routes/admin/payments/webhooks';

type Method = {
    code: string;
    enabled: boolean;
    label: string;
    instructions: string;
};

function OnOff({ on, extra }: { on: boolean; extra?: string }) {
    return on ? (
        <Badge>
            On
            {extra}
        </Badge>
    ) : (
        <Badge variant="outline">Off</Badge>
    );
}

export default function PaymentSettings({
    methods,
    guestCheckout,
    stripe,
}: {
    methods: Method[];
    guestCheckout: boolean;
    stripe: { enabled: boolean; configured: boolean; mode: string | null };
}) {
    return (
        <>
            <Head title="Payments" />
            <AdminPage>
                <PageHeader
                    title="Payments and checkout"
                    description="Card payments are confirmed by Stripe. Manual methods are paid outside the store, and those orders stay unpaid until you record the payment."
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={webhooks()}>Payment events</Link>
                        </Button>
                    }
                />

                <Form
                    {...PaymentSettingsController.update.form()}
                    options={{ preserveScroll: true }}
                    className="grid gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <Tabs
                                label="Payment settings"
                                items={[
                                    {
                                        id: 'stripe',
                                        label: (
                                            <>
                                                Stripe
                                                <OnOff
                                                    on={stripe.configured}
                                                    extra={
                                                        stripe.mode === 'test'
                                                            ? ' (test)'
                                                            : undefined
                                                    }
                                                />
                                            </>
                                        ),
                                        content: (
                                            <Card>
                                                <CardHeader>
                                                    <CardTitle>
                                                        Card payments with
                                                        Stripe
                                                    </CardTitle>
                                                    <CardDescription>
                                                        Accept cards and wallets
                                                        on Stripe's hosted
                                                        payment page. Requires a
                                                        Stripe account and a
                                                        webhook endpoint.
                                                    </CardDescription>
                                                </CardHeader>
                                                <CardContent>
                                                    <Button
                                                        variant="outline"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={stripeEdit()}
                                                        >
                                                            Configure Stripe
                                                        </Link>
                                                    </Button>
                                                </CardContent>
                                            </Card>
                                        ),
                                    },
                                    {
                                        id: 'checkout',
                                        label: 'Checkout',
                                        hasError: Boolean(
                                            errors.guest_checkout,
                                        ),
                                        content: (
                                            <Card>
                                                <CardHeader>
                                                    <CardTitle>
                                                        Checkout
                                                    </CardTitle>
                                                    <CardDescription>
                                                        How shoppers check out.
                                                    </CardDescription>
                                                </CardHeader>
                                                <CardContent>
                                                    <label className="flex items-center gap-2 text-sm">
                                                        <Checkbox
                                                            name="guest_checkout"
                                                            value="1"
                                                            defaultChecked={
                                                                guestCheckout
                                                            }
                                                        />
                                                        Allow checkout without
                                                        an account
                                                    </label>
                                                </CardContent>
                                            </Card>
                                        ),
                                    },
                                    ...methods.map((m) => ({
                                        id: m.code,
                                        label: (
                                            <>
                                                {m.label}
                                                <OnOff on={m.enabled} />
                                            </>
                                        ),
                                        hasError: Object.keys(errors).some(
                                            (k) =>
                                                k.startsWith(
                                                    `methods.${m.code}.`,
                                                ),
                                        ),
                                        content: (
                                            <MethodCard
                                                method={m}
                                                errors={errors}
                                            />
                                        ),
                                    })),
                                ]}
                            />
                            <div>
                                <Button type="submit" disabled={processing}>
                                    Save
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </AdminPage>
        </>
    );
}

/**
 * Fields are validated on the server (not with `required`): the browser cannot
 * show its own message for a field inside a hidden tab and would block Save.
 */
function MethodCard({
    method: m,
    errors,
}: {
    method: Method;
    errors: Record<string, string | undefined>;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{m.label}</CardTitle>
                <CardDescription>
                    {m.code === 'cash_on_delivery'
                        ? 'Orders can be shipped before payment is recorded.'
                        : 'Orders can be shipped only after you record the payment.'}
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-4">
                <label className="flex items-center gap-2 text-sm">
                    <Checkbox
                        name={`methods[${m.code}][enabled]`}
                        value="1"
                        defaultChecked={m.enabled}
                    />
                    Enabled
                </label>
                <FormField
                    id={`${m.code}-label`}
                    label="Name shown to customers"
                    error={errors[`methods.${m.code}.label`]}
                >
                    <Input
                        id={`${m.code}-label`}
                        name={`methods[${m.code}][label]`}
                        defaultValue={m.label}
                    />
                </FormField>
                <FormField
                    id={`${m.code}-instructions`}
                    label="Instructions"
                    error={errors[`methods.${m.code}.instructions`]}
                    hint="Shown at checkout and in the order confirmation email."
                >
                    <Textarea
                        id={`${m.code}-instructions`}
                        name={`methods[${m.code}][instructions]`}
                        rows={3}
                        defaultValue={m.instructions}
                    />
                </FormField>
            </CardContent>
        </Card>
    );
}

PaymentSettings.layout = {
    breadcrumbs: [{ title: 'Payments', href: settingsRoute() }],
};
