import { Form, Head, Link } from '@inertiajs/react';
import CustomerController from '@/actions/App/Http/Controllers/Admin/CustomerController';
import { FormField } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { StatusBadge } from '@/components/admin/status-badge';
import { AddressBlock, formatDate } from '@/components/storefront/order-view';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { index } from '@/routes/admin/customers';
import { show as orderShow } from '@/routes/admin/orders';
import type { Address, MoneyValue, OrderSummary } from '@/types';

type Props = {
    customer: {
        id: number;
        first_name: string | null;
        last_name: string | null;
        name: string;
        email: string;
        phone: string | null;
        accepts_marketing: boolean;
        registered: boolean;
        created_at: string | null;
    };
    addresses: Address[];
    orders: OrderSummary[];
    netSpent: MoneyValue[];
    canManage: boolean;
};

export default function CustomerShow({
    customer,
    addresses,
    orders,
    netSpent,
    canManage,
}: Props) {
    return (
        <>
            <Head title={customer.name} />
            <AdminPage>
                <PageHeader
                    title={customer.name}
                    description={`${customer.email} · ${customer.registered ? 'Registered account' : 'Guest'}${customer.created_at ? ` · since ${formatDate(customer.created_at)}` : ''}`}
                />
                <div className="grid gap-6 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Orders</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {orders.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No orders yet.
                                </p>
                            ) : (
                                <ul className="grid divide-y text-sm">
                                    {orders.map((o) => (
                                        <li
                                            key={o.number}
                                            className="flex flex-wrap items-center justify-between gap-2 py-2"
                                        >
                                            <Link
                                                href={orderShow(o.number)}
                                                className="font-medium hover:underline"
                                            >
                                                {o.number}
                                            </Link>
                                            <span className="text-muted-foreground">
                                                {formatDate(o.placed_at)}
                                            </span>
                                            <StatusBadge status={o.status} />
                                            <span className="tabular-nums">
                                                {o.grand_total.formatted}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                            {netSpent.length > 0 && (
                                <p className="mt-4 text-sm text-muted-foreground">
                                    Net paid (excluding refunds):{' '}
                                    {netSpent
                                        .map((m) => m.formatted)
                                        .join(', ')}
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    <div className="grid content-start gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Profile</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <Form
                                    {...CustomerController.update.form(
                                        customer.id,
                                    )}
                                    options={{ preserveScroll: true }}
                                    className="grid gap-3"
                                >
                                    {({ processing, errors }) => (
                                        <fieldset
                                            disabled={!canManage}
                                            className="grid gap-3"
                                        >
                                            <FormField
                                                id="first_name"
                                                label="First name"
                                                error={errors.first_name}
                                            >
                                                <Input
                                                    id="first_name"
                                                    name="first_name"
                                                    defaultValue={
                                                        customer.first_name ??
                                                        ''
                                                    }
                                                />
                                            </FormField>
                                            <FormField
                                                id="last_name"
                                                label="Last name"
                                                error={errors.last_name}
                                            >
                                                <Input
                                                    id="last_name"
                                                    name="last_name"
                                                    defaultValue={
                                                        customer.last_name ?? ''
                                                    }
                                                />
                                            </FormField>
                                            <FormField
                                                id="phone"
                                                label="Phone"
                                                error={errors.phone}
                                            >
                                                <Input
                                                    id="phone"
                                                    name="phone"
                                                    defaultValue={
                                                        customer.phone ?? ''
                                                    }
                                                />
                                            </FormField>
                                            <label className="flex items-center gap-2 text-sm">
                                                <Checkbox
                                                    name="accepts_marketing"
                                                    value="1"
                                                    defaultChecked={
                                                        customer.accepts_marketing
                                                    }
                                                />
                                                Accepts marketing email
                                            </label>
                                            {canManage && (
                                                <div>
                                                    <Button
                                                        type="submit"
                                                        size="sm"
                                                        disabled={processing}
                                                    >
                                                        Save
                                                    </Button>
                                                </div>
                                            )}
                                        </fieldset>
                                    )}
                                </Form>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle>Addresses</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                {addresses.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No saved addresses.
                                    </p>
                                ) : (
                                    addresses.map((a) => (
                                        <AddressBlock
                                            key={a.id}
                                            address={{ ...a, type: 'saved' }}
                                        />
                                    ))
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </AdminPage>
        </>
    );
}

CustomerShow.layout = {
    breadcrumbs: [{ title: 'Customers', href: index() }],
};
