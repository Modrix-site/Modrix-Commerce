import { Layers, Tag } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';

type TierItem = {
    min_qty: number;
    max_qty: number | null;
    range_label: string;
    badge_label: string;
    discount: number;
    discount_type: 'percent' | 'fixed_per_unit';
    unit_price: number;
    unit_price_formatted: string;
    savings_per_unit: number;
    savings_per_unit_formatted: string;
};

type Props = {
    rule_id: number;
    rule_title: string;
    heading: string;
    subheading: string;
    show_savings_badge: boolean;
    highlight_active_tier: boolean;
    currency: string;
    base_unit_price: number;
    base_unit_price_formatted: string;
    tiers: TierItem[];
};

function purchaseQuantity() {
    const inputs = document.querySelectorAll<HTMLInputElement>(
        'input[type="number"]:is([name="quantity"], #quantity, [aria-label="Quantity"])',
    );

    return Array.from(inputs).find(
        (input) =>
            input.form?.querySelector('[name="variant_id"]') &&
            input.getClientRects().length > 0,
    );
}

export default function TieredPricingTable({
    heading,
    subheading,
    show_savings_badge,
    highlight_active_tier,
    tiers,
}: Props) {
    const [quantity, setQuantity] = useState<{
        value: number;
        min: number;
        max: number;
    } | null>(null);

    useEffect(() => {
        const input = purchaseQuantity();
        if (!input) {
            setQuantity(null);
            return;
        }

        const sync = () => {
            setQuantity(
                input.matches(':disabled') || input.readOnly
                    ? null
                    : {
                          value: input.valueAsNumber,
                          min: Number(input.min || 1),
                          max: input.max ? Number(input.max) : Infinity,
                      },
            );
        };
        const observer = new MutationObserver(sync);
        observer.observe(input, {
            attributes: true,
            attributeFilter: ['value', 'min', 'max', 'disabled', 'readonly'],
        });
        input.addEventListener('input', sync);
        input.addEventListener('change', sync);
        sync();

        return () => {
            observer.disconnect();
            input.removeEventListener('input', sync);
            input.removeEventListener('change', sync);
        };
    }, [tiers]);

    const handleSelectTier = (nextQuantity: number) => {
        const input = purchaseQuantity();
        if (!input || input.matches(':disabled') || input.readOnly) {
            return;
        }

        // Bypass React's instance setter so the input event reaches onChange.
        Object.getOwnPropertyDescriptor(
            HTMLInputElement.prototype,
            'value',
        )?.set?.call(input, String(nextQuantity));
        input.dispatchEvent(new Event('input', { bubbles: true }));
    };

    if (!tiers || tiers.length === 0) {
        return null;
    }

    return (
        <Card className="my-5 overflow-hidden border border-blue-500/20 bg-gradient-to-b from-blue-500/[0.03] to-transparent shadow-xs">
            <CardContent className="p-4 sm:p-5">
                {/* Header */}
                <div className="flex items-center gap-2">
                    <Layers className="size-4 text-blue-600" />
                    <h4 className="text-sm font-bold tracking-tight text-foreground sm:text-base">
                        {heading}
                    </h4>
                </div>
                {subheading && (
                    <p className="mt-0.5 text-xs text-muted-foreground">
                        {subheading}
                    </p>
                )}

                {/* Tier Grid / Table */}
                <div className="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 md:grid-cols-3">
                    {tiers.map((tier, idx) => {
                        const nextQuantity = Math.max(
                            tier.min_qty,
                            quantity?.min ?? 1,
                        );
                        const canSelect =
                            quantity !== null &&
                            nextQuantity <= quantity.max &&
                            (tier.max_qty === null ||
                                nextQuantity <= tier.max_qty);
                        const isActive =
                            highlight_active_tier &&
                            quantity !== null &&
                            quantity.value >= tier.min_qty &&
                            (tier.max_qty === null ||
                                quantity.value <= tier.max_qty);

                        return (
                            <button
                                key={idx}
                                type="button"
                                aria-pressed={
                                    highlight_active_tier ? isActive : undefined
                                }
                                disabled={!canSelect}
                                onClick={() => handleSelectTier(nextQuantity)}
                                className={cn(
                                    'flex flex-col items-start justify-between rounded-lg border p-3 text-left transition-all enabled:cursor-pointer enabled:hover:border-blue-500/60 enabled:hover:bg-muted/50',
                                    isActive
                                        ? 'border-blue-600 bg-blue-50/50 ring-1 ring-blue-600 dark:bg-blue-950/20'
                                        : 'border-border/80 bg-card',
                                )}
                            >
                                <div className="flex w-full items-center justify-between gap-1">
                                    <span className="text-xs font-bold text-foreground">
                                        {tier.range_label}
                                    </span>
                                    {show_savings_badge && tier.badge_label && (
                                        <Badge
                                            variant="secondary"
                                            className="bg-blue-100 text-[10px] font-semibold text-blue-800 dark:bg-blue-900/60 dark:text-blue-200"
                                        >
                                            {tier.badge_label}
                                        </Badge>
                                    )}
                                </div>

                                <div className="mt-2 flex w-full items-baseline justify-between">
                                    <span className="text-sm font-extrabold text-blue-600 dark:text-blue-400">
                                        {tier.unit_price_formatted}
                                        <span className="text-[10px] font-normal text-muted-foreground">
                                            {' '}
                                            / ea
                                        </span>
                                    </span>

                                    <span className="flex items-center gap-0.5 text-[10px] text-muted-foreground">
                                        <Tag className="size-2.5 text-emerald-600" />
                                        Save {tier.savings_per_unit_formatted}
                                    </span>
                                </div>
                            </button>
                        );
                    })}
                </div>
            </CardContent>
        </Card>
    );
}
