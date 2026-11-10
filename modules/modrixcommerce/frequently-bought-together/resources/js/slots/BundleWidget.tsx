import { Form } from "@inertiajs/react";
import { Check, Plus } from "lucide-react";
import { useMemo, useState } from "react";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { cn } from "@/lib/utils";

type Price = {
    formatted: string;
    display_amount: string;
    display_currency: string;
    amount: string;
    currency: string;
};

type Companion = {
    id: number;
    name: string;
    slug: string;
    url: string;
    image: { url: string; alt: string | null } | null;
    variant_id: number;
    price: Price | null;
    compare_at_price: Price | null;
    has_variants: boolean;
};

type Props = {
    heading: string;
    current: {
        id: number;
        name: string;
        variant_id: number | null;
        image: { url: string; alt: string | null } | null;
    };
    items: Companion[];
    showSavings: boolean;
    addAllUrl: string;
    variant?: { id: number; price: Price | null; purchasable: boolean };
};

/**
 * "Frequently bought together": the product being viewed beside the products
 * customers usually buy with it. Shoppers tick what they want and add the set
 * to the cart in one request. The suggestions come from real order history.
 */
export default function BundleWidget({
    heading,
    current,
    items,
    showSavings,
    addAllUrl,
    variant,
}: Props) {
    const [selected, setSelected] = useState<number[]>(() =>
        items.map((item) => item.variant_id),
    );

    const toggle = (variantId: number) => {
        setSelected((ids) =>
            ids.includes(variantId)
                ? ids.filter((id) => id !== variantId)
                : [...ids, variantId],
        );
    };

    const total = useMemo(() => {
        const chosen = items.filter((item) =>
            selected.includes(item.variant_id),
        );

        if (chosen.some((item) => item.price === null)) {
            return null;
        }

        const currency = chosen[0]?.price?.display_currency;

        if (!currency) {
            return null;
        }

        if (
            variant &&
            (!variant.price || variant.price.display_currency !== currency)
        ) {
            return null;
        }
        const amount = chosen.reduce(
            (sum, item) => sum + Number(item.price?.display_amount ?? 0),
            Number(variant?.price?.display_amount ?? 0),
        );

        return `${amount.toFixed(2)} ${currency}`;
    }, [items, selected, variant]);

    const count = selected.length + (variant ? 1 : 0);

    return (
        <section
            className="rounded-[var(--radius)] border bg-muted/40 p-5"
            aria-labelledby="fbt-heading"
        >
            <h2 id="fbt-heading" className="text-lg font-semibold">
                {heading}
            </h2>

            <ul className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <li className="flex items-center gap-3 rounded-[var(--radius)] border bg-background p-3">
                    <BundleThumb image={current.image} name={current.name} />
                    <div className="grid gap-1">
                        <span className="text-sm font-medium">
                            {current.name}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            This item
                        </span>
                    </div>
                </li>

                {items.map((item) => {
                    const checked = selected.includes(item.variant_id);

                    return (
                        <li
                            key={item.id}
                            className={cn(
                                "flex items-center gap-3 rounded-[var(--radius)] border bg-background p-3",
                                checked && "ring-1 ring-primary",
                            )}
                        >
                            <Checkbox
                                checked={checked}
                                onCheckedChange={() => toggle(item.variant_id)}
                                aria-label={`Include ${item.name}`}
                            />
                            <BundleThumb image={item.image} name={item.name} />
                            <div className="grid min-w-0 gap-1">
                                <a
                                    href={item.url}
                                    className="truncate text-sm font-medium hover:underline"
                                >
                                    {item.name}
                                </a>
                                <span className="flex flex-wrap items-baseline gap-2 text-sm">
                                    {item.price && (
                                        <span className="font-semibold">
                                            {item.price.formatted}
                                        </span>
                                    )}
                                    {item.compare_at_price && (
                                        <span className="text-xs text-muted-foreground line-through">
                                            {item.compare_at_price.formatted}
                                        </span>
                                    )}
                                </span>
                            </div>
                        </li>
                    );
                })}
            </ul>

            <Form
                action={addAllUrl}
                method="post"
                options={{ preserveScroll: true }}
                className="mt-4 flex flex-wrap items-center gap-3"
            >
                {({ processing }) => (
                    <>
                        <input
                            type="hidden"
                            name="product_id"
                            value={current.id}
                        />
                        {variant && (
                            <input
                                type="hidden"
                                name="current_variant_id"
                                value={variant.id}
                            />
                        )}
                        {selected.map((id) => (
                            <input
                                key={id}
                                type="hidden"
                                name="variant_ids[]"
                                value={id}
                            />
                        ))}
                        <Button
                            type="submit"
                            disabled={
                                processing ||
                                selected.length === 0 ||
                                (variant !== undefined && !variant.purchasable)
                            }
                        >
                            <Plus /> Add {count} to cart
                        </Button>
                        {showSavings && total !== null && (
                            <span className="text-sm text-muted-foreground">
                                {variant ? "Bundle total: " : "Extras total: "}
                                <span className="font-semibold text-foreground">
                                    {total}
                                </span>
                            </span>
                        )}
                        {selected.length === 0 && (
                            <span className="flex items-center gap-1 text-xs text-muted-foreground">
                                <Check className="size-3.5" /> Pick at least one
                                extra item
                            </span>
                        )}
                    </>
                )}
            </Form>
        </section>
    );
}

function BundleThumb({
    image,
    name,
}: {
    image: { url: string; alt: string | null } | null;
    name: string;
}) {
    if (!image) {
        return (
            <span className="size-12 shrink-0 rounded bg-muted" aria-hidden />
        );
    }

    return (
        <img
            src={image.url}
            alt={image.alt ?? name}
            className="size-12 shrink-0 rounded object-cover"
            loading="lazy"
        />
    );
}
