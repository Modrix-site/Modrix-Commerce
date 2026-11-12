import { Tags, WandSparkles } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { AttributeOption, VariantRow } from './types';

export function VariantBulkEditor({
    variants,
    attributes,
    currency,
    productName,
    onChange,
}: {
    variants: VariantRow[];
    attributes: AttributeOption[];
    currency: string;
    productName: string;
    onChange: (variants: VariantRow[]) => void;
}) {
    const [open, setOpen] = useState(false);
    const [price, setPrice] = useState('');
    const [compare, setCompare] = useState('');
    const [error, setError] = useState('');
    const [notice, setNotice] = useState('');
    const missing = variants.filter((variant) => !variant.sku.trim()).length;
    const generate = () => {
        const normalise = (value: string) =>
            value
                .normalize('NFKD')
                .replace(/[\u0300-\u036f]/g, '')
                .toUpperCase()
                .replace(/[^A-Z0-9]+/g, '-')
                .replace(/^-|-$/g, '');
        const prefix = normalise(productName).slice(0, 30) || 'PRODUCT';
        const values = new Map(
            attributes.flatMap((attribute) =>
                attribute.values.map(
                    (value) => [value.id, value.value] as const,
                ),
            ),
        );
        const used = new Set(
            variants
                .filter((variant) => variant.sku.trim())
                .map((variant) => variant.sku.trim().toUpperCase()),
        );
        onChange(
            variants.map((variant, index) => {
                if (variant.sku.trim()) return variant;
                const suffix =
                    normalise(
                        variant.option_value_ids
                            .map((id) => values.get(id) ?? String(id))
                            .join('-'),
                    ) || String(index + 1);
                const base = `${prefix}-${suffix}`.slice(0, 57);
                let sku = base;
                let sequence = 2;
                while (used.has(sku)) sku = `${base}-${sequence++}`;
                used.add(sku);
                return { ...variant, sku };
            }),
        );
        setNotice(`Generated ${missing} SKUs. Save the product to apply them.`);
    };
    return (
        <div className="grid gap-2">
            <div className="flex flex-wrap items-center gap-2 rounded-lg border bg-muted/20 p-2">
                <span className="mr-auto px-1 text-xs text-muted-foreground">
                    Variant tools
                </span>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={missing === 0}
                    onClick={generate}
                >
                    <WandSparkles className="size-3.5" />
                    Generate missing SKUs{missing > 0 ? ` (${missing})` : ''}
                </Button>
                <Dialog
                    open={open}
                    onOpenChange={(value) => {
                        setOpen(value);
                        if (value) {
                            setPrice('');
                            setCompare('');
                            setError('');
                        }
                    }}
                >
                    <DialogTrigger asChild>
                        <Button type="button" variant="outline" size="sm">
                            <Tags className="size-3.5" />
                            Bulk pricing
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogTitle>Update variant prices</DialogTitle>
                        <DialogDescription>
                            Apply prices to all {variants.length} variants.
                            Leave a field blank to keep its current value.
                            Changes are applied when you save the product.
                        </DialogDescription>
                        <form
                            className="grid gap-4"
                            onSubmit={(event) => {
                                event.preventDefault();
                                const valid = (value: string) =>
                                    /^\d{1,13}(\.\d{1,4})?$/.test(value);
                                if (
                                    (!price && !compare) ||
                                    (price && !valid(price)) ||
                                    (compare && !valid(compare))
                                ) {
                                    setError(
                                        'Enter a valid price or compare-at price.',
                                    );
                                    return;
                                }
                                if (
                                    variants.some((variant) => {
                                        const nextPrice =
                                            price || variant.price;
                                        const nextCompare =
                                            compare || variant.compare_at_price;
                                        return (
                                            nextCompare !== '' &&
                                            Number(nextCompare) <=
                                                Number(nextPrice)
                                        );
                                    })
                                ) {
                                    setError(
                                        'Compare-at prices must be higher than the selling price for every variant.',
                                    );
                                    return;
                                }
                                onChange(
                                    variants.map((variant) => ({
                                        ...variant,
                                        ...(price ? { price } : {}),
                                        ...(compare
                                            ? { compare_at_price: compare }
                                            : {}),
                                    })),
                                );
                                setNotice(
                                    `Updated pricing for ${variants.length} variants. Save the product to apply it.`,
                                );
                                setOpen(false);
                            }}
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="bulk-variant-price">
                                    Price ({currency})
                                </Label>
                                <Input
                                    id="bulk-variant-price"
                                    inputMode="decimal"
                                    value={price}
                                    onChange={(event) =>
                                        setPrice(event.target.value)
                                    }
                                    placeholder="Keep existing prices"
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="bulk-variant-compare">
                                    Compare-at price ({currency})
                                </Label>
                                <Input
                                    id="bulk-variant-compare"
                                    inputMode="decimal"
                                    value={compare}
                                    onChange={(event) =>
                                        setCompare(event.target.value)
                                    }
                                    placeholder="Keep existing compare-at prices"
                                />
                            </div>
                            {error && (
                                <p
                                    className="text-xs text-destructive"
                                    role="alert"
                                >
                                    {error}
                                </p>
                            )}
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit">
                                    Apply to all variants
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
            {notice && (
                <p role="status" className="text-xs text-muted-foreground">
                    {notice}
                </p>
            )}
        </div>
    );
}
