import { Form, Head } from '@inertiajs/react';
import PaymentSettingsController from '@/actions/App/Http/Controllers/Admin/PaymentSettingsController';
import { FormField } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
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

type Method = {
    code: string;
    enabled: boolean;
    label: string;
    instructions: string;
};

export default function PaymentSettings({
    methods,
    guestCheckout,
}: {
    methods: Method[];
    guestCheckout: boolean;
}) {
    return (
        <>
            <Head title="Payments" />
            <AdminPage>
                <PageHeader
                    title="Payments and checkout"
                    description="Manual methods are paid outside the store. Orders stay unpaid until you record the payment. Card payments (Stripe) arrive in a later release."
                />
                <Form
                    {...PaymentSettingsController.update.form()}
                    options={{ preserveScroll: true }}
                    className="grid gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <Card>
                                <CardHeader>
                                    <CardTitle>Checkout</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <label className="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            name="guest_checkout"
                                            value="1"
                                            defaultChecked={guestCheckout}
                                        />
                                        Allow checkout without an account
                                    </label>
                                </CardContent>
                            </Card>
                            {methods.map((m) => (
                                <Card key={m.code}>
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
                                            error={
                                                errors[
                                                    `methods.${m.code}.label`
                                                ]
                                            }
                                        >
                                            <Input
                                                id={`${m.code}-label`}
                                                name={`methods[${m.code}][label]`}
                                                defaultValue={m.label}
                                                required
                                            />
                                        </FormField>
                                        <FormField
                                            id={`${m.code}-instructions`}
                                            label="Instructions"
                                            error={
                                                errors[
                                                    `methods.${m.code}.instructions`
                                                ]
                                            }
                                        >
                                            <Textarea
                                                id={`${m.code}-instructions`}
                                                name={`methods[${m.code}][instructions]`}
                                                rows={3}
                                                defaultValue={m.instructions}
                                                required
                                            />
                                        </FormField>
                                    </CardContent>
                                </Card>
                            ))}
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

PaymentSettings.layout = {
    breadcrumbs: [{ title: 'Payments', href: settingsRoute() }],
};
