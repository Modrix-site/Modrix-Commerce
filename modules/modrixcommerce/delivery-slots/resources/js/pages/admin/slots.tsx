import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Check,
    Clock,
    Edit2,
    Plus,
    Power,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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

const DAYS = [
    { value: 0, label: 'Sun' },
    { value: 1, label: 'Mon' },
    { value: 2, label: 'Tue' },
    { value: 3, label: 'Wed' },
    { value: 4, label: 'Thu' },
    { value: 5, label: 'Fri' },
    { value: 6, label: 'Sat' },
];

type SlotData = {
    id: number;
    name: string;
    start_time: string;
    end_time: string;
    cutoff_hours: number;
    max_orders: number;
    fee: number;
    is_active: boolean;
    days_of_week: number[];
    order: number;
    total_bookings: number;
};

type Props = {
    slots: SlotData[];
    urls: {
        index: string;
        slots: string;
        store: string;
        settings: string;
    };
};

export default function DeliverySlotsAdminSlots({ slots, urls }: Props) {
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [editingSlot, setEditingSlot] = useState<SlotData | null>(null);

    const { data, setData, post, put, processing, errors, reset } = useForm({
        name: '',
        start_time: '09:00',
        end_time: '13:00',
        cutoff_hours: 2,
        max_orders: 25,
        fee: 0,
        days_of_week: [0, 1, 2, 3, 4, 5, 6],
        is_active: true,
        order: 0,
    });

    const openCreateDialog = () => {
        reset();
        setEditingSlot(null);
        setIsDialogOpen(true);
    };

    const openEditDialog = (slot: SlotData) => {
        setEditingSlot(slot);
        setData({
            name: slot.name,
            start_time: slot.start_time,
            end_time: slot.end_time,
            cutoff_hours: slot.cutoff_hours,
            max_orders: slot.max_orders,
            fee: slot.fee,
            days_of_week: slot.days_of_week,
            is_active: slot.is_active,
            order: slot.order,
        });
        setIsDialogOpen(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingSlot) {
            put(`${urls.slots}/${editingSlot.id}`, {
                onSuccess: () => setIsDialogOpen(false),
            });
        } else {
            post(urls.store, {
                onSuccess: () => setIsDialogOpen(false),
            });
        }
    };

    const handleToggle = (slot: SlotData) => {
        router.post(
            `${urls.slots}/${slot.id}/toggle`,
            {},
            { preserveScroll: true },
        );
    };

    const handleDelete = (slot: SlotData) => {
        if (confirm(`Are you sure you want to delete slot '${slot.name}'?`)) {
            router.delete(`${urls.slots}/${slot.id}`, { preserveScroll: true });
        }
    };

    const toggleDay = (dayVal: number) => {
        const current = data.days_of_week;
        if (current.includes(dayVal)) {
            setData(
                'days_of_week',
                current.filter((d) => d !== dayVal),
            );
        } else {
            setData('days_of_week', [...current, dayVal].sort());
        }
    };

    return (
        <>
            <Head title="Manage Delivery Time Slots" />

            <AdminPage>
                <PageHeader
                    title="Delivery Time Slots"
                    description="Configure recurring delivery windows, cutoff times, and order capacities."
                    actions={
                        <div className="flex items-center gap-2">
                            <Link href={urls.index}>
                                <Button variant="outline" size="sm">
                                    <ArrowLeft className="mr-1.5 size-4" />
                                    Back to Schedule
                                </Button>
                            </Link>
                            <Button size="sm" onClick={openCreateDialog}>
                                <Plus className="mr-1.5 size-4" />
                                Add Time Slot
                            </Button>
                        </div>
                    }
                />

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base font-semibold">
                            Active Windows
                        </CardTitle>
                        <CardDescription>
                            Each window represents an available delivery slot
                            selectable by customers at checkout.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="p-0">
                        {slots.length === 0 ? (
                            <div className="flex flex-col items-center justify-center p-12 text-center">
                                <Clock className="size-10 text-muted-foreground/40" />
                                <h3 className="mt-3 text-sm font-semibold">
                                    No delivery slots created
                                </h3>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Create your first time slot to enable
                                    scheduled deliveries.
                                </p>
                                <Button
                                    size="sm"
                                    onClick={openCreateDialog}
                                    className="mt-4"
                                >
                                    <Plus className="mr-1.5 size-4" />
                                    Add Time Slot
                                </Button>
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Slot Name</TableHead>
                                        <TableHead>Time Window</TableHead>
                                        <TableHead>Operating Days</TableHead>
                                        <TableHead>Cutoff</TableHead>
                                        <TableHead>Max Orders</TableHead>
                                        <TableHead>Fee</TableHead>
                                        <TableHead>Total Orders</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">
                                            Actions
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {slots.map((slot) => (
                                        <TableRow key={slot.id}>
                                            <TableCell className="text-xs font-semibold text-foreground">
                                                {slot.name}
                                            </TableCell>
                                            <TableCell className="text-xs whitespace-nowrap">
                                                {slot.start_time} -{' '}
                                                {slot.end_time}
                                            </TableCell>
                                            <TableCell className="text-xs">
                                                <div className="flex gap-1">
                                                    {DAYS.map((d) => {
                                                        const active =
                                                            slot.days_of_week.includes(
                                                                d.value,
                                                            );
                                                        return (
                                                            <span
                                                                key={d.value}
                                                                className={`rounded px-1 text-[10px] font-bold ${
                                                                    active
                                                                        ? 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300'
                                                                        : 'text-muted-foreground/30'
                                                                }`}
                                                            >
                                                                {d.label[0]}
                                                            </span>
                                                        );
                                                    })}
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-xs whitespace-nowrap">
                                                {slot.cutoff_hours}h before
                                            </TableCell>
                                            <TableCell className="text-xs">
                                                {slot.max_orders > 0
                                                    ? `${slot.max_orders} / day`
                                                    : 'Unlimited'}
                                            </TableCell>
                                            <TableCell className="text-xs">
                                                {slot.fee > 0
                                                    ? `$${slot.fee.toFixed(2)}`
                                                    : 'Free'}
                                            </TableCell>
                                            <TableCell className="text-xs font-semibold">
                                                {slot.total_bookings}
                                            </TableCell>
                                            <TableCell className="text-xs">
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    className="h-7 px-2 text-xs"
                                                    onClick={() =>
                                                        handleToggle(slot)
                                                    }
                                                >
                                                    <Badge
                                                        variant={
                                                            slot.is_active
                                                                ? 'default'
                                                                : 'secondary'
                                                        }
                                                        className={
                                                            slot.is_active
                                                                ? 'bg-emerald-600 hover:bg-emerald-700'
                                                                : 'bg-muted text-muted-foreground'
                                                        }
                                                    >
                                                        {slot.is_active
                                                            ? 'Active'
                                                            : 'Disabled'}
                                                    </Badge>
                                                </Button>
                                            </TableCell>
                                            <TableCell className="text-right text-xs">
                                                <div className="flex items-center justify-end gap-1">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-8"
                                                        onClick={() =>
                                                            openEditDialog(slot)
                                                        }
                                                    >
                                                        <Edit2 className="size-3.5 text-muted-foreground" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-8 text-destructive hover:text-destructive"
                                                        onClick={() =>
                                                            handleDelete(slot)
                                                        }
                                                    >
                                                        <Trash2 className="size-3.5" />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>

                {/* Add / Edit Dialog */}
                <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
                    <DialogContent className="max-w-md">
                        <DialogHeader>
                            <DialogTitle>
                                {editingSlot
                                    ? `Edit Slot: ${editingSlot.name}`
                                    : 'Create Delivery Slot'}
                            </DialogTitle>
                            <DialogDescription>
                                Set window times, operating days, and daily
                                capacity limits.
                            </DialogDescription>
                        </DialogHeader>

                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="space-y-1">
                                <Label htmlFor="slot-name" className="text-xs">
                                    Slot Name
                                </Label>
                                <Input
                                    id="slot-name"
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                    placeholder="e.g. Morning Delivery (9 AM - 1 PM)"
                                    className="text-xs"
                                    required
                                />
                                {errors.name && (
                                    <p className="text-xs text-destructive">
                                        {errors.name}
                                    </p>
                                )}
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div className="space-y-1">
                                    <Label
                                        htmlFor="start-time"
                                        className="text-xs"
                                    >
                                        Start Time
                                    </Label>
                                    <Input
                                        id="start-time"
                                        type="time"
                                        value={data.start_time}
                                        onChange={(e) =>
                                            setData(
                                                'start_time',
                                                e.target.value,
                                            )
                                        }
                                        className="text-xs"
                                        required
                                    />
                                    {errors.start_time && (
                                        <p className="text-xs text-destructive">
                                            {errors.start_time}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-1">
                                    <Label
                                        htmlFor="end-time"
                                        className="text-xs"
                                    >
                                        End Time
                                    </Label>
                                    <Input
                                        id="end-time"
                                        type="time"
                                        value={data.end_time}
                                        onChange={(e) =>
                                            setData('end_time', e.target.value)
                                        }
                                        className="text-xs"
                                        required
                                    />
                                    {errors.end_time && (
                                        <p className="text-xs text-destructive">
                                            {errors.end_time}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div className="space-y-1">
                                <Label className="text-xs">
                                    Operating Days of Week
                                </Label>
                                <div className="flex gap-1.5 pt-1">
                                    {DAYS.map((d) => {
                                        const isSelected =
                                            data.days_of_week.includes(d.value);
                                        return (
                                            <button
                                                key={d.value}
                                                type="button"
                                                onClick={() =>
                                                    toggleDay(d.value)
                                                }
                                                className={`flex size-9 cursor-pointer items-center justify-center rounded-md border text-xs font-semibold transition-all ${
                                                    isSelected
                                                        ? 'border-sky-600 bg-sky-600 text-white'
                                                        : 'border-border bg-card text-muted-foreground hover:bg-muted'
                                                }`}
                                            >
                                                {d.label[0]}
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>

                            <div className="grid grid-cols-3 gap-3">
                                <div className="space-y-1">
                                    <Label
                                        htmlFor="cutoff-hours"
                                        className="text-xs"
                                    >
                                        Cutoff (Hrs)
                                    </Label>
                                    <Input
                                        id="cutoff-hours"
                                        type="number"
                                        min={0}
                                        max={72}
                                        value={data.cutoff_hours}
                                        onChange={(e) =>
                                            setData(
                                                'cutoff_hours',
                                                parseInt(e.target.value) || 0,
                                            )
                                        }
                                        className="text-xs"
                                    />
                                </div>

                                <div className="space-y-1">
                                    <Label
                                        htmlFor="max-orders"
                                        className="text-xs"
                                    >
                                        Max Orders
                                    </Label>
                                    <Input
                                        id="max-orders"
                                        type="number"
                                        min={0}
                                        value={data.max_orders}
                                        onChange={(e) =>
                                            setData(
                                                'max_orders',
                                                parseInt(e.target.value) || 0,
                                            )
                                        }
                                        className="text-xs"
                                    />
                                </div>

                                <div className="space-y-1">
                                    <Label
                                        htmlFor="slot-fee"
                                        className="text-xs"
                                    >
                                        Fee ($)
                                    </Label>
                                    <Input
                                        id="slot-fee"
                                        type="number"
                                        step="0.5"
                                        min={0}
                                        value={data.fee}
                                        onChange={(e) =>
                                            setData(
                                                'fee',
                                                parseFloat(e.target.value) || 0,
                                            )
                                        }
                                        className="text-xs"
                                    />
                                </div>
                            </div>

                            <div className="flex items-center gap-2 pt-2">
                                <input
                                    id="slot-active"
                                    type="checkbox"
                                    checked={data.is_active}
                                    onChange={(e) =>
                                        setData('is_active', e.target.checked)
                                    }
                                    className="size-4 rounded border-input"
                                />
                                <Label
                                    htmlFor="slot-active"
                                    className="text-xs font-normal"
                                >
                                    Activate this slot immediately for customers
                                </Label>
                            </div>

                            <DialogFooter className="gap-2 pt-3">
                                <DialogClose asChild>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                    >
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    size="sm"
                                    disabled={processing}
                                >
                                    {processing
                                        ? 'Saving...'
                                        : editingSlot
                                          ? 'Update Slot'
                                          : 'Create Slot'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </AdminPage>
        </>
    );
}
