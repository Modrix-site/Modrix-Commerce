import { Head, Link, router } from '@inertiajs/react';
import { Calendar, Clock, FileText, Filter, ListPlus, Settings, Truck } from 'lucide-react';
import { useState } from 'react';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

type ScheduleItem = {
    id: number;
    order_id: number;
    order_number: string;
    delivery_date: string;
    formatted_date: string;
    slot_id: number | null;
    slot_name: string;
    slot_window: string | null;
    delivery_instructions: string | null;
    customer_name: string;
    customer_email: string | null;
    customer_phone: string | null;
    order_status: string;
    order_total: string;
    order_url: string | null;
};

type SlotSummary = {
    id: number;
    name: string;
    time_range: string;
};

type Props = {
    schedules: ScheduleItem[];
    filters: {
        date_from: string;
        date_to: string;
        slot_id: number | null;
    };
    stats: {
        total_scheduled: number;
        today_deliveries: number;
        active_slots: number;
    };
    slots: SlotSummary[];
    urls: {
        index: string;
        slots: string;
        settings: string;
    };
};

export default function DeliverySlotsAdminIndex({
    schedules,
    filters,
    stats,
    slots,
    urls,
}: Props) {
    const [dateFrom, setDateFrom] = useState(filters.date_from);
    const [dateTo, setDateTo] = useState(filters.date_to);
    const [slotId, setSlotId] = useState<string>(filters.slot_id ? String(filters.slot_id) : '');

    const handleFilter = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            urls.index,
            {
                date_from: dateFrom,
                date_to: dateTo,
                slot_id: slotId || undefined,
            },
            { preserveState: true }
        );
    };

    const handleQuickDate = (days: number) => {
        const today = new Date().toISOString().split('T')[0];
        const target = new Date();
        target.setDate(target.getDate() + days);
        const toStr = target.toISOString().split('T')[0];

        setDateFrom(today);
        setDateTo(toStr);

        router.get(
            urls.index,
            {
                date_from: today,
                date_to: toStr,
                slot_id: slotId || undefined,
            },
            { preserveState: true }
        );
    };

    return (
        <>
            <Head title="Delivery Slots Schedule" />

            <AdminPage>
                <PageHeader
                    title="Delivery Schedule & Slots"
                    description="Monitor scheduled deliveries, manage customer time slots, and configure blackout dates."
                    actions={
                        <div className="flex items-center gap-2">
                            <Link href={urls.slots}>
                                <Button variant="outline" size="sm">
                                    <ListPlus className="mr-1.5 size-4" />
                                    Manage Slots
                                </Button>
                            </Link>
                            <Link href={urls.settings}>
                                <Button variant="outline" size="sm">
                                    <Settings className="mr-1.5 size-4" />
                                    Settings & Blackouts
                                </Button>
                            </Link>
                        </div>
                    }
                />

                {/* Metrics */}
                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Scheduled Deliveries
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center justify-between">
                                <div className="text-2xl font-bold text-sky-600">
                                    {stats.total_scheduled}
                                </div>
                                <Truck className="size-5 text-sky-500" />
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">From today forward</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Deliveries Today
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center justify-between">
                                <div className="text-2xl font-bold text-amber-600">
                                    {stats.today_deliveries}
                                </div>
                                <Calendar className="size-5 text-amber-500" />
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">Requiring fulfillment today</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Active Time Windows
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center justify-between">
                                <div className="text-2xl font-bold">{stats.active_slots}</div>
                                <Clock className="size-5 text-muted-foreground" />
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">Configured delivery slots</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters */}
                <Card>
                    <CardContent className="p-4">
                        <form onSubmit={handleFilter} className="flex flex-wrap items-end gap-3">
                            <div className="space-y-1">
                                <span className="text-xs font-medium text-muted-foreground">From Date</span>
                                <Input
                                    type="date"
                                    value={dateFrom}
                                    onChange={(e) => setDateFrom(e.target.value)}
                                    className="h-9 w-36 text-xs"
                                />
                            </div>

                            <div className="space-y-1">
                                <span className="text-xs font-medium text-muted-foreground">To Date</span>
                                <Input
                                    type="date"
                                    value={dateTo}
                                    onChange={(e) => setDateTo(e.target.value)}
                                    className="h-9 w-36 text-xs"
                                />
                            </div>

                            <div className="space-y-1">
                                <span className="text-xs font-medium text-muted-foreground">Slot</span>
                                <select
                                    value={slotId}
                                    onChange={(e) => setSlotId(e.target.value)}
                                    className="h-9 rounded-md border border-input bg-background px-3 text-xs"
                                >
                                    <option value="">All Time Slots</option>
                                    {slots.map((s) => (
                                        <option key={s.id} value={s.id}>
                                            {s.name} ({s.time_range})
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <Button type="submit" size="sm" className="h-9">
                                <Filter className="mr-1.5 size-3.5" />
                                Filter
                            </Button>

                            <div className="ml-auto flex items-center gap-1.5">
                                <Button
                                    type="button"
                                    variant="secondary"
                                    size="sm"
                                    className="h-9 text-xs"
                                    onClick={() => handleQuickDate(0)}
                                >
                                    Today
                                </Button>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    size="sm"
                                    className="h-9 text-xs"
                                    onClick={() => handleQuickDate(7)}
                                >
                                    Next 7 Days
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                {/* Schedule Table */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base font-semibold">Scheduled Deliveries</CardTitle>
                        <CardDescription>
                            Orders booked for the selected date range ({filters.date_from} to{' '}
                            {filters.date_to}).
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="p-0">
                        {schedules.length === 0 ? (
                            <div className="flex flex-col items-center justify-center p-12 text-center">
                                <Truck className="size-10 text-muted-foreground/40" />
                                <h3 className="mt-3 text-sm font-semibold">No deliveries scheduled</h3>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    No customer orders have delivery booked in this date range.
                                </p>
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Delivery Date</TableHead>
                                        <TableHead>Time Slot</TableHead>
                                        <TableHead>Order #</TableHead>
                                        <TableHead>Customer</TableHead>
                                        <TableHead>Instructions</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">Total</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {schedules.map((row) => (
                                        <TableRow key={row.id}>
                                            <TableCell className="font-medium text-xs whitespace-nowrap">
                                                {row.formatted_date}
                                            </TableCell>
                                            <TableCell className="text-xs whitespace-nowrap">
                                                <div className="font-semibold text-foreground">
                                                    {row.slot_name}
                                                </div>
                                                {row.slot_window && (
                                                    <div className="text-[11px] text-muted-foreground">
                                                        {row.slot_window}
                                                    </div>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-xs">
                                                {row.order_url ? (
                                                    <Link
                                                        href={row.order_url}
                                                        className="font-bold text-sky-600 hover:underline"
                                                    >
                                                        {row.order_number}
                                                    </Link>
                                                ) : (
                                                    <span>{row.order_number}</span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-xs">
                                                <div className="font-medium">{row.customer_name}</div>
                                                <div className="text-[11px] text-muted-foreground">
                                                    {row.customer_phone || row.customer_email || '-'}
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-xs max-w-[200px]">
                                                {row.delivery_instructions ? (
                                                    <div className="flex items-start gap-1 text-[11px] text-muted-foreground italic">
                                                        <FileText className="mt-0.5 size-3 shrink-0 text-sky-500" />
                                                        <span className="line-clamp-2">
                                                            {row.delivery_instructions}
                                                        </span>
                                                    </div>
                                                ) : (
                                                    <span className="text-[11px] text-muted-foreground">-</span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-xs">
                                                <Badge variant="outline" className="text-[10px] uppercase">
                                                    {row.order_status}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right text-xs font-semibold">
                                                {row.order_total}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </AdminPage>
        </>
    );
}
