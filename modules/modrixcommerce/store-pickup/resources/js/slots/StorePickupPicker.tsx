import { router } from '@inertiajs/react';
import {
    Calendar,
    CheckCircle2,
    Clock,
    CreditCard,
    Info,
    Loader2,
    MapPin,
    Phone,
    Store,
    UserCheck,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Branch = {
    id: number;
    name: string;
    code: string;
    address_line1: string;
    address_line2: string | null;
    city: string;
    region: string | null;
    postal_code: string | null;
    country_code: string;
    full_address: string;
    phone: string | null;
    email: string | null;
    pickup_hours: string;
    instructions: string | null;
    prep_time: string;
    fee: number;
    is_active: boolean;
};

type Selection = {
    branch_id: number;
    branch_name: string;
    branch_address: string;
    branch_phone: string | null;
    pickup_hours: string;
    prep_time: string;
    collector_name: string | null;
    collector_phone: string | null;
    pickup_date: string | null;
    pickup_time: string | null;
    customer_notes: string | null;
    fee: number;
};

type Props = {
    enabled: boolean;
    shipping_label: string;
    allow_collector: boolean;
    allow_schedule: boolean;
    cod_behavior?: 'pay_in_store' | 'hide' | 'keep';
    pay_in_store_label?: string;
    pay_in_store_instructions?: string;
    branches: Branch[];
    selection: Selection | null;
    select_url: string;
    clear_url: string;
    shipping_method?: string;
};

export default function StorePickupPicker({
    enabled,
    shipping_label,
    allow_collector,
    allow_schedule,
    cod_behavior = 'pay_in_store',
    pay_in_store_label = 'Pay in store',
    pay_in_store_instructions = 'Pay in store (cash or card) when you collect your order.',
    branches,
    selection: initialSelection,
    select_url,
    clear_url,
    shipping_method,
}: Props) {
    const [selectedBranchId, setSelectedBranchId] = useState<number | null>(
        initialSelection?.branch_id ??
            (branches.length === 1 ? branches[0].id : null),
    );
    const [collectorEnabled, setCollectorEnabled] = useState(
        Boolean(initialSelection?.collector_name),
    );
    const [collectorName, setCollectorName] = useState(
        initialSelection?.collector_name || '',
    );
    const [collectorPhone, setCollectorPhone] = useState(
        initialSelection?.collector_phone || '',
    );
    const [pickupDate, setPickupDate] = useState(
        initialSelection?.pickup_date || '',
    );
    const [pickupTime, setPickupTime] = useState(
        initialSelection?.pickup_time || '',
    );
    const [notes] = useState(initialSelection?.customer_notes || '');

    const [isSaving, setIsSaving] = useState(false);
    const [saveSuccess, setSaveSuccess] = useState<string | null>(
        initialSelection ? 'Store pickup location confirmed' : null,
    );
    const [errorMessage, setErrorMessage] = useState<string | null>(null);

    // Store pickup is active ONLY when the shopper selected the store pickup shipping method
    const isPickupActive =
        shipping_method === 'store_pickup' ||
        Boolean(shipping_method?.startsWith('pickup_'));

    // When clicking "Switch to pickup", select the store_pickup shipping method
    const handleSwitchToPickup = () => {
        try {
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('shipping_method', 'store_pickup');
            window.history.replaceState({}, '', currentUrl.toString());
        } catch {
            // ignore
        }
        const radio = document.querySelector(
            'input[name="shipping_method"][value="store_pickup"]',
        ) as HTMLInputElement | null;
        if (radio) {
            radio.click();
        }
        router.reload({
            only: ['summary', 'paymentMethods'],
            data: {
                shipping_method: 'store_pickup',
            },
        });
    };

    // Make Cash on delivery the default payment method on load and ensure smooth switching
    useEffect(() => {
        const codRadio = document.querySelector<HTMLInputElement>(
            'input[name="payment_method"][value="cash_on_delivery"]',
        );
        if (!codRadio || codRadio.disabled) {
            return;
        }

        const urlParams = new URLSearchParams(window.location.search);
        const explicitPayment = urlParams.get('payment_method');

        // If no explicit payment choice was made by the user in URL, select COD/Pay in store by default
        if (!explicitPayment || explicitPayment === 'cash_on_delivery') {
            if (!codRadio.checked) {
                codRadio.click();
            }
        }
    }, [isPickupActive]);

    // Dynamically synchronize the Cash on Delivery / Pay in store payment option in checkout
    useEffect(() => {
        // Write only when the text differs: every DOM write counts as a change for the observer below.
        const setText = (el: HTMLElement, text: string) => {
            if (el.textContent !== text) {
                el.textContent = text;
            }
        };
        const setLabel = (span: HTMLElement, text: string) => {
            const radioInput = span.querySelector('input');
            const current = Array.from(span.childNodes)
                .filter((n) => n !== radioInput)
                .map((n) => n.textContent ?? '')
                .join('')
                .trim();
            if (current === text) {
                return;
            }
            span.innerHTML = '';
            if (radioInput) {
                span.appendChild(radioInput);
            }
            span.appendChild(document.createTextNode(` ${text}`));
        };

        const syncPaymentOption = () => {
            const codRadio = document.querySelector<HTMLInputElement>(
                'input[name="payment_method"][value="cash_on_delivery"]',
            );
            if (!codRadio) {
                return;
            }

            const card =
                codRadio.closest<HTMLElement>('.rounded-lg.border') ||
                codRadio.closest<HTMLElement>(
                    '[class*="rounded"][class*="border"]',
                ) ||
                codRadio.closest<HTMLElement>('div');
            if (!card) {
                return;
            }

            const labelSpan = card.querySelector<HTMLElement>('label > span');
            const instructionsSpan = card.querySelector<HTMLElement>(
                'label > span.text-muted-foreground, label > span.pl-5, label > p',
            );
            const feeP = card.querySelector<HTMLElement>(
                'p[role="status"], p.text-muted-foreground, p.pl-5',
            );

            // Remember default courier Cash on Delivery texts
            if (!card.dataset.origCodLabel && labelSpan) {
                const clone = labelSpan.cloneNode(true) as HTMLElement;
                const radioInClone = clone.querySelector('input');
                if (radioInClone) {
                    radioInClone.remove();
                }
                const text = clone.textContent?.trim();
                card.dataset.origCodLabel =
                    text && text !== pay_in_store_label
                        ? text
                        : 'Cash on delivery';
            }
            if (!card.dataset.origCodInstructions && instructionsSpan) {
                const text = instructionsSpan.textContent?.trim();
                card.dataset.origCodInstructions =
                    text && text !== pay_in_store_instructions
                        ? text
                        : 'Pay the courier in cash when your order arrives.';
            }

            if (isPickupActive) {
                // In-store pickup: adapt to "Pay in store" (or hide)
                if (cod_behavior === 'hide') {
                    card.style.display = 'none';
                    if (codRadio.checked) {
                        const fallbackRadio =
                            document.querySelector<HTMLInputElement>(
                                'input[name="payment_method"]:not([value="cash_on_delivery"]):not([disabled])',
                            );
                        if (fallbackRadio) {
                            fallbackRadio.click();
                        }
                    }
                } else if (cod_behavior === 'pay_in_store') {
                    card.style.display = '';
                    if (labelSpan) {
                        setLabel(
                            labelSpan,
                            pay_in_store_label || 'Pay in store',
                        );
                    }
                    if (instructionsSpan) {
                        setText(
                            instructionsSpan,
                            pay_in_store_instructions ||
                                'Pay in store (cash or card) when you collect your order.',
                        );
                    }
                    if (feeP) {
                        feeP.style.display = 'none';
                    }
                }
            } else {
                // Standard delivery: restore Cash on Delivery with courier fee
                card.style.display = '';
                if (labelSpan) {
                    setLabel(
                        labelSpan,
                        card.dataset.origCodLabel || 'Cash on delivery',
                    );
                }
                if (instructionsSpan) {
                    setText(
                        instructionsSpan,
                        card.dataset.origCodInstructions ||
                            'Pay the courier in cash when your order arrives.',
                    );
                }
                if (feeP) {
                    feeP.style.display = '';
                }
            }

            // Sync courier fee row in order totals summary
            const summaryDts = document.querySelectorAll<HTMLElement>('dl dt');
            summaryDts.forEach((dt) => {
                if (
                    dt.textContent
                        ?.toLowerCase()
                        .includes('cash on delivery fee')
                ) {
                    const row =
                        dt.closest<HTMLElement>('div.flex.justify-between') ||
                        dt.parentElement;
                    if (row) {
                        row.style.display = isPickupActive ? 'none' : '';
                    }
                }
            });
        };

        // Observe the checkout payment section in case Inertia re-renders payment methods.
        // The observer is paused while syncing, so its own writes can't re-trigger it
        // (that loop froze the checkout with "Page not responding").
        const paymentSection =
            document.querySelector(
                'section[aria-labelledby="payment-heading"]',
            ) || document.getElementById('payment-heading')?.closest('section');
        let observer: MutationObserver | null = null;
        const run = () => {
            observer?.disconnect();
            try {
                syncPaymentOption();
            } finally {
                if (observer && paymentSection) {
                    observer.observe(paymentSection, {
                        childList: true,
                        subtree: true,
                    });
                }
            }
        };
        if (paymentSection) {
            observer = new MutationObserver(run);
        }
        run();

        return () => {
            if (observer) {
                observer.disconnect();
            }
        };
    }, [
        isPickupActive,
        cod_behavior,
        pay_in_store_label,
        pay_in_store_instructions,
    ]);

    if (!enabled || branches.length === 0) {
        return null;
    }

    if (!isPickupActive) {
        return (
            <div className="my-4 flex items-center justify-between rounded-lg border border-dashed bg-muted/20 p-3 text-xs text-muted-foreground">
                <div className="flex items-center gap-2">
                    <Store className="h-4 w-4 text-muted-foreground" />
                    <span>Want to collect in store instead?</span>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={handleSwitchToPickup}
                    className="h-7 text-xs"
                >
                    Switch to pickup
                </Button>
            </div>
        );
    }

    const handleSelectBranch = async (branchId: number) => {
        setSelectedBranchId(branchId);
        // Ensure shipping method radio "store_pickup" is selected
        const radio = document.querySelector(
            'input[name="shipping_method"][value="store_pickup"]',
        ) as HTMLInputElement | null;
        if (radio && !radio.checked) {
            radio.click();
        }
        await submitSelection(
            branchId,
            collectorName,
            collectorPhone,
            pickupDate,
            pickupTime,
            notes,
        );
    };

    const submitSelection = async (
        branchId: number,
        name: string,
        phone: string,
        date: string,
        time: string,
        customerNotes: string,
    ) => {
        setIsSaving(true);
        setErrorMessage(null);

        try {
            const token =
                document
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute('content') || '';
            const res = await fetch(select_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    branch_id: branchId,
                    collector_name: collectorEnabled ? name : null,
                    collector_phone: collectorEnabled ? phone : null,
                    pickup_date: date || null,
                    pickup_time: time || null,
                    customer_notes: customerNotes || null,
                }),
            });

            const data = await res.json();
            if (res.ok && data.success) {
                setSaveSuccess(
                    `Pickup confirmed at ${data.branch?.name || 'selected branch'}`,
                );
                try {
                    const currentUrl = new URL(window.location.href);
                    currentUrl.searchParams.set(
                        'shipping_method',
                        'store_pickup',
                    );
                    window.history.replaceState({}, '', currentUrl.toString());
                } catch {
                    // ignore
                }
                router.reload({
                    only: ['summary', 'paymentMethods'],
                    data: {
                        shipping_method: 'store_pickup',
                    },
                });
            } else {
                setErrorMessage(
                    data.message || 'Failed to save store pickup selection.',
                );
            }
        } catch {
            setErrorMessage('Network error while saving pickup branch.');
        } finally {
            setIsSaving(false);
        }
    };

    const handleClear = async () => {
        setIsSaving(true);
        try {
            const token =
                document
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute('content') || '';
            await fetch(clear_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            setSelectedBranchId(null);
            setSaveSuccess(null);
            router.reload({ only: ['summary'] });
        } catch {
            // ignore
        } finally {
            setIsSaving(false);
        }
    };

    const selectedBranch = branches.find((b) => b.id === selectedBranchId);

    return (
        <Card className="my-4 border-primary/30 bg-primary/5">
            <CardHeader className="pb-3">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <Store className="h-5 w-5 text-primary" />
                        <CardTitle className="text-base font-semibold">
                            {shipping_label || 'In-Store & Branch Pickup'}
                        </CardTitle>
                    </div>
                    {selectedBranch && (
                        <div className="flex items-center gap-2">
                            <Badge
                                variant="outline"
                                className="border-emerald-500 bg-emerald-50 text-xs text-emerald-600"
                            >
                                Location Selected
                            </Badge>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={handleClear}
                                className="h-7 text-xs text-muted-foreground hover:text-foreground"
                            >
                                <X className="mr-1 h-3 w-3" /> Clear
                            </Button>
                        </div>
                    )}
                </div>
                <p className="text-xs text-muted-foreground">
                    Choose your nearest store or retail branch to collect your
                    order in person.
                </p>
            </CardHeader>

            <CardContent className="space-y-4 pt-0">
                {/* Branch selection cards */}
                <div className="grid gap-3 sm:grid-cols-2">
                    {branches.map((branch) => {
                        const isSelected = selectedBranchId === branch.id;
                        return (
                            <button
                                key={branch.id}
                                type="button"
                                onClick={() => handleSelectBranch(branch.id)}
                                disabled={isSaving}
                                className={`flex flex-col justify-between rounded-lg border p-3 text-left transition-all ${
                                    isSelected
                                        ? 'border-primary bg-background shadow-xs ring-2 ring-primary/20'
                                        : 'border-border/70 bg-background/60 hover:border-primary/50 hover:bg-background'
                                }`}
                            >
                                <div className="space-y-1">
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="flex items-center gap-1.5 text-sm font-medium text-foreground">
                                            <MapPin className="h-4 w-4 shrink-0 text-primary" />
                                            <span>{branch.name}</span>
                                        </div>
                                        <span className="text-xs font-semibold text-foreground tabular-nums">
                                            {Number(branch.fee) === 0
                                                ? 'Free'
                                                : `+${branch.fee}`}
                                        </span>
                                    </div>
                                    <p className="line-clamp-2 text-xs text-muted-foreground">
                                        {branch.full_address}
                                    </p>
                                </div>

                                <div className="mt-3 flex flex-wrap items-center gap-2 border-t border-border/40 pt-2 text-[11px] text-muted-foreground">
                                    <span className="flex items-center gap-1">
                                        <Clock className="h-3 w-3" />
                                        {branch.prep_time || 'Ready in 2 hours'}
                                    </span>
                                    {branch.phone && (
                                        <span className="flex items-center gap-1">
                                            <Phone className="h-3 w-3" />
                                            {branch.phone}
                                        </span>
                                    )}
                                </div>
                            </button>
                        );
                    })}
                </div>

                {/* Selected branch details & schedule */}
                {selectedBranch && (
                    <div className="space-y-3 rounded-md border border-border/60 bg-background p-3 text-xs">
                        <div className="flex items-start justify-between">
                            <div>
                                <span className="font-semibold text-foreground">
                                    Selected:{' '}
                                </span>
                                <span className="font-medium text-primary">
                                    {selectedBranch.name}
                                </span>
                                <span className="text-muted-foreground">
                                    {' '}
                                    ({selectedBranch.city})
                                </span>
                            </div>
                            <span className="text-muted-foreground">
                                {selectedBranch.pickup_hours}
                            </span>
                        </div>

                        {selectedBranch.instructions && (
                            <div className="flex items-start gap-1.5 rounded bg-muted/40 p-2 text-muted-foreground">
                                <Info className="mt-0.5 h-3.5 w-3.5 shrink-0 text-primary" />
                                <span>{selectedBranch.instructions}</span>
                            </div>
                        )}

                        {/* Payment callout banner */}
                        {cod_behavior === 'pay_in_store' && (
                            <div className="flex items-center justify-between rounded-md border border-emerald-200 bg-emerald-50/70 p-2.5 text-xs text-emerald-900 dark:border-emerald-900/50 dark:bg-emerald-950/20 dark:text-emerald-300">
                                <div className="flex items-center gap-2">
                                    <CreditCard className="h-4 w-4 shrink-0 text-emerald-600" />
                                    <span>
                                        <strong>{pay_in_store_label}:</strong>{' '}
                                        Courier delivery fee is waived. You can
                                        pay by card or cash at the counter.
                                    </span>
                                </div>
                            </div>
                        )}

                        {/* Alternative Collector */}
                        {allow_collector && (
                            <div className="space-y-2 border-t border-border/40 pt-2">
                                <label className="flex cursor-pointer items-center gap-2 select-none">
                                    <input
                                        type="checkbox"
                                        checked={collectorEnabled}
                                        onChange={(e) =>
                                            setCollectorEnabled(
                                                e.target.checked,
                                            )
                                        }
                                        className="rounded border-border"
                                    />
                                    <span className="flex items-center gap-1.5 font-medium text-foreground">
                                        <UserCheck className="h-3.5 w-3.5 text-primary" />
                                        Someone else will collect this order
                                        (Authorized Person)
                                    </span>
                                </label>

                                {collectorEnabled && (
                                    <div className="grid gap-2 pt-1 pl-5 sm:grid-cols-2">
                                        <div>
                                            <Label
                                                htmlFor="collector_name"
                                                className="text-[11px]"
                                            >
                                                Collector Full Name
                                            </Label>
                                            <Input
                                                id="collector_name"
                                                size={1}
                                                value={collectorName}
                                                onChange={(e) =>
                                                    setCollectorName(
                                                        e.target.value,
                                                    )
                                                }
                                                onBlur={() =>
                                                    selectedBranchId &&
                                                    submitSelection(
                                                        selectedBranchId,
                                                        collectorName,
                                                        collectorPhone,
                                                        pickupDate,
                                                        pickupTime,
                                                        notes,
                                                    )
                                                }
                                                placeholder="e.g. John Doe"
                                                className="mt-1 h-8 text-xs"
                                            />
                                        </div>
                                        <div>
                                            <Label
                                                htmlFor="collector_phone"
                                                className="text-[11px]"
                                            >
                                                Collector Phone
                                            </Label>
                                            <Input
                                                id="collector_phone"
                                                size={1}
                                                value={collectorPhone}
                                                onChange={(e) =>
                                                    setCollectorPhone(
                                                        e.target.value,
                                                    )
                                                }
                                                onBlur={() =>
                                                    selectedBranchId &&
                                                    submitSelection(
                                                        selectedBranchId,
                                                        collectorName,
                                                        collectorPhone,
                                                        pickupDate,
                                                        pickupTime,
                                                        notes,
                                                    )
                                                }
                                                placeholder="+971 50 000 0000"
                                                className="mt-1 h-8 text-xs"
                                            />
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}

                        {/* Preferred pickup schedule */}
                        {allow_schedule && (
                            <div className="grid gap-2 border-t border-border/40 pt-2 sm:grid-cols-2">
                                <div>
                                    <Label
                                        htmlFor="pickup_date"
                                        className="flex items-center gap-1 text-[11px]"
                                    >
                                        <Calendar className="h-3 w-3 text-primary" />
                                        Preferred Pickup Date
                                    </Label>
                                    <Input
                                        id="pickup_date"
                                        type="date"
                                        value={pickupDate}
                                        min={
                                            new Date()
                                                .toISOString()
                                                .split('T')[0]
                                        }
                                        onChange={(e) => {
                                            setPickupDate(e.target.value);
                                            if (selectedBranchId) {
                                                void submitSelection(
                                                    selectedBranchId,
                                                    collectorName,
                                                    collectorPhone,
                                                    e.target.value,
                                                    pickupTime,
                                                    notes,
                                                );
                                            }
                                        }}
                                        className="mt-1 h-8 text-xs"
                                    />
                                </div>
                                <div>
                                    <Label
                                        htmlFor="pickup_time"
                                        className="flex items-center gap-1 text-[11px]"
                                    >
                                        <Clock className="h-3 w-3 text-primary" />
                                        Preferred Time Window
                                    </Label>
                                    <select
                                        id="pickup_time"
                                        value={pickupTime}
                                        onChange={(e) => {
                                            setPickupTime(e.target.value);
                                            if (selectedBranchId) {
                                                void submitSelection(
                                                    selectedBranchId,
                                                    collectorName,
                                                    collectorPhone,
                                                    pickupDate,
                                                    e.target.value,
                                                    notes,
                                                );
                                            }
                                        }}
                                        className="mt-1 h-8 w-full rounded-md border border-input bg-transparent px-3 py-1 text-xs shadow-xs"
                                    >
                                        <option value="">
                                            Any time during operating hours
                                        </option>
                                        <option value="morning">
                                            Morning (Opening - 12:00)
                                        </option>
                                        <option value="afternoon">
                                            Afternoon (12:00 - 17:00)
                                        </option>
                                        <option value="evening">
                                            Evening (17:00 - Closing)
                                        </option>
                                    </select>
                                </div>
                            </div>
                        )}

                        {saveSuccess && (
                            <div className="flex items-center gap-1.5 pt-1 text-[11px] font-medium text-emerald-600">
                                <CheckCircle2 className="h-3.5 w-3.5" />
                                <span>{saveSuccess}</span>
                            </div>
                        )}

                        {errorMessage && (
                            <div className="flex items-center gap-1.5 pt-1 text-[11px] font-medium text-destructive">
                                <span>{errorMessage}</span>
                            </div>
                        )}

                        {isSaving && (
                            <div className="flex items-center gap-1.5 pt-1 text-[11px] text-muted-foreground">
                                <Loader2 className="h-3 w-3 animate-spin" />
                                <span>Saving pickup preferences...</span>
                            </div>
                        )}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
