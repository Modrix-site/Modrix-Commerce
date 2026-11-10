import { router } from '@inertiajs/react';
import { Check, ShieldCheck, Sparkles, Wrench } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { MoneyValue } from '@/types';

interface AddonItem {
    id: number;
    name: string;
    type: 'warranty' | 'feature' | 'service' | string;
    badge: string | null;
    description: string | null;
    price: MoneyValue;
    selected: boolean;
}

interface Props {
    productId: number;
    heading: string;
    subtext: string;
    toggleUrl: string;
    basePrice?: MoneyValue | null;
    addonsTotal?: MoneyValue | null;
    totalPrice?: MoneyValue | null;
    addons: AddonItem[];
}

const typeMeta: Record<string, { label: string; icon: typeof ShieldCheck }> = {
    warranty: { label: 'Warranty', icon: ShieldCheck },
    feature: { label: 'Extra Feature', icon: Sparkles },
    service: { label: 'Service', icon: Wrench },
};

function formatCurrency(amount: number, sample?: MoneyValue | null): string {
    const currency = sample?.display_currency ?? sample?.currency ?? 'USD';
    try {
        return new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency,
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(amount);
    } catch {
        return `${currency} ${amount.toFixed(2)}`;
    }
}

export default function ProductAddons({
    productId,
    heading,
    subtext,
    toggleUrl,
    basePrice,
    addons,
}: Props) {
    const [pendingId, setPendingId] = useState<number | null>(null);
    const [selectedMap, setSelectedMap] = useState<Record<number, boolean>>(() =>
        Object.fromEntries((addons ?? []).map((a) => [a.id, a.selected])),
    );

    useEffect(() => {
        setSelectedMap(Object.fromEntries((addons ?? []).map((a) => [a.id, a.selected])));
    }, [addons]);

    if (!addons || addons.length === 0) return null;

    function toggleAddon(addonId: number) {
        const nextSelected = !selectedMap[addonId];
        const nextMap = { ...selectedMap, [addonId]: nextSelected };
        setSelectedMap(nextMap);
        setPendingId(addonId);

        const nextAddonSum = addons.reduce((sum, item) => {
            if (!nextMap[item.id]) return sum;
            return sum + Number(item.price.display_amount ?? item.price.amount ?? 0);
        }, 0);

        window.dispatchEvent(
            new CustomEvent('product-addons:optimistic', {
                detail: {
                    productId,
                    addonTotal: nextAddonSum,
                },
            }),
        );

        router.post(
            toggleUrl,
            { product_id: productId, addon_id: addonId },
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => setPendingId(null),
            },
        );
    }

    const selectedAddons = addons.filter((a) => Boolean(selectedMap[a.id]));
    const sampleMoney = basePrice ?? addons[0]?.price;
    const selectedSum = selectedAddons.reduce(
        (sum, a) => sum + Number(a.price.display_amount ?? a.price.amount ?? 0),
        0,
    );
    const baseNumeric = basePrice
        ? Number(basePrice.display_amount ?? basePrice.amount ?? 0)
        : null;

    return (
        <section className="my-5 rounded-xl border border-border bg-card p-4 shadow-xs">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="flex items-center gap-1.5 text-sm font-semibold text-foreground">
                        <ShieldCheck className="size-4 text-primary" />
                        {heading}
                    </p>
                    {subtext && <p className="mt-0.5 text-xs text-muted-foreground">{subtext}</p>}
                </div>
                {selectedAddons.length > 0 && (
                    <span className="shrink-0 rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-medium text-primary">
                        {selectedAddons.length} selected (+{formatCurrency(selectedSum, sampleMoney)})
                    </span>
                )}
            </div>

            <div className="mt-3 space-y-2.5">
                {addons.map((addon) => {
                    const meta = typeMeta[addon.type] ?? typeMeta.warranty;
                    const Icon = meta.icon;
                    const busy = pendingId === addon.id;
                    const isSelected = Boolean(selectedMap[addon.id]);

                    return (
                        <button
                            key={addon.id}
                            type="button"
                            disabled={busy}
                            onClick={() => toggleAddon(addon.id)}
                            className={`flex w-full items-start gap-3 rounded-lg border p-3 text-left transition ${
                                isSelected
                                    ? 'border-primary bg-primary/5 ring-1 ring-primary/30'
                                    : 'border-border/80 bg-background/60 hover:border-foreground/30'
                            } ${busy ? 'opacity-60' : ''}`}
                        >
                            <span
                                className={`mt-0.5 flex size-5 shrink-0 items-center justify-center rounded border transition ${
                                    isSelected
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-muted-foreground/40 bg-background'
                                }`}
                            >
                                {isSelected && <Check className="size-3.5 stroke-[3]" />}
                            </span>

                            <div className="min-w-0 flex-1">
                                <div className="flex flex-wrap items-center gap-1.5">
                                    <span className="text-sm font-medium text-foreground">{addon.name}</span>
                                    <span className="inline-flex items-center gap-1 rounded-md bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground">
                                        <Icon className="size-3" />
                                        {addon.badge || meta.label}
                                    </span>
                                </div>
                                {addon.description && (
                                    <p className="mt-0.5 text-xs leading-relaxed text-muted-foreground">
                                        {addon.description}
                                    </p>
                                )}
                            </div>

                            <div className="shrink-0 text-right">
                                <span className="text-sm font-semibold tabular-nums text-foreground">
                                    +{addon.price.formatted}
                                </span>
                                <p className="text-[10px] text-muted-foreground">per item</p>
                            </div>
                        </button>
                    );
                })}
            </div>

            {selectedAddons.length > 0 && baseNumeric !== null && (
                <div className="mt-3 flex flex-wrap items-center justify-between gap-2 rounded-lg bg-muted/60 px-3 py-2 text-xs">
                    <span className="text-muted-foreground">
                        Base {formatCurrency(baseNumeric, sampleMoney)} + Add-ons{' '}
                        <strong className="font-medium text-foreground">
                            +{formatCurrency(selectedSum, sampleMoney)}
                        </strong>
                    </span>
                    <span className="font-semibold tabular-nums text-foreground">
                        Total with add-ons: {formatCurrency(baseNumeric + selectedSum, sampleMoney)}
                    </span>
                </div>
            )}
        </section>
    );
}
