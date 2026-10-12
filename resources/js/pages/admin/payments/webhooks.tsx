import { Form, Head, Link } from '@inertiajs/react';
import PaymentWebhookEventController from '@/actions/App/Http/Controllers/Admin/PaymentWebhookEventController';
import { EmptyState } from '@/components/admin/empty-state';
import { NativeSelect } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useCan } from '@/hooks/use-can';
import { show } from '@/routes/admin/orders';
import { settings as settingsRoute } from '@/routes/admin/payments';
import { index } from '@/routes/admin/payments/webhooks';
import type { Paginated } from '@/types';

type WebhookEvent = {
    id: number;
    provider: string;
    event_id: string;
    type: string;
    status: string;
    attempts: number;
    error: string | null;
    order_number: string | null;
    received_at: string;
    processed_at: string | null;
};

const statusVariant: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    processed: 'default',
    ignored: 'outline',
    received: 'secondary',
    failed: 'destructive',
};

export default function PaymentWebhooks({
    events,
    filters,
    statuses,
}: {
    events: Paginated<WebhookEvent>;
    filters: { status: string };
    statuses: string[];
}) {
    const can = useCan();

    return (
        <>
            <Head title="Payment events" />
            <AdminPage>
                <PageHeader
                    title="Payment events"
                    description="Every verified webhook received from payment providers. Events about payments this store did not create are ignored."
                />
                <Form
                    {...index.form()}
                    className="flex flex-wrap items-end gap-3"
                >
                    <div className="grid gap-2">
                        <Label htmlFor="status">Status</Label>
                        <NativeSelect
                            id="status"
                            name="status"
                            defaultValue={filters.status}
                        >
                            <option value="">Any status</option>
                            {statuses.map((s) => (
                                <option key={s} value={s}>
                                    {s}
                                </option>
                            ))}
                        </NativeSelect>
                    </div>
                    <Button type="submit" variant="secondary">
                        Apply
                    </Button>
                </Form>

                {events.data.length === 0 ? (
                    <EmptyState
                        title="No payment events"
                        description="Events appear here once a payment provider sends a webhook."
                    />
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Received</TableHead>
                                <TableHead>Event</TableHead>
                                <TableHead>Order</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>
                                    <span className="sr-only">Actions</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {events.data.map((e) => (
                                <TableRow key={e.id}>
                                    <TableCell className="whitespace-nowrap">
                                        {new Date(
                                            e.received_at,
                                        ).toLocaleString()}
                                    </TableCell>
                                    <TableCell>
                                        <div className="font-mono text-xs">
                                            {e.type}
                                        </div>
                                        <div className="font-mono text-xs text-muted-foreground">
                                            {e.provider} · {e.event_id}
                                        </div>
                                        {e.error && (
                                            <div className="text-xs text-destructive">
                                                {e.error}
                                            </div>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        {e.order_number ? (
                                            <Link
                                                href={show(e.order_number)}
                                                className="hover:underline"
                                            >
                                                {e.order_number}
                                            </Link>
                                        ) : (
                                            '—'
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant={
                                                statusVariant[e.status] ??
                                                'outline'
                                            }
                                        >
                                            {e.status}
                                        </Badge>
                                        {e.attempts > 1 && (
                                            <div className="text-xs text-muted-foreground">
                                                {e.attempts} attempts
                                            </div>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {e.status === 'failed' &&
                                            can('payments.manage') && (
                                                <Form
                                                    {...PaymentWebhookEventController.retry.form(
                                                        e.id,
                                                    )}
                                                    options={{
                                                        preserveScroll: true,
                                                    }}
                                                >
                                                    {({ processing }) => (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            disabled={
                                                                processing
                                                            }
                                                        >
                                                            Retry
                                                        </Button>
                                                    )}
                                                </Form>
                                            )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}

                <Pagination
                    from={events.from}
                    to={events.to}
                    total={events.total}
                    prevUrl={events.prev_page_url}
                    nextUrl={events.next_page_url}
                />
            </AdminPage>
        </>
    );
}

PaymentWebhooks.layout = {
    breadcrumbs: [
        { title: 'Payments', href: settingsRoute() },
        { title: 'Payment events', href: index() },
    ],
};
