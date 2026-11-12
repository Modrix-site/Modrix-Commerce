import { Head, Link, router, useForm } from '@inertiajs/react';
import { ChevronDown, ImageOff, ImagePlus, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { NativeSelect } from '@/components/admin/form-field';
import { AdjustStockDialog } from '@/components/admin/inventory/adjust-stock-dialog';
import { useUnsavedChangesGuard } from '@/components/admin/products/save-bar';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCan } from '@/hooks/use-can';
import { cn } from '@/lib/utils';
import { index as productsIndex } from '@/routes/admin/products';
import type { Option } from '@/types';

type VariantLink = {
    id: number;
    name: string;
    sku: string;
    image_url: string | null;
    options: Record<string, string>;
    url: string;
};

type Props = {
    product: {
        id: number;
        name: string;
        status: string;
        image_url: string | null;
        edit_url: string;
        options: { name: string; values: string[] }[];
        variants: VariantLink[];
        images: { id: number; url: string; alt: string | null }[];
    };
    variant: {
        id: number;
        name: string;
        options: { name: string; value: string }[];
        image_id: number | null;
        sku: string;
        barcode: string | null;
        price: string;
        compare_at_price: string;
        cost: string;
        track_inventory: boolean;
        allow_backorder: boolean;
        low_stock_threshold: number | null;
        weight: string | null;
        length: string | null;
        width: string | null;
        height: string | null;
        is_active: boolean;
    };
    locations: {
        id: number;
        name: string;
        available: number;
        committed: number;
        on_hand: number;
    }[];
    currency: string;
    units: { weight: string; dimension: string };
    urls: { update: string; adjust: string; history: string };
    reasons: Option[];
};

export default function VariantPage({
    product,
    variant,
    locations,
    currency,
    units,
    urls,
    reasons,
}: Props) {
    const can = useCan();
    const form = useForm({
        sku: variant.sku,
        barcode: variant.barcode ?? '',
        image_id: variant.image_id,
        price: variant.price,
        compare_at_price: variant.compare_at_price,
        cost: variant.cost,
        track_inventory: variant.track_inventory,
        allow_backorder: variant.allow_backorder,
        low_stock_threshold:
            variant.low_stock_threshold === null
                ? ''
                : String(variant.low_stock_threshold),
        weight: variant.weight ?? '',
        length: variant.length ?? '',
        width: variant.width ?? '',
        height: variant.height ?? '',
    });
    const { data, setData, errors } = form;
    useUnsavedChangesGuard(form.isDirty && !form.processing);
    const image = product.images.find((i) => i.id === data.image_id) ?? null;
    const save = () =>
        form.put(urls.update, {
            preserveScroll: true,
            onSuccess: () => form.setDefaults(),
        });
    const status =
        product.status === 'published'
            ? 'Active'
            : product.status === 'draft'
              ? 'Draft'
              : product.status;

    return (
        <>
            <Head title={`${variant.name} · ${product.name}`} />
            <div className="mx-auto grid w-full max-w-6xl gap-6 p-4 pb-28 md:p-6">
                <nav
                    className="flex items-center gap-1 text-sm text-muted-foreground"
                    aria-label="Breadcrumb"
                >
                    <Link
                        href={productsIndex()}
                        className="hover:text-foreground"
                    >
                        Products
                    </Link>
                    <span>/</span>
                    <Link
                        href={product.edit_url}
                        className="hover:text-foreground"
                    >
                        {product.name}
                    </Link>
                </nav>
                <h1 className="text-2xl font-semibold tracking-tight">
                    {variant.name}
                </h1>

                <div className="grid items-start gap-6 lg:grid-cols-[22rem_1fr]">
                    <VariantList
                        product={product}
                        current={variant.id}
                        status={status}
                    />

                    <div className="grid gap-6">
                        <Card>
                            <div className="flex gap-4">
                                <ImagePicker
                                    images={product.images}
                                    value={image}
                                    onChange={(id) => setData('image_id', id)}
                                />
                                <div className="grid content-start gap-1">
                                    <p className="font-medium">
                                        {variant.name}
                                    </p>
                                    {!variant.is_active && (
                                        <Badge variant="secondary">
                                            Hidden
                                        </Badge>
                                    )}
                                </div>
                            </div>
                            {variant.options.length > 0 && (
                                <div className="mt-4 grid gap-3">
                                    {variant.options.map((option) => (
                                        <div
                                            key={option.name}
                                            className="grid gap-1.5"
                                        >
                                            <Label>{option.name}</Label>
                                            <Input
                                                value={option.value}
                                                readOnly
                                                aria-readonly
                                                className="bg-muted/40"
                                            />
                                        </div>
                                    ))}
                                    <p className="text-xs text-muted-foreground">
                                        Option values are set on the product.{' '}
                                        <Link
                                            href={product.edit_url}
                                            className="underline"
                                        >
                                            Edit options
                                        </Link>
                                    </p>
                                </div>
                            )}
                        </Card>

                        <section className="grid gap-3">
                            <h2 className="font-semibold">Price</h2>
                            <Card>
                                <MoneyInput
                                    id="price"
                                    label="Price"
                                    currency={currency}
                                    value={data.price}
                                    onChange={(v) => setData('price', v)}
                                    error={errors.price}
                                />
                                <Disclosure
                                    pills={[
                                        ['Compare-at', data.compare_at_price],
                                        ['Cost per item', data.cost],
                                    ]}
                                >
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <MoneyInput
                                            id="compare_at_price"
                                            label="Compare-at price"
                                            currency={currency}
                                            value={data.compare_at_price}
                                            onChange={(v) =>
                                                setData('compare_at_price', v)
                                            }
                                            error={errors.compare_at_price}
                                        />
                                        <MoneyInput
                                            id="cost"
                                            label="Cost per item"
                                            currency={currency}
                                            value={data.cost}
                                            onChange={(v) => setData('cost', v)}
                                            error={errors.cost}
                                        />
                                    </div>
                                </Disclosure>
                            </Card>
                        </section>

                        <Card>
                            <div className="flex items-center justify-between">
                                <h2 className="font-semibold">Inventory</h2>
                                <label className="flex items-center gap-2 text-sm text-muted-foreground">
                                    Inventory tracked
                                    <Checkbox
                                        checked={data.track_inventory}
                                        onCheckedChange={(v) =>
                                            setData(
                                                'track_inventory',
                                                v === true,
                                            )
                                        }
                                    />
                                </label>
                            </div>
                            {data.track_inventory ? (
                                <div className="mt-4 overflow-hidden rounded-lg border">
                                    <table className="w-full text-sm">
                                        <thead className="bg-muted/50 text-muted-foreground">
                                            <tr>
                                                <th className="px-4 py-2 text-left font-medium">
                                                    Locations
                                                </th>
                                                <th
                                                    className="px-4 py-2 text-right font-medium"
                                                    title="Sold, not yet shipped"
                                                >
                                                    Committed
                                                </th>
                                                <th
                                                    className="px-4 py-2 text-right font-medium"
                                                    title="Can be sold now"
                                                >
                                                    Available
                                                </th>
                                                <th
                                                    className="px-4 py-2 text-right font-medium"
                                                    title="Available plus committed"
                                                >
                                                    On hand
                                                </th>
                                                <th className="px-4 py-2">
                                                    <span className="sr-only">
                                                        Adjust
                                                    </span>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {locations.map((location) => (
                                                <tr
                                                    key={location.id}
                                                    className="border-t"
                                                >
                                                    <td className="px-4 py-3">
                                                        {location.name}
                                                    </td>
                                                    <td className="px-4 py-3 text-right tabular-nums">
                                                        {location.committed}
                                                    </td>
                                                    <td className="px-4 py-3 text-right tabular-nums">
                                                        {location.available}
                                                    </td>
                                                    <td className="px-4 py-3 text-right font-medium tabular-nums">
                                                        {location.on_hand}
                                                    </td>
                                                    <td className="px-4 py-3 text-right">
                                                        {can(
                                                            'inventory.manage',
                                                        ) &&
                                                            variant.track_inventory && (
                                                                <AdjustStockDialog
                                                                    row={{
                                                                        id: variant.id,
                                                                        name: `${product.name} — ${variant.name}`,
                                                                        sku: variant.sku,
                                                                        on_hand:
                                                                            location.available,
                                                                    }}
                                                                    reasons={
                                                                        reasons
                                                                    }
                                                                />
                                                            )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                    <div className="border-t bg-muted/30 px-4 py-2">
                                        <Link
                                            href={urls.history}
                                            className="text-sm underline"
                                        >
                                            View adjustment history
                                        </Link>
                                    </div>
                                </div>
                            ) : (
                                <p className="mt-3 text-sm text-muted-foreground">
                                    Stock is not counted for this variant; it
                                    can always be sold.
                                </p>
                            )}
                            {data.track_inventory !==
                                variant.track_inventory && (
                                <p className="mt-2 text-xs text-muted-foreground">
                                    Save to apply the tracking change.
                                </p>
                            )}
                            <Disclosure
                                pills={[
                                    ['SKU', data.sku],
                                    ['Barcode', data.barcode],
                                    [
                                        'Sell when out of stock',
                                        data.allow_backorder ? 'On' : 'Off',
                                    ],
                                    [
                                        'Low stock alert',
                                        data.low_stock_threshold,
                                    ],
                                ]}
                                open={Boolean(
                                    errors.sku ||
                                    errors.barcode ||
                                    errors.low_stock_threshold,
                                )}
                            >
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field
                                        id="sku"
                                        label="SKU (stock keeping unit)"
                                        error={errors.sku}
                                    >
                                        <Input
                                            id="sku"
                                            value={data.sku}
                                            onChange={(e) =>
                                                setData('sku', e.target.value)
                                            }
                                            required
                                            maxLength={64}
                                        />
                                    </Field>
                                    <Field
                                        id="barcode"
                                        label="Barcode (ISBN, UPC, GTIN…)"
                                        error={errors.barcode}
                                    >
                                        <Input
                                            id="barcode"
                                            value={data.barcode}
                                            onChange={(e) =>
                                                setData(
                                                    'barcode',
                                                    e.target.value,
                                                )
                                            }
                                            maxLength={64}
                                        />
                                    </Field>
                                    <Field
                                        id="low_stock_threshold"
                                        label="Low stock alert at"
                                        error={errors.low_stock_threshold}
                                    >
                                        <Input
                                            id="low_stock_threshold"
                                            type="number"
                                            min={0}
                                            placeholder="Store default"
                                            value={data.low_stock_threshold}
                                            onChange={(e) =>
                                                setData(
                                                    'low_stock_threshold',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </Field>
                                    <label className="flex items-center gap-2 self-end pb-2 text-sm">
                                        <Checkbox
                                            checked={data.allow_backorder}
                                            onCheckedChange={(v) =>
                                                setData(
                                                    'allow_backorder',
                                                    v === true,
                                                )
                                            }
                                        />
                                        Sell when out of stock
                                    </label>
                                </div>
                            </Disclosure>
                        </Card>

                        <Card>
                            <h2 className="font-semibold">Shipping</h2>
                            <div className="mt-4 grid gap-4">
                                <div className="grid gap-1.5 sm:grid-cols-[12rem_1fr] sm:items-center">
                                    <Label>Packed product size</Label>
                                    <div className="flex items-center gap-2">
                                        {(
                                            [
                                                'length',
                                                'width',
                                                'height',
                                            ] as const
                                        ).map((side, i) => (
                                            <div
                                                key={side}
                                                className="flex flex-1 items-center gap-2"
                                            >
                                                {i > 0 && (
                                                    <span className="text-muted-foreground">
                                                        ×
                                                    </span>
                                                )}
                                                <Input
                                                    aria-label={`${side} (${units.dimension})`}
                                                    placeholder={`${side[0].toUpperCase()} 0.0`}
                                                    type="number"
                                                    min={0}
                                                    step="0.01"
                                                    value={data[side]}
                                                    onChange={(e) =>
                                                        setData(
                                                            side,
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </div>
                                        ))}
                                        <span className="text-sm text-muted-foreground">
                                            {units.dimension}
                                        </span>
                                    </div>
                                </div>
                                <InputError
                                    message={
                                        errors.length ??
                                        errors.width ??
                                        errors.height
                                    }
                                />
                                <div className="grid gap-1.5 sm:grid-cols-[12rem_1fr] sm:items-center">
                                    <Label htmlFor="weight">Weight</Label>
                                    <div className="flex items-center gap-2">
                                        <Input
                                            id="weight"
                                            type="number"
                                            min={0}
                                            step="0.001"
                                            value={data.weight}
                                            onChange={(e) =>
                                                setData(
                                                    'weight',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <span className="text-sm text-muted-foreground">
                                            {units.weight}
                                        </span>
                                    </div>
                                </div>
                                <InputError message={errors.weight} />
                            </div>
                        </Card>
                    </div>
                </div>
            </div>

            {(form.isDirty || form.hasErrors) && (
                <div className="fixed inset-x-0 bottom-0 z-40 border-t bg-background/95 px-4 py-3 backdrop-blur md:left-[var(--sidebar-width,0px)]">
                    <div className="mx-auto flex max-w-6xl items-center justify-between gap-3">
                        <p className="text-sm text-muted-foreground">
                            {form.hasErrors
                                ? 'Some fields need attention'
                                : 'Unsaved changes'}
                        </p>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                disabled={form.processing}
                                onClick={() => form.reset()}
                            >
                                Discard
                            </Button>
                            <Button disabled={form.processing} onClick={save}>
                                Save variant
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}

function VariantList({
    product,
    current,
    status,
}: {
    product: Props['product'];
    current: number;
    status: string;
}) {
    const [query, setQuery] = useState('');
    const [filters, setFilters] = useState<Record<string, string>>({});
    const shown = useMemo(
        () =>
            product.variants.filter(
                (v) =>
                    (query === '' ||
                        `${v.name} ${v.sku}`
                            .toLowerCase()
                            .includes(query.toLowerCase())) &&
                    Object.entries(filters).every(
                        ([name, value]) =>
                            value === '' || v.options[name] === value,
                    ),
            ),
        [product.variants, query, filters],
    );

    return (
        <aside className="overflow-hidden rounded-xl border bg-card lg:sticky lg:top-4">
            <div className="flex items-center gap-3 border-b p-4">
                <Thumb url={product.image_url} size="size-14" />
                <div className="grid gap-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <Link
                            href={product.edit_url}
                            className="font-medium hover:underline"
                        >
                            {product.name}
                        </Link>
                        <Badge
                            variant={
                                status === 'Active' ? 'default' : 'secondary'
                            }
                        >
                            {status}
                        </Badge>
                    </div>
                    <span className="text-sm text-muted-foreground">
                        {product.variants.length} variants
                    </span>
                </div>
            </div>
            {product.variants.length > 1 && (
                <div className="grid gap-2 border-b p-3">
                    <label className="relative">
                        <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <span className="sr-only">Search variants</span>
                        <Input
                            className="pl-9"
                            placeholder="Search variants"
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                        />
                    </label>
                    {product.options.length > 0 && (
                        <div className="flex flex-wrap gap-2">
                            {product.options.map((option) => (
                                <NativeSelect
                                    key={option.name}
                                    aria-label={option.name}
                                    className="h-8 w-auto rounded-full text-xs"
                                    value={filters[option.name] ?? ''}
                                    onChange={(e) =>
                                        setFilters({
                                            ...filters,
                                            [option.name]: e.target.value,
                                        })
                                    }
                                >
                                    <option value="">{option.name}</option>
                                    {option.values.map((value) => (
                                        <option key={value} value={value}>
                                            {value}
                                        </option>
                                    ))}
                                </NativeSelect>
                            ))}
                        </div>
                    )}
                </div>
            )}
            <p className="px-4 pt-3 pb-1 text-sm text-muted-foreground">
                {shown.length} {shown.length === 1 ? 'variant' : 'variants'}
            </p>
            <ul className="grid pb-2">
                {shown.map((v) => (
                    <li key={v.id}>
                        <button
                            type="button"
                            onClick={() =>
                                router.visit(v.url, { preserveScroll: true })
                            }
                            aria-current={v.id === current ? 'page' : undefined}
                            className={cn(
                                'flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm hover:bg-muted',
                                v.id === current && 'bg-muted font-medium',
                            )}
                        >
                            <Thumb url={v.image_url} size="size-8" />
                            <span className="min-w-0 flex-1 truncate">
                                {v.name}
                            </span>
                        </button>
                    </li>
                ))}
            </ul>
        </aside>
    );
}

function ImagePicker({
    images,
    value,
    onChange,
}: {
    images: Props['product']['images'];
    value: Props['product']['images'][number] | null;
    onChange: (id: number | null) => void;
}) {
    const [open, setOpen] = useState(false);

    return (
        <div className="relative">
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                className="flex size-24 items-center justify-center overflow-hidden rounded-lg border border-dashed bg-muted/30 hover:bg-muted"
                aria-label={
                    value ? 'Change variant image' : 'Choose variant image'
                }
            >
                {value ? (
                    <img
                        src={value.url}
                        alt={value.alt ?? ''}
                        className="size-full object-cover"
                    />
                ) : (
                    <ImagePlus className="size-5 text-muted-foreground" />
                )}
            </button>
            {open && (
                <div className="absolute top-full left-0 z-20 mt-2 grid w-72 grid-cols-4 gap-2 rounded-lg border bg-popover p-2 shadow-lg">
                    {images.length === 0 && (
                        <p className="col-span-4 p-2 text-xs text-muted-foreground">
                            Add images to the product first.
                        </p>
                    )}
                    {images.map((image) => (
                        <button
                            key={image.id}
                            type="button"
                            onClick={() => {
                                onChange(image.id);
                                setOpen(false);
                            }}
                            className={cn(
                                'aspect-square overflow-hidden rounded border hover:ring-2 hover:ring-primary',
                                value?.id === image.id && 'ring-2 ring-primary',
                            )}
                        >
                            <img
                                src={image.url}
                                alt={image.alt ?? ''}
                                className="size-full object-cover"
                            />
                        </button>
                    ))}
                    {value && (
                        <button
                            type="button"
                            className="col-span-4 rounded px-2 py-1 text-left text-xs text-destructive hover:bg-muted"
                            onClick={() => {
                                onChange(null);
                                setOpen(false);
                            }}
                        >
                            Remove image
                        </button>
                    )}
                </div>
            )}
        </div>
    );
}

function Disclosure({
    pills,
    children,
    open: forced = false,
}: {
    pills: [string, string | null | undefined][];
    children: ReactNode;
    open?: boolean;
}) {
    const [open, setOpen] = useState(false);
    const shown = open || forced;

    return (
        <div className="-mx-5 mt-4 -mb-5 border-t">
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                aria-expanded={shown}
                className="flex w-full items-center gap-2 px-5 py-3 text-left"
            >
                <span className="flex flex-1 flex-wrap gap-2">
                    {pills.map(([label, value]) => (
                        <span
                            key={label}
                            className="inline-flex items-center gap-1.5 rounded-md bg-muted px-2 py-1 text-sm"
                        >
                            {label}
                            {value !== undefined &&
                                value !== null &&
                                value !== '' && (
                                    <span className="rounded bg-background px-1.5 text-xs font-medium">
                                        {value}
                                    </span>
                                )}
                        </span>
                    ))}
                </span>
                <ChevronDown
                    className={cn(
                        'size-4 shrink-0 transition-transform',
                        shown && 'rotate-180',
                    )}
                />
            </button>
            {shown && <div className="px-5 pb-5">{children}</div>}
        </div>
    );
}

function Card({ children }: { children: ReactNode }) {
    return (
        <section className="rounded-xl border bg-card p-5">{children}</section>
    );
}

function Field({
    id,
    label,
    error,
    children,
}: {
    id: string;
    label: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-1.5">
            <Label htmlFor={id}>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}

function MoneyInput({
    id,
    label,
    currency,
    value,
    onChange,
    error,
}: {
    id: string;
    label: string;
    currency: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
}) {
    return (
        <Field id={id} label={label} error={error}>
            <div className="flex max-w-60 items-center rounded-md border focus-within:ring-2 focus-within:ring-ring/50">
                <span className="pl-3 text-sm text-muted-foreground">
                    {currency}
                </span>
                <input
                    id={id}
                    inputMode="decimal"
                    className="h-9 w-full bg-transparent px-2 text-sm outline-none"
                    value={value}
                    onChange={(e) =>
                        onChange(e.target.value.replace(/[^\d.]/g, ''))
                    }
                    placeholder="0.00"
                />
            </div>
        </Field>
    );
}

function Thumb({ url, size }: { url: string | null; size: string }) {
    return url ? (
        <img
            src={url}
            alt=""
            className={cn(size, 'shrink-0 rounded-md border object-cover')}
        />
    ) : (
        <span
            className={cn(
                size,
                'flex shrink-0 items-center justify-center rounded-md border bg-muted text-muted-foreground',
            )}
        >
            <ImageOff className="size-4" />
        </span>
    );
}
