import { ExtensionSlot } from '@/components/extensions/extension-slot';
import type { SlotItem } from '@/components/extensions/extension-slot';
import { Head, Link } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import { AuditTable } from '@/components/admin/audit-table';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { StatusBadge } from '@/components/admin/status-badge';
import { formatDate } from '@/components/storefront/order-view';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes/admin';
import { index as auditIndex } from '@/routes/admin/audit-logs';
import { index as inventoryIndex } from '@/routes/admin/inventory';
import { index as ordersIndex, show as orderShow } from '@/routes/admin/orders';
import type { AuditLogEntry, MoneyValue, OrderSummary } from '@/types';

type Headline = {
    gross_sales: MoneyValue;
    orders: number;
    average_order_value: MoneyValue | null;
};

type Props = {
    store: { store_name: string; default_currency: string; timezone: string };
    sales: { today: Headline; last30: Headline } | null;
    operations: {
        pending_payment: number;
        awaiting_fulfillment: number;
        low_stock: number;
    } | null;
    recentOrders: OrderSummary[] | null;
    recentActivity: AuditLogEntry[] | null;
    warnings: string[];
    widgets: (SlotItem & { title: string })[];
};

export default function AdminDashboard({
    store,
    sales,
    operations,
    recentOrders,
    recentActivity,
    warnings,
    widgets,
}: Props) {
    return (
        <>
            <Head title="Dashboard" />
            <AdminPage>
                <PageHeader
                    title={store.store_name}
                    description={`Currency ${store.default_currency} · Timezone ${store.timezone}`}
                />

                {warnings.map((warning) => (
                    <Alert key={warning}>
                        <TriangleAlert />
                        <AlertTitle>Action recommended</AlertTitle>
                        <AlertDescription>{warning}</AlertDescription>
                    </Alert>
                ))}

                {sales && (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Stat
                            label="Sales today"
                            value={sales.today.gross_sales.formatted}
                            hint={`${sales.today.orders} orders`}
                        />
                        <Stat
                            label="Sales, last 30 days"
                            value={sales.last30.gross_sales.formatted}
                            hint={`${sales.last30.orders} orders`}
                        />
                        <Stat
                            label="Average order value (30 days)"
                            value={
                                sales.last30.average_order_value?.formatted ??
                                '—'
                            }
                        />
                        {operations && (
                            <Stat
                                label="Awaiting payment"
                                value={String(operations.pending_payment)}
                            />
                        )}
                    </div>
                )}

                {operations && (
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between">
                                <div className="grid gap-1">
                                    <CardDescription>
                                        Ready to fulfil
                                    </CardDescription>
                                    <CardTitle className="text-3xl tabular-nums">
                                        {operations.awaiting_fulfillment}
                                    </CardTitle>
                                </div>
                                <Button variant="outline" size="sm" asChild>
                                    <Link
                                        href={ordersIndex({
                                            query: {
                                                fulfillment_status:
                                                    'unfulfilled',
                                            },
                                        })}
                                    >
                                        View orders
                                    </Link>
                                </Button>
                            </CardHeader>
                        </Card>
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between">
                                <div className="grid gap-1">
                                    <CardDescription>
                                        Low-stock variants
                                    </CardDescription>
                                    <CardTitle className="text-3xl tabular-nums">
                                        {operations.low_stock}
                                    </CardTitle>
                                </div>
                                <Button variant="outline" size="sm" asChild>
                                    <Link
                                        href={inventoryIndex({
                                            query: { stock: 'low' },
                                        })}
                                    >
                                        View inventory
                                    </Link>
                                </Button>
                            </CardHeader>
                        </Card>
                    </div>
                )}

                {recentOrders !== null && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Recent orders</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {recentOrders.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No orders yet.
                                </p>
                            ) : (
                                <ul className="grid divide-y text-sm">
                                    {recentOrders.map((o) => (
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
                                                {o.email}
                                            </span>
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
                        </CardContent>
                    </Card>
                )}

                {widgets.length > 0 && (
                    <div className="grid gap-4 md:grid-cols-2">
                        {widgets.map((widget) => (
                            <Card key={widget.key}>
                                <CardHeader>
                                    <CardTitle>{widget.title}</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <ExtensionSlot items={[widget]} />
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}

                {recentActivity !== null && (
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <div className="space-y-1.5">
                                <CardTitle>Recent activity</CardTitle>
                                <CardDescription>
                                    Latest entries from the audit log.
                                </CardDescription>
                            </div>
                            <Button variant="outline" size="sm" asChild>
                                <Link href={auditIndex()}>View all</Link>
                            </Button>
                        </CardHeader>
                        <CardContent>
                            <AuditTable entries={recentActivity} compact />
                        </CardContent>
                    </Card>
                )}
            </AdminPage>
        </>
    );
}

function Stat({
    label,
    value,
    hint,
}: {
    label: string;
    value: string;
    hint?: string;
}) {
    return (
        <Card>
            <CardHeader>
                <CardDescription>{label}</CardDescription>
                <CardTitle className="text-2xl tabular-nums">{value}</CardTitle>
                {hint && <CardDescription>{hint}</CardDescription>}
            </CardHeader>
        </Card>
    );
}

AdminDashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
