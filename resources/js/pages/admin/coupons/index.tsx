import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { EmptyState } from '@/components/admin/empty-state';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { create, edit, index } from '@/routes/admin/coupons';
import type { Paginated } from '@/types';

type CouponRow = {
    id: number;
    code: string;
    description: string | null;
    type: string;
    value: string;
    currency: string | null;
    times_used: number;
    usage_limit: number | null;
    starts_at: string | null;
    ends_at: string | null;
    is_active: boolean;
};

function describe(c: CouponRow): string {
    const amount = Number(c.value);

    return c.type === 'percentage'
        ? `${amount}% off`
        : `${amount.toFixed(2)} ${c.currency} off`;
}

export default function CouponsIndex({
    coupons,
    canManage,
}: {
    coupons: Paginated<CouponRow>;
    canManage: boolean;
}) {
    return (
        <>
            <Head title="Discounts" />
            <AdminPage>
                <PageHeader
                    title="Discounts"
                    description="Coupon codes customers enter in the cart."
                    actions={
                        canManage && (
                            <Button asChild>
                                <Link href={create()}>
                                    <Plus /> New coupon
                                </Link>
                            </Button>
                        )
                    }
                />
                {coupons.data.length === 0 ? (
                    <EmptyState title="No coupons yet" />
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Code</TableHead>
                                <TableHead>Discount</TableHead>
                                <TableHead>Used</TableHead>
                                <TableHead>Status</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {coupons.data.map((c) => {
                                const expired =
                                    c.ends_at !== null &&
                                    new Date(c.ends_at) < new Date();

                                return (
                                    <TableRow key={c.id}>
                                        <TableCell>
                                            {canManage ? (
                                                <Link
                                                    href={edit(c.id)}
                                                    className="font-mono font-medium hover:underline"
                                                >
                                                    {c.code}
                                                </Link>
                                            ) : (
                                                <span className="font-mono font-medium">
                                                    {c.code}
                                                </span>
                                            )}
                                            {c.description && (
                                                <div className="text-xs text-muted-foreground">
                                                    {c.description}
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell>{describe(c)}</TableCell>
                                        <TableCell className="tabular-nums">
                                            {c.times_used}
                                            {c.usage_limit !== null &&
                                                ` / ${c.usage_limit}`}
                                        </TableCell>
                                        <TableCell>
                                            {!c.is_active ? (
                                                <Badge variant="outline">
                                                    Inactive
                                                </Badge>
                                            ) : expired ? (
                                                <Badge variant="secondary">
                                                    Expired
                                                </Badge>
                                            ) : (
                                                <Badge>Active</Badge>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                );
                            })}
                        </TableBody>
                    </Table>
                )}
                <Pagination
                    from={coupons.from}
                    to={coupons.to}
                    total={coupons.total}
                    prevUrl={coupons.prev_page_url}
                    nextUrl={coupons.next_page_url}
                />
            </AdminPage>
        </>
    );
}

CouponsIndex.layout = {
    breadcrumbs: [{ title: 'Discounts', href: index() }],
};
