import { Form, Head, Link } from '@inertiajs/react';
import { Download, Ellipsis, Search, SlidersHorizontal } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/admin/empty-state';
import { NativeSelect } from '@/components/admin/form-field';
import { OrderBulkBar } from '@/components/admin/orders/order-bulk-bar';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import { StatusBadge } from '@/components/admin/status-badge';
import { formatDate } from '@/components/storefront/order-view';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
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
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import { exportMethod, index, show } from '@/routes/admin/orders';
import type { Option, OrderSummary, Paginated } from '@/types';

type Filters = {
    search: string;
    display_status: string;
    ready: string;
    payment_status: string;
    fulfillment_status: string;
    from: string;
    to: string;
};

type AdminOrderSummary = OrderSummary & {
    customer_name: string | null;
    product_names: string[];
};

type Props = {
    orders: Paginated<AdminOrderSummary>;
    filters: Filters;
    displayStatuses: Option[];
    statusCounts: Record<string, number>;
    paymentStatuses: Option[];
    fulfillmentStatuses: Option[];
};

const columnLabels = {
    customer: 'Customer',
    product: 'Product',
    status: 'Status',
    payment: 'Payment',
    date: 'Date',
};
type Column = keyof typeof columnLabels;

export default function OrdersIndex({
    orders,
    filters,
    displayStatuses,
    statusCounts,
    paymentStatuses,
    fulfillmentStatuses,
}: Props) {
    const filtered = Object.values(filters).some((value) => value !== '');
    const can = useCan();
    const initials = useInitials();
    const [selected, setSelected] = useState<string[]>([]);
    const [columns, setColumns] = useState<Column[]>([
        'customer',
        'product',
        'status',
        'payment',
        'date',
    ]);
    const visible = (column: Column) => columns.includes(column);
    const pageNumbers = orders.data.map((order) => order.number);
    const allSelected =
        pageNumbers.length > 0 &&
        pageNumbers.every((number) => selected.includes(number));
    const someSelected = pageNumbers.some((number) =>
        selected.includes(number),
    );
    const toggle = (number: string, on: boolean) =>
        setSelected((current) =>
            on
                ? [...new Set([...current, number])]
                : current.filter((value) => value !== number),
        );
    const toggleAll = (on: boolean) =>
        setSelected((current) =>
            on
                ? [...new Set([...current, ...pageNumbers])].slice(0, 100)
                : current.filter((number) => !pageNumbers.includes(number)),
        );
    const activeFilters = Object.fromEntries(
        Object.entries(filters).filter(([, value]) => value !== ''),
    );
    const quickStatuses = [
        '',
        'delivered',
        'processing',
        'pending',
        'cancelled',
    ];
    const advancedActive = [
        'ready',
        'payment_status',
        'fulfillment_status',
        'from',
        'to',
    ].some((key) => filters[key as keyof Filters] !== '');

    const select = (name: keyof Filters, label: string, options: Option[]) => (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            <NativeSelect id={name} name={name} defaultValue={filters[name]}>
                <option value="">Any</option>
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </NativeSelect>
        </div>
    );

    return (
        <>
            <Head title="Orders" />
            <AdminPage>
                <PageHeader
                    title="Orders"
                    description="Manage and track all customer orders."
                />
                <nav
                    aria-label="Order status"
                    className="flex w-fit max-w-full gap-1 overflow-x-auto rounded-lg bg-muted p-1"
                >
                    {quickStatuses.map((status) => (
                        <Link
                            key={status}
                            href={index({
                                query: {
                                    ...activeFilters,
                                    display_status: status,
                                },
                            })}
                            preserveScroll
                            aria-current={
                                filters.display_status === status
                                    ? 'page'
                                    : undefined
                            }
                            className={cn(
                                'rounded-md px-3 py-1.5 text-xs font-medium whitespace-nowrap transition-colors',
                                filters.display_status === status
                                    ? 'bg-card text-foreground shadow-xs'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {status === ''
                                ? 'All'
                                : (displayStatuses.find(
                                      (option) => option.value === status,
                                  )?.label ?? status)}
                        </Link>
                    ))}
                    {filters.display_status &&
                        !quickStatuses.includes(filters.display_status) && (
                            <span className="rounded-md bg-card px-3 py-1.5 text-xs font-medium shadow-xs">
                                {
                                    displayStatuses.find(
                                        (option) =>
                                            option.value ===
                                            filters.display_status,
                                    )?.label
                                }
                            </span>
                        )}
                </nav>

                <Form
                    key={JSON.stringify(filters)}
                    {...index.form()}
                    options={{ preserveScroll: true }}
                    className="grid gap-3"
                >
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex w-full items-center gap-2 sm:w-auto">
                            <div className="relative w-full sm:w-96">
                                <Search
                                    className="pointer-events-none absolute top-2.5 left-3 size-4 text-muted-foreground"
                                    aria-hidden
                                />
                                <Input
                                    id="search"
                                    name="search"
                                    type="search"
                                    defaultValue={filters.search}
                                    placeholder="Search orders..."
                                    aria-label="Search order number or email"
                                    className="pl-9"
                                />
                            </div>
                            <Button type="submit" variant="outline" size="sm">
                                Search
                            </Button>
                        </div>
                        <div className="flex items-center gap-2">
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button variant="outline" size="sm">
                                        <SlidersHorizontal
                                            className="size-3.5"
                                            aria-hidden
                                        />
                                        Columns
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent
                                    align="end"
                                    className="w-44"
                                >
                                    <DropdownMenuLabel>
                                        Visible columns
                                    </DropdownMenuLabel>
                                    {(
                                        Object.keys(columnLabels) as Column[]
                                    ).map((column) => (
                                        <DropdownMenuCheckboxItem
                                            key={column}
                                            checked={visible(column)}
                                            onSelect={(event) =>
                                                event.preventDefault()
                                            }
                                            onCheckedChange={(checked) =>
                                                setColumns((current) =>
                                                    checked
                                                        ? [...current, column]
                                                        : current.filter(
                                                              (value) =>
                                                                  value !==
                                                                  column,
                                                          ),
                                                )
                                            }
                                        >
                                            {columnLabels[column]}
                                        </DropdownMenuCheckboxItem>
                                    ))}
                                </DropdownMenuContent>
                            </DropdownMenu>
                            <Button variant="outline" size="sm" asChild>
                                <a
                                    href={exportMethod.url({
                                        query: activeFilters,
                                    })}
                                    download
                                >
                                    <Download
                                        className="size-3.5"
                                        aria-hidden
                                    />
                                    Export
                                </a>
                            </Button>
                        </div>
                    </div>
                    <details
                        open={advancedActive || undefined}
                        className="group/filters"
                    >
                        <summary className="w-fit cursor-pointer text-xs text-muted-foreground hover:text-foreground">
                            Advanced filters{advancedActive ? ' · Active' : ''}
                        </summary>
                        <div className="mt-3 grid gap-3 rounded-lg border bg-card p-4 sm:grid-cols-2 xl:grid-cols-5">
                            <div className="grid gap-2">
                                <Label htmlFor="display_status">Status</Label>
                                <NativeSelect
                                    id="display_status"
                                    name="display_status"
                                    defaultValue={filters.display_status}
                                >
                                    <option value="">All statuses</option>
                                    {displayStatuses.map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label} (
                                            {statusCounts[option.value] ?? 0})
                                        </option>
                                    ))}
                                </NativeSelect>
                            </div>
                            {select(
                                'payment_status',
                                'Payment',
                                paymentStatuses,
                            )}
                            {select(
                                'fulfillment_status',
                                'Fulfilment',
                                fulfillmentStatuses,
                            )}
                            <div className="grid gap-2">
                                <Label htmlFor="from">From</Label>
                                <Input
                                    id="from"
                                    name="from"
                                    type="date"
                                    defaultValue={filters.from}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="to">To</Label>
                                <Input
                                    id="to"
                                    name="to"
                                    type="date"
                                    defaultValue={filters.to}
                                />
                            </div>
                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox
                                    name="ready"
                                    value="1"
                                    defaultChecked={filters.ready === '1'}
                                />
                                Ready to fulfil
                            </label>
                            <p className="self-center text-xs text-muted-foreground sm:col-span-2">
                                Dates use the store timezone.
                            </p>
                            <div className="flex gap-2 xl:col-span-2 xl:justify-end">
                                <Button type="submit" size="sm">
                                    Apply filters
                                </Button>
                                {filtered && (
                                    <Button variant="ghost" size="sm" asChild>
                                        <Link href={index()}>Clear all</Link>
                                    </Button>
                                )}
                            </div>
                        </div>
                    </details>
                    {filtered && (
                        <Link
                            href={index()}
                            className="w-fit text-xs text-primary hover:underline"
                        >
                            Clear all filters
                        </Link>
                    )}
                </Form>

                {orders.data.length === 0 ? (
                    <EmptyState
                        title={
                            filtered
                                ? 'No orders match these filters'
                                : 'No orders yet'
                        }
                        description={
                            filtered
                                ? 'Try different filters.'
                                : 'Orders placed on the storefront appear here.'
                        }
                    />
                ) : (
                    <Table className="min-w-[60rem]">
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-9">
                                    <Checkbox
                                        aria-label="Select all orders on this page"
                                        checked={
                                            allSelected
                                                ? true
                                                : someSelected
                                                  ? 'indeterminate'
                                                  : false
                                        }
                                        onCheckedChange={(value) =>
                                            toggleAll(value === true)
                                        }
                                    />
                                </TableHead>
                                <TableHead>Order</TableHead>
                                {visible('customer') && (
                                    <TableHead>Customer</TableHead>
                                )}
                                {visible('product') && (
                                    <TableHead>Product</TableHead>
                                )}
                                {visible('status') && (
                                    <TableHead>Status</TableHead>
                                )}
                                {visible('payment') && (
                                    <TableHead>Payment</TableHead>
                                )}
                                {visible('date') && <TableHead>Date</TableHead>}
                                <TableHead className="text-right">
                                    Amount
                                </TableHead>
                                <TableHead className="w-10">
                                    <span className="sr-only">Actions</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {orders.data.map((order) => (
                                <TableRow
                                    key={order.number}
                                    data-state={
                                        selected.includes(order.number)
                                            ? 'selected'
                                            : undefined
                                    }
                                >
                                    <TableCell>
                                        <Checkbox
                                            aria-label={`Select order ${order.number}`}
                                            checked={selected.includes(
                                                order.number,
                                            )}
                                            onCheckedChange={(value) =>
                                                toggle(
                                                    order.number,
                                                    value === true,
                                                )
                                            }
                                        />
                                    </TableCell>
                                    <TableCell>
                                        <Link
                                            href={show(order.number)}
                                            className="font-mono text-xs font-medium whitespace-nowrap hover:text-primary hover:underline"
                                        >
                                            {order.number}
                                        </Link>
                                    </TableCell>
                                    {visible('customer') && (
                                        <TableCell>
                                            <div className="flex items-center gap-2.5">
                                                <Avatar className="size-8 shrink-0">
                                                    <AvatarFallback className="bg-primary/10 text-[11px] font-semibold text-primary">
                                                        {initials(
                                                            order.customer_name ??
                                                                order.email,
                                                        )}
                                                    </AvatarFallback>
                                                </Avatar>
                                                <div>
                                                    <div className="font-medium whitespace-nowrap">
                                                        {order.customer_name ??
                                                            order.email}
                                                    </div>
                                                    {order.customer_name && (
                                                        <div className="text-xs text-muted-foreground">
                                                            {order.email}
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                        </TableCell>
                                    )}
                                    {visible('product') && (
                                        <TableCell>
                                            <span
                                                className="line-clamp-1 max-w-64"
                                                title={order.product_names.join(
                                                    ', ',
                                                )}
                                            >
                                                {order.product_names[0] ?? '—'}
                                            </span>
                                            {order.product_names.length > 1 && (
                                                <span className="text-xs text-muted-foreground">
                                                    +
                                                    {order.product_names
                                                        .length - 1}{' '}
                                                    more
                                                </span>
                                            )}
                                        </TableCell>
                                    )}
                                    {visible('status') && (
                                        <TableCell>
                                            <StatusBadge
                                                status={order.display_status}
                                            />
                                        </TableCell>
                                    )}
                                    {visible('payment') && (
                                        <TableCell>
                                            <StatusBadge
                                                status={order.payment_status}
                                            />
                                        </TableCell>
                                    )}
                                    {visible('date') && (
                                        <TableCell className="whitespace-nowrap">
                                            {formatDate(order.placed_at)}
                                        </TableCell>
                                    )}
                                    <TableCell className="text-right font-semibold whitespace-nowrap tabular-nums">
                                        {order.grand_total.formatted}
                                    </TableCell>
                                    <TableCell>
                                        <Button
                                            asChild
                                            variant="ghost"
                                            size="icon"
                                            className="size-7"
                                        >
                                            <Link
                                                href={show(order.number)}
                                                aria-label={`View order ${order.number}`}
                                            >
                                                <Ellipsis className="size-4" />
                                            </Link>
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
                {selected.length > 0 && (
                    <OrderBulkBar
                        numbers={selected}
                        canManage={can('orders.manage')}
                        onClear={() => setSelected([])}
                    />
                )}
                <Pagination
                    from={orders.from}
                    to={orders.to}
                    total={orders.total}
                    prevUrl={orders.prev_page_url}
                    nextUrl={orders.next_page_url}
                    currentPage={orders.current_page}
                    lastPage={orders.last_page}
                    pageUrl={(page) =>
                        index.url({ query: { ...activeFilters, page } })
                    }
                />
            </AdminPage>
        </>
    );
}

OrdersIndex.layout = { breadcrumbs: [{ title: 'Orders', href: index() }] };
