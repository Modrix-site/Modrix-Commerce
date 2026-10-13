import { FormField } from '@/components/admin/form-field';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { margin } from './types';
import type { Errors, VariantRow } from './types';

type Props = {
    index: number;
    variant: VariantRow;
    currency: string;
    errors: Errors;
    onChange: (patch: Partial<VariantRow>) => void;
    /** Simple products show SKU and price here; variants show them in the table row. */
    withBasics?: boolean;
};

/**
 * Price, cost, stock and identifiers for one variant.
 */
export function VariantFields({
    index: i,
    variant,
    currency,
    errors,
    onChange,
    withBasics = false,
}: Props) {
    const pct = margin(variant.price, variant.cost);
    const profit =
        pct === null
            ? null
            : (Number(variant.price) - Number(variant.cost)).toFixed(2);

    return (
        <div className="grid gap-4">
            <div className="grid gap-3 sm:grid-cols-3">
                {withBasics && (
                    <FormField
                        id={`v${i}-price`}
                        label={`Price (${currency})`}
                        error={errors[`variants.${i}.price`]}
                    >
                        <Input
                            id={`v${i}-price`}
                            inputMode="decimal"
                            value={variant.price}
                            onChange={(e) =>
                                onChange({ price: e.target.value })
                            }
                            placeholder="0.00"
                            required
                        />
                    </FormField>
                )}
                <FormField
                    id={`v${i}-compare`}
                    label="Compare-at price"
                    error={errors[`variants.${i}.compare_at_price`]}
                    hint="Shown crossed out when higher than the price."
                >
                    <Input
                        id={`v${i}-compare`}
                        inputMode="decimal"
                        value={variant.compare_at_price}
                        onChange={(e) =>
                            onChange({ compare_at_price: e.target.value })
                        }
                        placeholder="Optional"
                    />
                </FormField>
                <FormField
                    id={`v${i}-cost`}
                    label="Cost per item"
                    error={errors[`variants.${i}.cost`]}
                    hint="Only staff see this."
                >
                    <Input
                        id={`v${i}-cost`}
                        inputMode="decimal"
                        value={variant.cost}
                        onChange={(e) => onChange({ cost: e.target.value })}
                        placeholder="Optional"
                    />
                </FormField>
                <div className="grid content-start gap-2">
                    <span className="text-sm font-medium">Margin</span>
                    <span
                        className={cn(
                            'py-2 text-sm tabular-nums',
                            pct !== null && pct < 0 && 'text-destructive',
                            pct === null && 'text-muted-foreground',
                        )}
                        aria-live="polite"
                    >
                        {pct === null
                            ? 'Enter a price and cost'
                            : `${pct}% · ${profit} ${currency} profit`}
                    </span>
                </div>
            </div>

            <div className="grid gap-3 sm:grid-cols-3">
                {withBasics && (
                    <FormField
                        id={`v${i}-sku`}
                        label="SKU"
                        error={errors[`variants.${i}.sku`]}
                    >
                        <Input
                            id={`v${i}-sku`}
                            value={variant.sku}
                            onChange={(e) => onChange({ sku: e.target.value })}
                            required
                        />
                    </FormField>
                )}
                <FormField
                    id={`v${i}-barcode`}
                    label="Barcode (ISBN, UPC, GTIN…)"
                    error={errors[`variants.${i}.barcode`]}
                >
                    <Input
                        id={`v${i}-barcode`}
                        value={variant.barcode}
                        onChange={(e) => onChange({ barcode: e.target.value })}
                    />
                </FormField>
                {variant.id === null ? (
                    <FormField
                        id={`v${i}-stock`}
                        label="Initial stock"
                        error={errors[`variants.${i}.initial_stock`]}
                    >
                        <Input
                            id={`v${i}-stock`}
                            type="number"
                            min={0}
                            value={variant.initial_stock}
                            disabled={!variant.track_inventory}
                            onChange={(e) =>
                                onChange({ initial_stock: e.target.value })
                            }
                        />
                    </FormField>
                ) : (
                    <div className="grid content-start gap-2">
                        <span className="text-sm font-medium">On hand</span>
                        <span className="py-2 text-sm tabular-nums">
                            {variant.track_inventory
                                ? (variant.on_hand ?? 0)
                                : 'Not tracked'}
                            <span className="block text-xs text-muted-foreground">
                                Change stock on the Inventory page.
                            </span>
                        </span>
                    </div>
                )}
                <FormField
                    id={`v${i}-low`}
                    label="Low-stock alert at"
                    error={errors[`variants.${i}.low_stock_threshold`]}
                >
                    <Input
                        id={`v${i}-low`}
                        type="number"
                        min={0}
                        value={variant.low_stock_threshold}
                        placeholder="Store default"
                        onChange={(e) =>
                            onChange({ low_stock_threshold: e.target.value })
                        }
                    />
                </FormField>
            </div>

            <div className="flex flex-wrap gap-5 text-sm">
                <label className="flex items-center gap-2">
                    <Checkbox
                        checked={variant.track_inventory}
                        onCheckedChange={(c) =>
                            onChange({ track_inventory: c === true })
                        }
                    />
                    Track inventory
                </label>
                <label className="flex items-center gap-2">
                    <Checkbox
                        checked={variant.allow_backorder}
                        disabled={!variant.track_inventory}
                        onCheckedChange={(c) =>
                            onChange({ allow_backorder: c === true })
                        }
                    />
                    Sell when out of stock
                </label>
                <label className="flex items-center gap-2">
                    <Checkbox
                        checked={variant.is_active}
                        onCheckedChange={(c) =>
                            onChange({ is_active: c === true })
                        }
                    />
                    Available for sale
                </label>
            </div>
        </div>
    );
}

/**
 * Weight and dimensions for one variant (used for shipping).
 */
export function ShippingFields({
    index: i,
    variant,
    errors,
    weightUnit,
    dimensionUnit,
    onChange,
}: {
    index: number;
    variant: VariantRow;
    errors: Errors;
    weightUnit: string;
    dimensionUnit: string;
    onChange: (patch: Partial<VariantRow>) => void;
}) {
    const field = (
        key: 'weight' | 'length' | 'width' | 'height',
        label: string,
    ) => (
        <FormField
            id={`v${i}-${key}`}
            label={label}
            error={errors[`variants.${i}.${key}`]}
        >
            <Input
                id={`v${i}-${key}`}
                inputMode="decimal"
                value={variant[key]}
                onChange={(e) => onChange({ [key]: e.target.value })}
            />
        </FormField>
    );

    return (
        <div className="grid gap-3 sm:grid-cols-4">
            {field('weight', `Weight (${weightUnit})`)}
            {field('length', `Length (${dimensionUnit})`)}
            {field('width', `Width (${dimensionUnit})`)}
            {field('height', `Height (${dimensionUnit})`)}
        </div>
    );
}
