import { Link } from '@inertiajs/react';
import {
    ChevronDown,
    ChevronRight,
    Plus,
    SquareArrowOutUpRight,
    Trash2,
} from 'lucide-react';
import { Fragment, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { show as variantShow } from '@/routes/admin/products/variants';
import type { Option } from '@/types';
import { VariantImagePicker } from './variant-image-picker';
import type { VariantImageProps } from './variant-image-picker';
import { VariantInventory } from './variant-inventory';
import type { AttributeOption, Errors, VariantRow } from './types';
import { VariantFields } from './variant-fields';

const selectClass =
    'h-9 w-full min-w-24 rounded-md border border-input bg-transparent px-2 text-sm dark:bg-input/30';

/**
 * Variants of a variable product: one compact row each (options, SKU,
 * price, stock, available) with the remaining fields in an expandable row.
 */
export function VariantsTable({
    variants,
    attributes,
    currency,
    errors,
    onChange,
    onRemove,
    onAdd,
    productId,
    images,
    inventoryReasons,
}: VariantImageProps & {
    variants: VariantRow[];
    attributes: AttributeOption[];
    currency: string;
    errors: Errors;
    onChange: (index: number, patch: Partial<VariantRow>) => void;
    onRemove: (index: number) => void;
    onAdd: () => void;
    inventoryReasons: Option[];
}) {
    const [open, setOpen] = useState<number | null>(null);

    const setOption = (index: number, attributeId: number, valueId: number) => {
        const attribute = attributes.find((a) => a.id === attributeId);
        const others = new Set(attribute?.values.map((v) => v.id) ?? []);
        const current = variants[index].option_value_ids.filter(
            (id) => !others.has(id),
        );
        onChange(index, {
            option_value_ids: valueId ? [...current, valueId] : current,
        });
    };

    const rowHasError = (i: number) =>
        Object.keys(errors).some((k) => k.startsWith(`variants.${i}.`));

    return (
        <div className="grid gap-3">
            <div className="overflow-x-auto rounded-lg border">
                <table className="w-full text-sm">
                    <thead className="bg-muted/50 text-left text-xs text-muted-foreground">
                        <tr>
                            <th className="w-8 p-2">
                                <span className="sr-only">Details</span>
                            </th>
                            <th className="p-2 font-medium">Image</th>
                            {attributes.map((a) => (
                                <th key={a.id} className="p-2 font-medium">
                                    {a.name}
                                </th>
                            ))}
                            <th className="p-2 font-medium">SKU</th>
                            <th className="p-2 font-medium">
                                Price ({currency})
                            </th>
                            <th className="p-2 font-medium">Stock</th>
                            <th className="p-2 font-medium">Available</th>
                            <th className="w-10 p-2">
                                <span className="sr-only">Remove</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {variants.map((variant, i) => {
                            const expanded = open === i || rowHasError(i);

                            return (
                                <Fragment key={variant.id ?? `new-${i}`}>
                                    <tr
                                        className={cn(
                                            'border-t align-top',
                                            rowHasError(i) &&
                                                'bg-destructive/5',
                                        )}
                                    >
                                        <td className="p-2">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="size-8"
                                                aria-expanded={expanded}
                                                aria-label={`${expanded ? 'Hide' : 'Show'} details for variant ${i + 1}`}
                                                onClick={() =>
                                                    setOpen(
                                                        open === i ? null : i,
                                                    )
                                                }
                                            >
                                                {expanded ? (
                                                    <ChevronDown />
                                                ) : (
                                                    <ChevronRight />
                                                )}
                                            </Button>
                                        </td>
                                        <td className="p-2">
                                            <VariantImagePicker
                                                productId={productId}
                                                images={images}
                                                imageId={variant.image_id}
                                                label={`Choose image for variant ${i + 1}`}
                                                onChange={(image_id) =>
                                                    onChange(i, { image_id })
                                                }
                                            />
                                            <InputError
                                                message={
                                                    errors[
                                                        `variants.${i}.image_id`
                                                    ]
                                                }
                                            />
                                        </td>
                                        {attributes.map((attribute) => {
                                            const selected =
                                                attribute.values.find((v) =>
                                                    variant.option_value_ids.includes(
                                                        v.id,
                                                    ),
                                                );

                                            return (
                                                <td
                                                    key={attribute.id}
                                                    className="p-2"
                                                >
                                                    <select
                                                        aria-label={`${attribute.name} for variant ${i + 1}`}
                                                        value={
                                                            selected?.id ?? ''
                                                        }
                                                        onChange={(e) =>
                                                            setOption(
                                                                i,
                                                                attribute.id,
                                                                Number(
                                                                    e.target
                                                                        .value,
                                                                ),
                                                            )
                                                        }
                                                        className={selectClass}
                                                    >
                                                        <option value="">
                                                            Choose…
                                                        </option>
                                                        {attribute.values.map(
                                                            (v) => (
                                                                <option
                                                                    key={v.id}
                                                                    value={v.id}
                                                                >
                                                                    {v.value}
                                                                </option>
                                                            ),
                                                        )}
                                                    </select>
                                                </td>
                                            );
                                        })}
                                        <td className="p-2">
                                            <Input
                                                aria-label={`SKU for variant ${i + 1}`}
                                                value={variant.sku}
                                                onChange={(e) =>
                                                    onChange(i, {
                                                        sku: e.target.value,
                                                    })
                                                }
                                                className="min-w-28"
                                                required
                                            />
                                        </td>
                                        <td className="p-2">
                                            <Input
                                                aria-label={`Price for variant ${i + 1}`}
                                                inputMode="decimal"
                                                value={variant.price}
                                                onChange={(e) =>
                                                    onChange(i, {
                                                        price: e.target.value,
                                                    })
                                                }
                                                placeholder="0.00"
                                                className="min-w-24"
                                                required
                                            />
                                        </td>
                                        <td className="p-2 tabular-nums">
                                            {variant.id === null ? (
                                                <Input
                                                    aria-label={`Initial stock for variant ${i + 1}`}
                                                    type="number"
                                                    min={0}
                                                    max={1000000}
                                                    step={1}
                                                    className="w-24"
                                                    disabled={
                                                        !variant.track_inventory
                                                    }
                                                    value={
                                                        variant.initial_stock
                                                    }
                                                    onChange={(event) =>
                                                        onChange(i, {
                                                            initial_stock:
                                                                event.target
                                                                    .value,
                                                        })
                                                    }
                                                />
                                            ) : (
                                                <VariantInventory
                                                    variant={variant}
                                                    reasons={inventoryReasons}
                                                />
                                            )}
                                        </td>
                                        <td className="p-2 py-3">
                                            <Checkbox
                                                aria-label={`Variant ${i + 1} available for sale`}
                                                checked={variant.is_active}
                                                onCheckedChange={(c) =>
                                                    onChange(i, {
                                                        is_active: c === true,
                                                    })
                                                }
                                            />
                                        </td>
                                        <td className="flex p-2">
                                            {variant.id !== null &&
                                                productId !== undefined && (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-8"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={
                                                                variantShow({
                                                                    product:
                                                                        productId,
                                                                    variant:
                                                                        variant.id,
                                                                }).url
                                                            }
                                                            aria-label={`Open variant ${i + 1}`}
                                                            title="Open variant (price, inventory per location, shipping)"
                                                        >
                                                            <SquareArrowOutUpRight />
                                                        </Link>
                                                    </Button>
                                                )}
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="size-8"
                                                disabled={variants.length === 1}
                                                onClick={() => onRemove(i)}
                                                aria-label={`Remove variant ${i + 1}`}
                                            >
                                                <Trash2 />
                                            </Button>
                                        </td>
                                    </tr>
                                    {(errors[
                                        `variants.${i}.option_value_ids`
                                    ] ||
                                        errors[`variants.${i}.sku`] ||
                                        errors[`variants.${i}.price`]) && (
                                        <tr>
                                            <td
                                                colSpan={attributes.length + 7}
                                                className="px-2 pb-2"
                                            >
                                                <InputError
                                                    message={
                                                        errors[
                                                            `variants.${i}.option_value_ids`
                                                        ] ??
                                                        errors[
                                                            `variants.${i}.sku`
                                                        ] ??
                                                        errors[
                                                            `variants.${i}.price`
                                                        ]
                                                    }
                                                />
                                            </td>
                                        </tr>
                                    )}
                                    {expanded && (
                                        <tr className="bg-muted/20">
                                            <td
                                                colSpan={attributes.length + 7}
                                                className="p-4"
                                            >
                                                <VariantFields
                                                    index={i}
                                                    variant={variant}
                                                    currency={currency}
                                                    errors={errors}
                                                    inventoryReasons={
                                                        inventoryReasons
                                                    }
                                                    onChange={(patch) =>
                                                        onChange(i, patch)
                                                    }
                                                />
                                            </td>
                                        </tr>
                                    )}
                                </Fragment>
                            );
                        })}
                    </tbody>
                </table>
            </div>
            <div>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={onAdd}
                >
                    <Plus /> Add variant
                </Button>
            </div>
        </div>
    );
}
