import { Form, Head, Link } from '@inertiajs/react';
import PaymentSettingsController from '@/actions/App/Http/Controllers/Admin/PaymentSettingsController';
import { ExtensionIcon } from '@/components/admin/extension-icon';
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
    fee_enabled: boolean;
    fee_amount: string;
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
    gatewayExtensions,
    stripe,
    currency,
}: {
    gatewayExtensions: {
        key: string;
        title: string;
        href: string;
        icon: string;
    }[];
    methods: Method[];
    currency: string;
    stripe: { enabled: boolean; configured: boolean; mode: string | null };
}) {
    return (
        <>
            <Head title="Payments" />
            <AdminPage>
                <PageHeader
                    title="Payments and checkout"
                    description="Configure payment gateways and checkout. Manual payment orders stay unpaid until you record the payment."
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
                                    ...gatewayExtensions.map((gateway) => ({
                                        id: gateway.key,
                                        label: (
                                            <>
                                                <ExtensionIcon
                                                    name={gateway.key}
                                                    icon={gateway.icon}
                                                    className="h-7 w-16 rounded bg-white"
                                                />
                                                {gateway.title}
                                            </>
                                        ),
                                        content: (
                                            <Card>
                                                <CardHeader>
                                                    <CardTitle>
                                                        {gateway.title}
                                                    </CardTitle>
                                                    <CardDescription>
                                                        Configure this payment
                                                        gateway extension,
                                                        credentials and checkout
                                                        options.
                                                    </CardDescription>
                                                </CardHeader>
                                                <CardContent>
                                                    <Button
                                                        variant="outline"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={gateway.href}
                                                        >
                                                            Configure{' '}
                                                            {gateway.title}
                                                        </Link>
                                                    </Button>
                                                </CardContent>
                                            </Card>
                                        ),
                                    })),
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
                                                currency={currency}
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
    currency,
}: {
    method: Method;
    errors: Record<string, string | undefined>;
    currency: string;
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
                {m.code === 'cash_on_delivery' && (
                    <div className="grid gap-4 rounded-lg border p-4">
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox
                                name="methods[cash_on_delivery][fee_enabled]"
                                value="1"
                                defaultChecked={m.fee_enabled}
                            />
                            Add a courier collection fee
                        </label>
                        <FormField
                            id="cash_on_delivery-fee_amount"
                            label={`Courier fee (${currency})`}
                            error={
                                errors['methods.cash_on_delivery.fee_amount']
                            }
                            hint="Added to the order total only for cash on delivery with physical items. The courier collects this fee when delivering the order. Enter the final fee including any applicable tax."
                        >
                            <Input
                                id="cash_on_delivery-fee_amount"
                                name="methods[cash_on_delivery][fee_amount]"
                                type="number"
                                min="0"
                                step="0.0001"
                                defaultValue={m.fee_amount}
                            />
                        </FormField>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

PaymentSettings.layout = {
    breadcrumbs: [{ title: 'Payments', href: settingsRoute() }],
};
