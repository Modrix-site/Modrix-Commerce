import { Calendar, CheckCircle2, Clock, FileText, Loader2, Truck } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

type DateOption = {
    date: string;
    day_name: string;
    formatted: string;
    is_today: boolean;
    is_tomorrow: boolean;
    is_available: boolean;
    available_slots_count: number;
    reason: string | null;
};

type SlotOption = {
    id: number;
    name: string;
    start_time: string;
    end_time: string;
    formatted_time: string;
    fee: number;
    is_available: boolean;
    remaining_orders: number | null;
    reason: string | null;
};

type Props = {
    heading: string;
    is_required: boolean;
    dates: DateOption[];
    initial_slots: SlotOption[];
    initial_date: string | null;
    selection: {
        date: string;
        delivery_slot_id: number;
        slot_name: string;
        instructions: string | null;
    } | null;
    select_url: string;
    slots_url: string;
    clear_url: string;
};

export default function DeliverySlotPicker({
    heading,
    is_required,
    dates,
    initial_slots,
    initial_date,
    selection,
    select_url,
    slots_url,
}: Props) {
    const [selectedDate, setSelectedDate] = useState<string | null>(
        selection?.date ?? initial_date ?? dates.find((d) => d.is_available)?.date ?? null
    );
    const [slots, setSlots] = useState<SlotOption[]>(initial_slots || []);
    const [selectedSlotId, setSelectedSlotId] = useState<number | null>(
        selection?.delivery_slot_id ?? null
    );
    const [instructions, setInstructions] = useState<string>(
        selection?.instructions ?? ''
    );
    const [isLoadingSlots, setIsLoadingSlots] = useState(false);
    const [isSaving, setIsSaving] = useState(false);
    const [saveStatus, setSaveStatus] = useState<string | null>(
        selection?.delivery_slot_id ? 'Delivery schedule selected' : null
    );
    const [errorMessage, setErrorMessage] = useState<string | null>(null);

    // Fetch slots when user switches dates
    const handleSelectDate = async (dateStr: string) => {
        if (dateStr === selectedDate && slots.length > 0) return;

        setSelectedDate(dateStr);
        setSelectedSlotId(null);
        setErrorMessage(null);
        setIsLoadingSlots(true);

        try {
            const res = await fetch(`${slots_url}?date=${encodeURIComponent(dateStr)}`, {
                headers: { credentials: 'same-origin', Accept: 'application/json' },
            });
            const data = await res.json();
            setSlots(data.slots || []);
        } catch {
            setErrorMessage('Failed to load slots for this date.');
        } finally {
            setIsLoadingSlots(false);
        }
    };

    // Save selection to session
    const saveSelection = async (slotId: number, currentInstructions: string) => {
        if (!selectedDate) return;

        setIsSaving(true);
        setErrorMessage(null);

        const csrfToken =
            (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ||
            '';

        try {
            const res = await fetch(select_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    date: selectedDate,
                    delivery_slot_id: slotId,
                    delivery_instructions: currentInstructions,
                }),
            });

            const data = await res.json();
            if (res.ok && data.success) {
                setSelectedSlotId(slotId);
                setSaveStatus('Delivery schedule confirmed for this order');
            } else {
                setErrorMessage(data.message || 'Could not select this slot.');
            }
        } catch {
            setErrorMessage('Network error while saving delivery slot.');
        } finally {
            setIsSaving(false);
        }
    };

    const handleSlotClick = (slot: SlotOption) => {
        if (!slot.is_available) return;
        setSelectedSlotId(slot.id);
        void saveSelection(slot.id, instructions);
    };

    const handleInstructionsBlur = () => {
        if (selectedSlotId) {
            void saveSelection(selectedSlotId, instructions);
        }
    };

    if (!dates || dates.length === 0) {
        return null;
    }

    return (
        <Card className="my-6 border-sky-500/20 bg-gradient-to-b from-sky-500/[0.02] to-transparent shadow-xs">
            <CardContent className="p-4 sm:p-6">
                {/* Header */}
                <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border/60 pb-3">
                    <div className="flex items-center gap-2">
                        <Truck className="size-5 text-sky-600 dark:text-sky-400" />
                        <h3 className="text-base font-semibold text-foreground">{heading}</h3>
                        {is_required ? (
                            <Badge variant="destructive" className="text-[10px] uppercase font-bold">
                                Required
                            </Badge>
                        ) : (
                            <span className="text-xs text-muted-foreground">(Optional)</span>
                        )}
                    </div>

                    {isSaving ? (
                        <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
                            <Loader2 className="size-3.5 animate-spin" />
                            <span>Saving...</span>
                        </div>
                    ) : saveStatus && selectedSlotId ? (
                        <div className="flex items-center gap-1.5 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                            <CheckCircle2 className="size-3.5" />
                            <span>{saveStatus}</span>
                        </div>
                    ) : null}
                </div>

                {errorMessage && (
                    <div className="mt-3 rounded-md bg-destructive/10 p-3 text-xs text-destructive">
                        {errorMessage}
                    </div>
                )}

                {/* 1. Date Chips */}
                <div className="mt-4">
                    <Label className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                        1. Choose Delivery Date
                    </Label>
                    <div className="mt-2 flex gap-2 overflow-x-auto pb-2 scrollbar-thin">
                        {dates.map((d) => {
                            const isSelected = selectedDate === d.date;
                            return (
                                <button
                                    key={d.date}
                                    type="button"
                                    disabled={!d.is_available}
                                    onClick={() => handleSelectDate(d.date)}
                                    className={cn(
                                        'flex min-w-[76px] flex-col items-center justify-center rounded-xl border p-2.5 text-center transition-all cursor-pointer',
                                        isSelected
                                            ? 'border-sky-600 bg-sky-500/10 text-sky-950 ring-2 ring-sky-500/30 dark:bg-sky-950/40 dark:text-sky-100'
                                            : d.is_available
                                              ? 'border-border bg-card hover:border-sky-500/40 hover:bg-muted/40 text-foreground'
                                              : 'cursor-not-allowed border-dashed border-border/50 bg-muted/20 opacity-40 text-muted-foreground'
                                    )}
                                >
                                    <span className="text-[11px] font-medium uppercase text-muted-foreground">
                                        {d.is_today ? 'Today' : d.is_tomorrow ? 'Tmrw' : d.day_name}
                                    </span>
                                    <span className="mt-0.5 text-sm font-bold">{d.formatted}</span>
                                    <span className="mt-1 text-[9px] text-muted-foreground">
                                        {d.is_available
                                            ? `${d.available_slots_count} slot${d.available_slots_count === 1 ? '' : 's'}`
                                            : 'Unavailable'}
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                </div>

                {/* 2. Slot Selection */}
                <div className="mt-5">
                    <Label className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                        2. Choose Time Slot
                    </Label>

                    {isLoadingSlots ? (
                        <div className="flex h-24 items-center justify-center gap-2 text-xs text-muted-foreground">
                            <Loader2 className="size-4 animate-spin text-sky-600" />
                            <span>Loading available time slots...</span>
                        </div>
                    ) : slots.length === 0 ? (
                        <div className="mt-2 rounded-lg border border-dashed p-4 text-center text-xs text-muted-foreground">
                            No time slots available for this date. Please choose another date.
                        </div>
                    ) : (
                        <div className="mt-2 grid grid-cols-1 gap-2.5 sm:grid-cols-2 md:grid-cols-3">
                            {slots.map((slot) => {
                                const isSelected = selectedSlotId === slot.id;
                                return (
                                    <button
                                        key={slot.id}
                                        type="button"
                                        disabled={!slot.is_available}
                                        onClick={() => handleSlotClick(slot)}
                                        className={cn(
                                            'relative flex flex-col justify-between rounded-lg border p-3 text-left transition-all cursor-pointer',
                                            isSelected
                                                ? 'border-sky-600 bg-sky-50 dark:bg-sky-950/30 ring-1 ring-sky-600'
                                                : slot.is_available
                                                  ? 'border-border bg-card hover:border-sky-500/50 hover:bg-muted/30'
                                                  : 'cursor-not-allowed border-dashed bg-muted/30 opacity-50'
                                        )}
                                    >
                                        <div className="flex items-start justify-between gap-1">
                                            <div className="font-semibold text-xs text-foreground">
                                                {slot.name}
                                            </div>
                                            {slot.fee > 0 ? (
                                                <Badge variant="secondary" className="text-[10px]">
                                                    +${slot.fee.toFixed(2)}
                                                </Badge>
                                            ) : (
                                                <Badge
                                                    variant="secondary"
                                                    className="bg-emerald-100 text-[10px] text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300"
                                                >
                                                    Free
                                                </Badge>
                                            )}
                                        </div>

                                        <div className="mt-2 flex items-center gap-1.5 text-xs text-muted-foreground">
                                            <Clock className="size-3 text-sky-600" />
                                            <span>{slot.formatted_time}</span>
                                        </div>

                                        <div className="mt-2 flex items-center justify-between text-[10px]">
                                            {slot.is_available ? (
                                                slot.remaining_orders !== null ? (
                                                    <span
                                                        className={cn(
                                                            slot.remaining_orders <= 3
                                                                ? 'font-medium text-amber-600 dark:text-amber-400'
                                                                : 'text-muted-foreground'
                                                        )}
                                                    >
                                                        {slot.remaining_orders} slots left
                                                    </span>
                                                ) : (
                                                    <span className="text-emerald-600 dark:text-emerald-400 font-medium">
                                                        Available
                                                    </span>
                                                )
                                            ) : (
                                                <span className="text-destructive font-medium">
                                                    {slot.reason || 'Unavailable'}
                                                </span>
                                            )}

                                            {isSelected && (
                                                <span className="text-sky-600 font-bold">Selected</span>
                                            )}
                                        </div>
                                    </button>
                                );
                            })}
                        </div>
                    )}
                </div>

                {/* 3. Delivery Instructions */}
                <div className="mt-5 border-t border-border/50 pt-4">
                    <div className="flex items-center gap-1.5">
                        <FileText className="size-3.5 text-muted-foreground" />
                        <Label
                            htmlFor="delivery-instructions"
                            className="text-xs font-semibold uppercase tracking-wider text-muted-foreground"
                        >
                            3. Special Delivery Instructions (Optional)
                        </Label>
                    </div>
                    <Textarea
                        id="delivery-instructions"
                        rows={2}
                        maxLength={500}
                        placeholder="e.g. Leave package by apartment door, buzz #402, ring the bell on delivery..."
                        value={instructions}
                        onChange={(e) => setInstructions(e.target.value)}
                        onBlur={handleInstructionsBlur}
                        className="mt-2 text-xs"
                    />
                    <p className="mt-1 text-[11px] text-muted-foreground">
                        Your driver will see these notes when delivering your order.
                    </p>
                </div>
            </CardContent>
        </Card>
    );
}
