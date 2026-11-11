import { Form, Head, Link } from '@inertiajs/react';
import { EmptyState } from '@/components/admin/empty-state';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import type { Paginated } from '@/types';

type ReturnRow = {
    id: number;
    status: string;
    order_number: string | null;
    order_url: string;
    customer: string;
    email: string;
    customer_comment: string;
    staff_comment: string | null;
    created_at: string | null;
    items: { name: string; quantity: number }[];
    approve_url: string;
    reject_url: string;
};

type Props = {
    returns: Paginated<ReturnRow>;
    status: string;
    counts: Record<string, number>;
    indexUrl: string;
    commentMin: number;
};

const TABS = [
    { status: 'pending', label: 'Waiting for review' },
    { status: 'approved', label: 'Approved' },
    { status: 'rejected', label: 'Rejected' },
];

export default function ReturnsAdmin({
    returns,
    status,
    counts,
    indexUrl,
    commentMin,
}: Props) {
    return (
        <>
            <Head title="Returns" />
            <AdminPage>
                <div className="grid items-start gap-8 md:grid-cols-[13rem_1fr]">
                    <nav
                        aria-label="Return status"
                        className="grid content-start gap-1 text-sm"
                    >
                        <p className="px-2 pb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            Returns
                        </p>
                        {TABS.map((tab) => (
                            <Link
                                key={tab.status}
                                href={`${indexUrl}?status=${tab.status}`}
                                aria-current={
                                    tab.status === status ? 'page' : undefined
                                }
                                className="flex items-center justify-between gap-2 rounded px-2 py-1.5 hover:bg-muted aria-[current=page]:bg-muted aria-[current=page]:font-medium"
                            >
                                <span>{tab.label}</span>
                                <span className="text-muted-foreground tabular-nums">
                                    {counts[tab.status] ?? 0}
                                </span>
                            </Link>
                        ))}
                    </nav>
                    <div className="grid min-w-0 content-start gap-5">
                        <PageHeader
                            title="Returns"
                            description="Approve or reject a customer's return request. A comment is required either way. This does not refund the payment or restock the items."
                        />

                        {returns.data.length === 0 ? (
                            <EmptyState
                                title="No returns here"
                                description="Requests appear here after a customer asks to return items from an order."
                            />
                        ) : (
                            <ul className="grid gap-3">
                                {returns.data.map((row) => (
                                    <li key={row.id}>
                                        <Card>
                                            <CardHeader>
                                                <CardTitle className="text-base">
                                                    <Link
                                                        href={row.order_url}
                                                        className="hover:underline"
                                                    >
                                                        Order {row.order_number}
                                                    </Link>
                                                    <span className="ml-2 text-sm font-normal text-muted-foreground">
                                                        {row.customer} ·{' '}
                                                        {row.email}
                                                    </span>
                                                </CardTitle>
                                            </CardHeader>
                                            <CardContent className="grid gap-3 text-sm">
                                                <ul>
                                                    {row.items.map((item) => (
                                                        <li
                                                            key={`${row.id}-${item.name}`}
                                                        >
                                                            {item.name} ×{' '}
                                                            {item.quantity}
                                                        </li>
                                                    ))}
                                                </ul>
                                                <p>{row.customer_comment}</p>
                                                {row.staff_comment && (
                                                    <p className="text-muted-foreground">
                                                        Staff:{' '}
                                                        {row.staff_comment}
                                                    </p>
                                                )}
                                                {row.status === 'pending' && (
                                                    <div className="grid gap-3 sm:grid-cols-2">
                                                        <DecisionForm
                                                            action={
                                                                row.approve_url
                                                            }
                                                            label="Approve"
                                                            commentMin={
                                                                commentMin
                                                            }
                                                        />
                                                        <DecisionForm
                                                            action={
                                                                row.reject_url
                                                            }
                                                            label="Reject"
                                                            commentMin={
                                                                commentMin
                                                            }
                                                        />
                                                    </div>
                                                )}
                                            </CardContent>
                                        </Card>
                                    </li>
                                ))}
                            </ul>
                        )}
                        <Pagination
                            from={returns.from}
                            to={returns.to}
                            total={returns.total}
                            prevUrl={returns.prev_page_url}
                            nextUrl={returns.next_page_url}
                        />
                    </div>
                </div>
            </AdminPage>
        </>
    );
}

function DecisionForm({
    action,
    label,
    commentMin,
}: {
    action: string;
    label: string;
    commentMin: number;
}) {
    return (
        <Form action={action} method="post" className="grid gap-2">
            {({ errors, processing }) => (
                <>
                    <Textarea
                        name="comment"
                        required
                        minLength={commentMin}
                        rows={3}
                        placeholder={`${label} comment`}
                        aria-label={`${label} comment`}
                    />
                    <InputError message={errors.comment} />
                    <div>
                        <Button
                            type="submit"
                            variant={label === 'Reject' ? 'outline' : 'default'}
                            disabled={processing}
                        >
                            {label}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
