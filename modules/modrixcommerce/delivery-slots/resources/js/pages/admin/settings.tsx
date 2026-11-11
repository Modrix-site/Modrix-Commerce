import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, CalendarOff, Plus, Save, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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

type BlackoutItem = {
    id: number;
    date: string;
    formatted: string;
    reason: string | null;
};

type MethodRuleItem = {
    code: string;
    name: string;
    subtitle: string;
    enabled: boolean;
    max_days: number;
    show_time_slots: boolean;
};

type Props = {
    settings: {
        enabled: boolean;
        is_required: boolean;
        max_advance_days: number;
        same_day_cutoff: string;
        heading: string;
        method_rules?: Record<string, MethodRuleItem>;
    };
    blackoutDates: BlackoutItem[];
    urls: {
        index: string;
        slots: string;
        settings: string;
        updateSettings: string;
        storeBlackout: string;
    };
};

export default function DeliverySlotsAdminSettings({
    settings,
    blackoutDates,
    urls,
}: Props) {
    const { data, setData, put, processing, errors } = useForm({
        enabled: settings.enabled,
        is_required: settings.is_required,
        max_advance_days: settings.max_advance_days,
        same_day_cutoff: settings.same_day_cutoff,
        heading: settings.heading,
        method_rules: settings.method_rules ?? {},
    });

    const updateMethodRule = (code: string, patch: Partial<MethodRuleItem>) => {
        const current = data.method_rules?.[code];
        if (!current) return;
        setData('method_rules', {
            ...data.method_rules,
            [code]: {
                ...current,
                ...patch,
            },
        });
    };

    // Form for new blackout date
    const [blackoutDate, setBlackoutDate] = useState('');
    const [blackoutReason, setBlackoutReason] = useState('');
    const [isAddingBlackout, setIsAddingBlackout] = useState(false);

    const handleSaveSettings = (e: React.FormEvent) => {
        e.preventDefault();
        put(urls.updateSettings);
    };

    const handleAddBlackout = (e: React.FormEvent) => {
        e.preventDefault();
        if (!blackoutDate) return;

        setIsAddingBlackout(true);
        router.post(
            urls.storeBlackout,
            {
                date: blackoutDate,
                reason: blackoutReason,
            },
            {
                onSuccess: () => {
                    setBlackoutDate('');
                    setBlackoutReason('');
                },
                onFinish: () => setIsAddingBlackout(false),
            },
        );
    };

    const handleDeleteBlackout = (item: BlackoutItem) => {
        if (confirm(`Remove blackout for ${item.date}?`)) {
            router.delete(
                `/admin/m/modrixcommerce/delivery-slots/blackouts/${item.id}`,
            );
        }
    };

    return (
        <>
            <Head title="Delivery Slots Settings & Blackouts" />

            <AdminPage>
                <PageHeader
                    title="Delivery Slot Settings"
                    description="Configure checkout booking options, cutoff times, and holiday blackout dates."
                    actions={
                        <Link href={urls.index}>
                            <Button variant="outline" size="sm">
                                <ArrowLeft className="mr-1.5 size-4" />
                                Back to Schedule
                            </Button>
                        </Link>
                    }
                />

                <div className="grid gap-6 md:grid-cols-2">
                    {/* General Settings */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base font-semibold">
                                General Options
                            </CardTitle>
                            <CardDescription>
                                Control how delivery slot selection behaves
                                during checkout.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form
                                onSubmit={handleSaveSettings}
                                className="space-y-4"
                            >
                                <div className="flex items-center gap-2">
                                    <input
                                        id="enabled"
                                        type="checkbox"
                                        checked={data.enabled}
                                        onChange={(e) =>
                                            setData('enabled', e.target.checked)
                                        }
                                        className="size-4 rounded border-input"
                                    />
                                    <Label
                                        htmlFor="enabled"
                                        className="text-xs font-semibold"
                                    >
                                        Enable delivery date & slot selection at
                                        checkout
                                    </Label>
                                </div>

                                <div className="flex items-center gap-2">
                                    <input
                                        id="is_required"
                                        type="checkbox"
                                        checked={data.is_required}
                                        onChange={(e) =>
                                            setData(
                                                'is_required',
                                                e.target.checked,
                                            )
                                        }
                                        className="size-4 rounded border-input"
                                    />
                                    <Label
                                        htmlFor="is_required"
                                        className="text-xs font-semibold"
                                    >
                                        Require customers to pick a slot before
                                        placing order
                                    </Label>
                                </div>

                                <div className="space-y-1">
                                    <Label
                                        htmlFor="heading"
                                        className="text-xs"
                                    >
                                        Checkout Section Heading
                                    </Label>
                                    <Input
                                        id="heading"
                                        value={data.heading}
                                        onChange={(e) =>
                                            setData('heading', e.target.value)
                                        }
                                        className="text-xs"
                                        required
                                    />
                                    {errors.heading && (
                                        <p className="text-xs text-destructive">
                                            {errors.heading}
                                        </p>
                                    )}
                                </div>

                                <div className="grid grid-cols-2 gap-3">
                                    <div className="space-y-1">
                                        <Label
                                            htmlFor="max_advance_days"
                                            className="text-xs"
                                        >
                                            Max Advance Booking (Days)
                                        </Label>
                                        <Input
                                            id="max_advance_days"
                                            type="number"
                                            min={1}
                                            max={30}
                                            value={data.max_advance_days}
                                            onChange={(e) =>
                                                setData(
                                                    'max_advance_days',
                                                    parseInt(e.target.value) ||
                                                        7,
                                                )
                                            }
                                            className="text-xs"
                                            required
                                        />
                                        {errors.max_advance_days && (
                                            <p className="text-xs text-destructive">
                                                {errors.max_advance_days}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-1">
                                        <Label
                                            htmlFor="same_day_cutoff"
                                            className="text-xs"
                                        >
                                            Same-day Cutoff (24h)
                                        </Label>
                                        <Input
                                            id="same_day_cutoff"
                                            type="time"
                                            value={data.same_day_cutoff}
                                            onChange={(e) =>
                                                setData(
                                                    'same_day_cutoff',
                                                    e.target.value,
                                                )
                                            }
                                            className="text-xs"
                                            required
                                        />
                                        {errors.same_day_cutoff && (
                                            <p className="text-xs text-destructive">
                                                {errors.same_day_cutoff}
                                            </p>
                                        )}
                                    </div>
                                </div>

                                {Object.values(data.method_rules ?? {}).length >
                                    0 && (
                                    <div className="space-y-3 rounded-lg border bg-muted/20 p-3.5">
                                        <div>
                                            <h4 className="text-xs font-semibold">
                                                Per-Delivery Option Slot Rules
                                            </h4>
                                            <p className="text-[11px] text-muted-foreground">
                                                Customize how many delivery days
                                                (1 day vs 1–2 days) and whether
                                                time slots are displayed for
                                                Instant vs Standard delivery.
                                            </p>
                                        </div>

                                        <div className="space-y-2.5">
                                            {Object.values(
                                                data.method_rules ?? {},
                                            ).map((rule) => (
                                                <div
                                                    key={rule.code}
                                                    className="space-y-2.5 rounded-md border bg-background p-3"
                                                >
                                                    <div className="flex items-center justify-between gap-2">
                                                        <div>
                                                            <div className="text-xs font-semibold">
                                                                {rule.name}
                                                            </div>
                                                            {rule.subtitle && (
                                                                <div className="text-[11px] text-muted-foreground">
                                                                    {
                                                                        rule.subtitle
                                                                    }
                                                                </div>
                                                            )}
                                                        </div>
                                                        <label className="flex cursor-pointer items-center gap-1.5 text-xs font-medium">
                                                            <input
                                                                type="checkbox"
                                                                checked={
                                                                    rule.enabled
                                                                }
                                                                onChange={(e) =>
                                                                    updateMethodRule(
                                                                        rule.code,
                                                                        {
                                                                            enabled:
                                                                                e
                                                                                    .target
                                                                                    .checked,
                                                                        },
                                                                    )
                                                                }
                                                                className="size-3.5 rounded border-input"
                                                            />
                                                            Active
                                                        </label>
                                                    </div>

                                                    {rule.enabled && (
                                                        <div className="grid grid-cols-1 gap-2.5 border-t pt-1 sm:grid-cols-2">
                                                            <div className="space-y-1">
                                                                <Label className="text-[11px]">
                                                                    Allowed
                                                                    Delivery
                                                                    Window
                                                                    (Days)
                                                                </Label>
                                                                <div className="flex items-center gap-1.5">
                                                                    <Input
                                                                        type="number"
                                                                        min={1}
                                                                        max={30}
                                                                        value={
                                                                            rule.max_days
                                                                        }
                                                                        onChange={(
                                                                            e,
                                                                        ) =>
                                                                            updateMethodRule(
                                                                                rule.code,
                                                                                {
                                                                                    max_days:
                                                                                        Math.max(
                                                                                            1,
                                                                                            Math.min(
                                                                                                30,
                                                                                                parseInt(
                                                                                                    e
                                                                                                        .target
                                                                                                        .value,
                                                                                                ) ||
                                                                                                    1,
                                                                                            ),
                                                                                        ),
                                                                                },
                                                                            )
                                                                        }
                                                                        className="h-8 w-20 text-xs"
                                                                    />
                                                                    <Button
                                                                        type="button"
                                                                        variant={
                                                                            rule.max_days ===
                                                                            1
                                                                                ? 'default'
                                                                                : 'outline'
                                                                        }
                                                                        size="sm"
                                                                        className="h-7 px-2 text-[11px]"
                                                                        onClick={() =>
                                                                            updateMethodRule(
                                                                                rule.code,
                                                                                {
                                                                                    max_days: 1,
                                                                                    show_time_slots: false,
                                                                                },
                                                                            )
                                                                        }
                                                                    >
                                                                        1 Day
                                                                        (Instant)
                                                                    </Button>
                                                                    <Button
                                                                        type="button"
                                                                        variant={
                                                                            rule.max_days ===
                                                                            2
                                                                                ? 'default'
                                                                                : 'outline'
                                                                        }
                                                                        size="sm"
                                                                        className="h-7 px-2 text-[11px]"
                                                                        onClick={() =>
                                                                            updateMethodRule(
                                                                                rule.code,
                                                                                {
                                                                                    max_days: 2,
                                                                                    show_time_slots: true,
                                                                                },
                                                                            )
                                                                        }
                                                                    >
                                                                        1–2 Days
                                                                    </Button>
                                                                </div>
                                                            </div>

                                                            <div className="flex items-end pb-1">
                                                                <label className="flex cursor-pointer items-center gap-2 text-xs">
                                                                    <input
                                                                        type="checkbox"
                                                                        checked={
                                                                            rule.show_time_slots
                                                                        }
                                                                        onChange={(
                                                                            e,
                                                                        ) =>
                                                                            updateMethodRule(
                                                                                rule.code,
                                                                                {
                                                                                    show_time_slots:
                                                                                        e
                                                                                            .target
                                                                                            .checked,
                                                                                },
                                                                            )
                                                                        }
                                                                        className="size-3.5 rounded border-input"
                                                                    />
                                                                    <span>
                                                                        Display
                                                                        time
                                                                        slots
                                                                        picker
                                                                    </span>
                                                                </label>
                                                            </div>
                                                        </div>
                                                    )}
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                <Button
                                    type="submit"
                                    size="sm"
                                    disabled={processing}
                                    className="mt-2"
                                >
                                    <Save className="mr-1.5 size-3.5" />
                                    {processing ? 'Saving...' : 'Save Settings'}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    {/* Blackout Dates */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base font-semibold">
                                Blackout Dates & Holidays
                            </CardTitle>
                            <CardDescription>
                                Block specific calendar dates from being
                                selected for delivery.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {/* Add Blackout Form */}
                            <form
                                onSubmit={handleAddBlackout}
                                className="flex flex-wrap items-end gap-2"
                            >
                                <div className="space-y-1">
                                    <Label
                                        htmlFor="blackout-date"
                                        className="text-xs"
                                    >
                                        Date
                                    </Label>
                                    <Input
                                        id="blackout-date"
                                        type="date"
                                        value={blackoutDate}
                                        onChange={(e) =>
                                            setBlackoutDate(e.target.value)
                                        }
                                        className="h-9 w-36 text-xs"
                                        required
                                    />
                                </div>

                                <div className="min-w-[130px] flex-1 space-y-1">
                                    <Label
                                        htmlFor="blackout-reason"
                                        className="text-xs"
                                    >
                                        Reason
                                    </Label>
                                    <Input
                                        id="blackout-reason"
                                        placeholder="e.g. National Holiday"
                                        value={blackoutReason}
                                        onChange={(e) =>
                                            setBlackoutReason(e.target.value)
                                        }
                                        className="h-9 text-xs"
                                    />
                                </div>

                                <Button
                                    type="submit"
                                    size="sm"
                                    className="h-9"
                                    disabled={isAddingBlackout || !blackoutDate}
                                >
                                    <Plus className="mr-1.5 size-3.5" />
                                    Add
                                </Button>
                            </form>

                            {/* Blackout Dates List */}
                            <div className="rounded-md border">
                                {blackoutDates.length === 0 ? (
                                    <div className="flex flex-col items-center justify-center p-6 text-center">
                                        <CalendarOff className="size-6 text-muted-foreground/50" />
                                        <p className="mt-2 text-xs text-muted-foreground">
                                            No blackout dates scheduled.
                                        </p>
                                    </div>
                                ) : (
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Date</TableHead>
                                                <TableHead>Reason</TableHead>
                                                <TableHead className="w-12"></TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {blackoutDates.map((item) => (
                                                <TableRow key={item.id}>
                                                    <TableCell className="text-xs font-medium whitespace-nowrap">
                                                        {item.formatted}
                                                    </TableCell>
                                                    <TableCell className="text-xs text-muted-foreground">
                                                        {item.reason ||
                                                            'Store Closed'}
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="size-7 text-destructive hover:text-destructive"
                                                            onClick={() =>
                                                                handleDeleteBlackout(
                                                                    item,
                                                                )
                                                            }
                                                        >
                                                            <Trash2 className="size-3.5" />
                                                        </Button>
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </AdminPage>
        </>
    );
}
