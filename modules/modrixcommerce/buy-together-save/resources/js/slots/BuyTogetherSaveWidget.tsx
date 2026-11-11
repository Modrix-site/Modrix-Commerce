import { router } from '@inertiajs/react';
import { Check, Loader2, Plus, Sparkles, Tag } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { cn } from '@/lib/utils';

type BundleItem = {
    id: number;
    name: string;
    slug: string;
    image: string | null;
    variant_id: number;
    price: number;
    formatted_price: string;
    is_main: boolean;
};

type Props = {
    deal_id: number | null;
    heading: string;
    subheading: string;
    badge_text: string | null;
    discount_type: 'percent' | 'fixed';
    discount_value: number;
    cta_button_text: string;
    show_savings_badge: boolean;
    currency: string;
    main_item: BundleItem;
    items: BundleItem[];
    total_original: number;
    total_original_formatted: string;
    total_bundle: number;
    total_bundle_formatted: string;
    savings_amount: number;
    savings_amount_formatted: string;
    add_bundle_url: string;
};

export default function BuyTogetherSaveWidget({
    deal_id,
    heading,
    subheading,
    badge_text,
    discount_type,
    discount_value,
    cta_button_text,
    show_savings_badge,
    currency,
    main_item,
    items,
    add_bundle_url,
}: Props) {
    // Companion items checked by default
    const [selectedCompanionIds, setSelectedCompanionIds] = useState<number[]>(
        () => items.map((i) => i.variant_id),
    );
    const [isSubmitting, setIsSubmitting] = useState(false);

    const toggleCompanion = (variantId: number) => {
        setSelectedCompanionIds((prev) =>
            prev.includes(variantId)
                ? prev.filter((id) => id !== variantId)
                : [...prev, variantId],
        );
    };

    // Calculate dynamic pricing based on selections
    const pricing = useMemo(() => {
        let original = main_item.price;
        const selectedItems = items.filter((i) =>
            selectedCompanionIds.includes(i.variant_id),
        );

        for (const item of selectedItems) {
            original += item.price;
        }

        const totalItemsCount = 1 + selectedItems.length;
        let savings = 0;

        if (totalItemsCount >= 2) {
            if (discount_type === 'percent') {
                const ratio = Math.max(0, Math.min(100, discount_value)) / 100;
                savings = Math.round(original * ratio * 100) / 100;
            } else {
                savings = Math.min(original, discount_value);
            }
        }

        const bundleTotal = Math.max(0, original - savings);

        return {
            originalTotal: original,
            bundleTotal,
            savings,
            hasSavings: savings > 0 && totalItemsCount >= 2,
            count: totalItemsCount,
        };
    }, [main_item, items, selectedCompanionIds, discount_type, discount_value]);

    const formatPrice = (amount: number) => {
        return `${currency} ${amount.toFixed(2)}`;
    };

    const handleAddBundle = () => {
        if (pricing.count < 2 || isSubmitting) {
            return;
        }

        setIsSubmitting(true);

        const allVariantIds = [main_item.variant_id, ...selectedCompanionIds];

        router.post(
            add_bundle_url,
            {
                product_id: main_item.id,
                variant_ids: allVariantIds,
                deal_id: deal_id,
            },
            {
                preserveScroll: true,
                onFinish: () => setIsSubmitting(false),
            },
        );
    };

    return (
        <Card className="my-8 overflow-hidden border-2 border-emerald-500/20 bg-gradient-to-b from-emerald-500/[0.03] to-transparent shadow-sm">
            <CardContent className="p-5 sm:p-6">
                {/* Header Section */}
                <div className="flex flex-wrap items-center justify-between gap-3 border-b pb-4">
                    <div>
                        <div className="flex items-center gap-2">
                            <Sparkles className="size-5 text-emerald-600" />
                            <h3 className="text-lg font-bold tracking-tight text-foreground sm:text-xl">
                                {heading}
                            </h3>
                            {show_savings_badge && badge_text && (
                                <Badge className="bg-emerald-600 font-semibold text-white hover:bg-emerald-700">
                                    {badge_text}
                                </Badge>
                            )}
                        </div>
                        {subheading && (
                            <p className="mt-1 text-sm text-muted-foreground">
                                {subheading}
                            </p>
                        )}
                    </div>
                </div>

                {/* Products Bundle Display */}
                <div className="mt-6 flex flex-wrap items-center justify-center gap-3 md:justify-start">
                    {/* Main Product Card */}
                    <div className="flex flex-col items-center rounded-lg border bg-card p-3 text-center shadow-xs transition-shadow hover:shadow-sm sm:w-44">
                        <div className="relative size-24 shrink-0 overflow-hidden rounded-md bg-muted/40 sm:size-28">
                            {main_item.image ? (
                                <img
                                    src={main_item.image}
                                    alt={main_item.name}
                                    className="size-full object-cover object-center"
                                />
                            ) : (
                                <div className="flex size-full items-center justify-center text-xs text-muted-foreground">
                                    No Image
                                </div>
                            )}
                            <Badge
                                variant="secondary"
                                className="absolute bottom-1 left-1 text-[10px] font-bold uppercase"
                            >
                                This item
                            </Badge>
                        </div>
                        <span className="mt-2 line-clamp-2 text-xs font-medium text-foreground sm:text-sm">
                            {main_item.name}
                        </span>
                        <span className="mt-1 font-semibold text-foreground">
                            {main_item.formatted_price}
                        </span>
                    </div>

                    {/* Companion Items with Plus Separator */}
                    {items.map((item) => {
                        const isChecked = selectedCompanionIds.includes(
                            item.variant_id,
                        );

                        return (
                            <div
                                key={item.id}
                                className="flex items-center gap-3"
                            >
                                <div className="flex size-8 items-center justify-center rounded-full bg-muted font-bold text-muted-foreground">
                                    <Plus className="size-4" />
                                </div>

                                <div
                                    className={cn(
                                        'flex cursor-pointer flex-col items-center rounded-lg border bg-card p-3 text-center shadow-xs transition-all hover:shadow-sm sm:w-44',
                                        isChecked
                                            ? 'border-emerald-500/60 ring-1 ring-emerald-500/20'
                                            : 'border-dashed opacity-60',
                                    )}
                                    onClick={() =>
                                        toggleCompanion(item.variant_id)
                                    }
                                >
                                    <div className="relative size-24 shrink-0 overflow-hidden rounded-md bg-muted/40 sm:size-28">
                                        {item.image ? (
                                            <img
                                                src={item.image}
                                                alt={item.name}
                                                className="size-full object-cover object-center"
                                            />
                                        ) : (
                                            <div className="flex size-full items-center justify-center text-xs text-muted-foreground">
                                                No Image
                                            </div>
                                        )}
                                        <div className="absolute top-1.5 right-1.5 rounded-sm bg-background/80 p-0.5 shadow-xs">
                                            <Checkbox
                                                checked={isChecked}
                                                onCheckedChange={() =>
                                                    toggleCompanion(
                                                        item.variant_id,
                                                    )
                                                }
                                            />
                                        </div>
                                    </div>
                                    <span className="mt-2 line-clamp-2 text-xs font-medium text-foreground sm:text-sm">
                                        {item.name}
                                    </span>
                                    <span className="mt-1 font-semibold text-foreground">
                                        {item.formatted_price}
                                    </span>
                                </div>
                            </div>
                        );
                    })}
                </div>

                {/* Price Summary & CTA Action Bar */}
                <div className="mt-6 flex flex-col items-center justify-between gap-4 rounded-xl bg-muted/40 p-4 sm:flex-row sm:p-5">
                    <div className="flex flex-col text-center sm:text-left">
                        <div className="flex flex-wrap items-baseline justify-center gap-2 sm:justify-start">
                            <span className="text-sm font-medium text-muted-foreground">
                                Total Bundle Price:
                            </span>
                            <span className="text-2xl font-extrabold text-emerald-700 dark:text-emerald-400">
                                {formatPrice(pricing.bundleTotal)}
                            </span>
                            {pricing.hasSavings && (
                                <span className="text-sm text-muted-foreground line-through">
                                    {formatPrice(pricing.originalTotal)}
                                </span>
                            )}
                        </div>

                        {pricing.hasSavings ? (
                            <div className="mt-1 flex items-center justify-center gap-1.5 text-sm font-semibold text-emerald-600 sm:justify-start">
                                <Tag className="size-4" />
                                <span>
                                    You Save: {formatPrice(pricing.savings)}
                                </span>
                            </div>
                        ) : (
                            <span className="mt-1 text-xs text-muted-foreground">
                                Select at least one companion product to unlock
                                bundle savings!
                            </span>
                        )}
                    </div>

                    <Button
                        type="button"
                        size="lg"
                        className="w-full bg-emerald-600 font-semibold text-white hover:bg-emerald-700 sm:w-auto"
                        disabled={pricing.count < 2 || isSubmitting}
                        onClick={handleAddBundle}
                    >
                        {isSubmitting ? (
                            <>
                                <Loader2 className="mr-2 size-4 animate-spin" />
                                Adding Bundle...
                            </>
                        ) : (
                            <>
                                <Check className="mr-2 size-4" />
                                {cta_button_text} ({pricing.count} items)
                            </>
                        )}
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}
