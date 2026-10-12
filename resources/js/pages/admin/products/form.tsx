import { Head, Link, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import ProductController from '@/actions/App/Http/Controllers/Admin/Catalog/ProductController';
import { FormField } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { ProductImages } from '@/components/admin/product-images';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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
import { Textarea } from '@/components/ui/textarea';
import { useCan } from '@/hooks/use-can';
import { index } from '@/routes/admin/products';
import type { CategoryOption, Option } from '@/types';

type AttributeOption = {
    id: number;
    name: string;
    values: { id: number; value: string }[];
};

type VariantData = {
    id?: number;
    sku: string;
    name: string | null;
    price: string;
    compare_at_price: string;
    weight: string | null;
    barcode: string | null;
    track_inventory: boolean;
    allow_backorder: boolean;
    is_active: boolean;
    low_stock_threshold: number | null;
    option_value_ids: number[];
    on_hand?: number | null;
};

type ProductData = {
    id: number;
    type: string;
    name: string;
    slug: string;
    status: string;
    published_at: string | null;
    short_description: string | null;
    description: string | null;
    meta_title: string | null;
    meta_description: string | null;
    category_ids: number[];
    option_attribute_ids: number[];
    variants: VariantData[];
    images: { id: number; url: string; alt: string | null }[];
};

type Props = {
    product: ProductData | null;
    currency: string;
    categories: CategoryOption[];
    statuses: Option[];
    types: Option[];
    attributes: AttributeOption[];
};

type VariantRow = {
    id: number | null;
    sku: string;
    name: string;
    price: string;
    compare_at_price: string;
    weight: string;
    barcode: string;
    track_inventory: boolean;
    allow_backorder: boolean;
    is_active: boolean;
    low_stock_threshold: string;
    option_value_ids: number[];
    initial_stock: string;
    on_hand: number | null;
};

type FormData = {
    type: string;
    name: string;
    slug: string;
    status: string;
    published_at: string;
    short_description: string;
    description: string;
    meta_title: string;
    meta_description: string;
    category_ids: number[];
    option_attribute_ids: number[];
    variants: VariantRow[];
};

const emptyVariant = (): VariantRow => ({
    id: null,
    sku: '',
    name: '',
    price: '',
    compare_at_price: '',
    weight: '',
    barcode: '',
    track_inventory: true,
    allow_backorder: false,
    is_active: true,
    low_stock_threshold: '',
    option_value_ids: [],
    initial_stock: '0',
    on_hand: null,
});

function toRow(v: VariantData): VariantRow {
    return {
        id: v.id ?? null,
        sku: v.sku,
        name: v.name ?? '',
        price: v.price,
        compare_at_price: v.compare_at_price ?? '',
        weight: v.weight ?? '',
        barcode: v.barcode ?? '',
        track_inventory: v.track_inventory,
        allow_backorder: v.allow_backorder,
        is_active: v.is_active,
        low_stock_threshold:
            v.low_stock_threshold === null ? '' : String(v.low_stock_threshold),
        option_value_ids: v.option_value_ids,
        initial_stock: '0',
        on_hand: v.on_hand ?? null,
    };
}

/**
 * Inertia reuses this component when redirecting from create to edit, which
 * would keep stale useForm state (e.g. new variants without their ids).
 * Keying by product and variant ids forces a fresh form after each save.
 */
export default function ProductFormPage(props: Props) {
    const key = props.product
        ? `${props.product.id}:${props.product.variants.map((v) => v.id).join(',')}`
        : 'new';

    return <ProductForm key={key} {...props} />;
}

function ProductForm({
    product,
    currency,
    categories,
    statuses,
    types,
    attributes,
}: Props) {
    const can = useCan();
    const form = useForm<FormData>({
        type: product?.type ?? 'simple',
        name: product?.name ?? '',
        slug: product?.slug ?? '',
        status: product?.status ?? 'draft',
        published_at: product?.published_at ?? '',
        short_description: product?.short_description ?? '',
        description: product?.description ?? '',
        meta_title: product?.meta_title ?? '',
        meta_description: product?.meta_description ?? '',
        category_ids: product?.category_ids ?? [],
        option_attribute_ids: product?.option_attribute_ids ?? [],
        variants: product ? product.variants.map(toRow) : [emptyVariant()],
    });
    const { data, setData, processing } = form;
    const errors = form.errors as Record<string, string | undefined>;
    const isVariable = data.type === 'variable';
    const selectedAttributes = attributes.filter((a) =>
        data.option_attribute_ids.includes(a.id),
    );

    const updateVariant = (index: number, patch: Partial<VariantRow>) => {
        setData(
            'variants',
            data.variants.map((v, i) => (i === index ? { ...v, ...patch } : v)),
        );
    };

    const setOptionValue = (
        index: number,
        attributeId: number,
        valueId: number,
    ) => {
        const attribute = attributes.find((a) => a.id === attributeId);
        const otherValueIds = new Set(attribute?.values.map((v) => v.id) ?? []);
        const current = data.variants[index].option_value_ids.filter(
            (id) => !otherValueIds.has(id),
        );
        updateVariant(index, {
            option_value_ids: valueId ? [...current, valueId] : current,
        });
    };

    const toggleAttribute = (attributeId: number, checked: boolean) => {
        const ids = checked
            ? [...data.option_attribute_ids, attributeId].slice(0, 3)
            : data.option_attribute_ids.filter((id) => id !== attributeId);
        setData('option_attribute_ids', ids);
    };

    const generateCombinations = () => {
        const lists = selectedAttributes.map((a) => a.values.map((v) => v.id));

        if (lists.some((l) => l.length === 0)) {
            return;
        }

        const combos = lists.reduce<number[][]>(
            (acc, values) => acc.flatMap((c) => values.map((v) => [...c, v])),
            [[]],
        );
        const existing = new Set(
            data.variants.map((v) =>
                [...v.option_value_ids].sort((a, b) => a - b).join('-'),
            ),
        );
        const base = data.variants[0];
        const additions = combos
            .filter(
                (c) => !existing.has([...c].sort((a, b) => a - b).join('-')),
            )
            .map((c) => ({
                ...emptyVariant(),
                price: base?.price ?? '',
                option_value_ids: c,
            }));
        const kept = data.variants.filter(
            (v) => v.option_value_ids.length > 0 || v.id !== null,
        );
        setData('variants', [...kept, ...additions]);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((d) => ({
            ...d,
            option_attribute_ids: isVariable ? d.option_attribute_ids : [],
            variants: (isVariable ? d.variants : d.variants.slice(0, 1)).map(
                (v) => ({
                    id: v.id,
                    sku: v.sku,
                    name: v.name || null,
                    price: v.price,
                    compare_at_price: v.compare_at_price || null,
                    weight: v.weight || null,
                    barcode: v.barcode || null,
                    track_inventory: v.track_inventory,
                    allow_backorder: v.allow_backorder,
                    is_active: v.is_active,
                    low_stock_threshold:
                        v.low_stock_threshold === ''
                            ? null
                            : Number(v.low_stock_threshold),
                    option_value_ids: isVariable ? v.option_value_ids : [],
                    initial_stock:
                        v.id === null ? Number(v.initial_stock || 0) : null,
                }),
            ),
        }));
        form.submit(
            product
                ? ProductController.update(product.id)
                : ProductController.store(),
            { preserveScroll: true },
        );
    };

    const variantRows = isVariable ? data.variants : data.variants.slice(0, 1);

    return (
        <>
            <Head title={product ? `Edit ${product.name}` : 'Add product'} />
            <AdminPage>
                <PageHeader
                    title={product ? product.name : 'Add product'}
                    description={`Prices are in ${currency}.`}
                    actions={
                        product &&
                        can('products.delete') && (
                            <DeleteProductDialog product={product} />
                        )
                    }
                />

                <form onSubmit={submit} className="grid gap-6 lg:grid-cols-3">
                    <div className="grid gap-6 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>General</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <FormField
                                    id="name"
                                    label="Name"
                                    error={errors.name}
                                >
                                    <Input
                                        id="name"
                                        value={data.name}
                                        onChange={(e) =>
                                            setData('name', e.target.value)
                                        }
                                        required
                                    />
                                </FormField>
                                <FormField
                                    id="short_description"
                                    label="Short description"
                                    error={errors.short_description}
                                >
                                    <Input
                                        id="short_description"
                                        value={data.short_description}
                                        onChange={(e) =>
                                            setData(
                                                'short_description',
                                                e.target.value,
                                            )
                                        }
                                        maxLength={500}
                                    />
                                </FormField>
                                <FormField
                                    id="description"
                                    label="Description"
                                    error={errors.description}
                                    hint="Plain text. Line breaks are preserved on the storefront."
                                >
                                    <Textarea
                                        id="description"
                                        rows={6}
                                        value={data.description}
                                        onChange={(e) =>
                                            setData(
                                                'description',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                            </CardContent>
                        </Card>

                        {isVariable && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Options</CardTitle>
                                    <CardDescription>
                                        Choose up to three options. Each variant
                                        needs one value per option.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-3">
                                    {attributes.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">
                                            No options defined yet. Create
                                            options such as Size or Color under
                                            Catalog → Options.
                                        </p>
                                    ) : (
                                        <div className="flex flex-wrap gap-4">
                                            {attributes.map((attribute) => (
                                                <label
                                                    key={attribute.id}
                                                    className="flex items-center gap-2 text-sm"
                                                >
                                                    <Checkbox
                                                        checked={data.option_attribute_ids.includes(
                                                            attribute.id,
                                                        )}
                                                        onCheckedChange={(
                                                            checked,
                                                        ) =>
                                                            toggleAttribute(
                                                                attribute.id,
                                                                checked ===
                                                                    true,
                                                            )
                                                        }
                                                    />
                                                    {attribute.name}
                                                </label>
                                            ))}
                                        </div>
                                    )}
                                    <InputError
                                        message={errors.option_attribute_ids}
                                    />
                                    {selectedAttributes.length > 0 && (
                                        <div>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={generateCombinations}
                                            >
                                                Add all combinations
                                            </Button>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        )}

                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    {isVariable
                                        ? 'Variants'
                                        : 'Pricing and inventory'}
                                </CardTitle>
                                <CardDescription>
                                    Stock for existing variants is changed from
                                    the Inventory page so every change is
                                    recorded.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <InputError message={errors.variants} />
                                {variantRows.map((variant, i) => (
                                    <fieldset
                                        key={variant.id ?? `new-${i}`}
                                        className="grid gap-3 rounded-lg border p-4"
                                    >
                                        <legend className="sr-only">
                                            Variant {i + 1}
                                        </legend>
                                        {isVariable && (
                                            <div className="flex flex-wrap items-end gap-3">
                                                {selectedAttributes.map(
                                                    (attribute) => {
                                                        const selected =
                                                            attribute.values.find(
                                                                (v) =>
                                                                    variant.option_value_ids.includes(
                                                                        v.id,
                                                                    ),
                                                            );

                                                        return (
                                                            <div
                                                                key={
                                                                    attribute.id
                                                                }
                                                                className="grid gap-1"
                                                            >
                                                                <Label
                                                                    htmlFor={`v${i}-a${attribute.id}`}
                                                                >
                                                                    {
                                                                        attribute.name
                                                                    }
                                                                </Label>
                                                                <select
                                                                    id={`v${i}-a${attribute.id}`}
                                                                    value={
                                                                        selected?.id ??
                                                                        ''
                                                                    }
                                                                    onChange={(
                                                                        e,
                                                                    ) =>
                                                                        setOptionValue(
                                                                            i,
                                                                            attribute.id,
                                                                            Number(
                                                                                e
                                                                                    .target
                                                                                    .value,
                                                                            ),
                                                                        )
                                                                    }
                                                                    className="h-9 rounded-md border border-input bg-transparent px-2 text-sm dark:bg-input/30"
                                                                >
                                                                    <option value="">
                                                                        Choose…
                                                                    </option>
                                                                    {attribute.values.map(
                                                                        (v) => (
                                                                            <option
                                                                                key={
                                                                                    v.id
                                                                                }
                                                                                value={
                                                                                    v.id
                                                                                }
                                                                            >
                                                                                {
                                                                                    v.value
                                                                                }
                                                                            </option>
                                                                        ),
                                                                    )}
                                                                </select>
                                                            </div>
                                                        );
                                                    },
                                                )}
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    className="ml-auto"
                                                    disabled={
                                                        data.variants.length ===
                                                        1
                                                    }
                                                    onClick={() =>
                                                        setData(
                                                            'variants',
                                                            data.variants.filter(
                                                                (_, j) =>
                                                                    j !== i,
                                                            ),
                                                        )
                                                    }
                                                    aria-label={`Remove variant ${i + 1}`}
                                                >
                                                    <Trash2 />
                                                </Button>
                                                <InputError
                                                    className="w-full"
                                                    message={
                                                        errors[
                                                            `variants.${i}.option_value_ids`
                                                        ]
                                                    }
                                                />
                                            </div>
                                        )}
                                        <div className="grid gap-3 sm:grid-cols-4">
                                            <FormField
                                                id={`v${i}-sku`}
                                                label="SKU"
                                                error={
                                                    errors[`variants.${i}.sku`]
                                                }
                                            >
                                                <Input
                                                    id={`v${i}-sku`}
                                                    value={variant.sku}
                                                    onChange={(e) =>
                                                        updateVariant(i, {
                                                            sku: e.target.value,
                                                        })
                                                    }
                                                    required
                                                />
                                            </FormField>
                                            <FormField
                                                id={`v${i}-price`}
                                                label={`Price (${currency})`}
                                                error={
                                                    errors[
                                                        `variants.${i}.price`
                                                    ]
                                                }
                                            >
                                                <Input
                                                    id={`v${i}-price`}
                                                    inputMode="decimal"
                                                    value={variant.price}
                                                    onChange={(e) =>
                                                        updateVariant(i, {
                                                            price: e.target
                                                                .value,
                                                        })
                                                    }
                                                    placeholder="0.00"
                                                    required
                                                />
                                            </FormField>
                                            <FormField
                                                id={`v${i}-compare`}
                                                label="Compare-at price"
                                                error={
                                                    errors[
                                                        `variants.${i}.compare_at_price`
                                                    ]
                                                }
                                            >
                                                <Input
                                                    id={`v${i}-compare`}
                                                    inputMode="decimal"
                                                    value={
                                                        variant.compare_at_price
                                                    }
                                                    onChange={(e) =>
                                                        updateVariant(i, {
                                                            compare_at_price:
                                                                e.target.value,
                                                        })
                                                    }
                                                    placeholder="Optional"
                                                />
                                            </FormField>
                                            {variant.id === null ? (
                                                <FormField
                                                    id={`v${i}-stock`}
                                                    label="Initial stock"
                                                    error={
                                                        errors[
                                                            `variants.${i}.initial_stock`
                                                        ]
                                                    }
                                                >
                                                    <Input
                                                        id={`v${i}-stock`}
                                                        type="number"
                                                        min={0}
                                                        value={
                                                            variant.initial_stock
                                                        }
                                                        disabled={
                                                            !variant.track_inventory
                                                        }
                                                        onChange={(e) =>
                                                            updateVariant(i, {
                                                                initial_stock:
                                                                    e.target
                                                                        .value,
                                                            })
                                                        }
                                                    />
                                                </FormField>
                                            ) : (
                                                <div className="grid gap-2">
                                                    <span className="text-sm font-medium">
                                                        On hand
                                                    </span>
                                                    <span className="py-2 text-sm tabular-nums">
                                                        {variant.on_hand ??
                                                            'Not tracked'}
                                                    </span>
                                                </div>
                                            )}
                                        </div>
                                        <div className="grid gap-3 sm:grid-cols-4">
                                            <FormField
                                                id={`v${i}-weight`}
                                                label="Weight"
                                                error={
                                                    errors[
                                                        `variants.${i}.weight`
                                                    ]
                                                }
                                            >
                                                <Input
                                                    id={`v${i}-weight`}
                                                    inputMode="decimal"
                                                    value={variant.weight}
                                                    onChange={(e) =>
                                                        updateVariant(i, {
                                                            weight: e.target
                                                                .value,
                                                        })
                                                    }
                                                />
                                            </FormField>
                                            <FormField
                                                id={`v${i}-barcode`}
                                                label="Barcode"
                                                error={
                                                    errors[
                                                        `variants.${i}.barcode`
                                                    ]
                                                }
                                            >
                                                <Input
                                                    id={`v${i}-barcode`}
                                                    value={variant.barcode}
                                                    onChange={(e) =>
                                                        updateVariant(i, {
                                                            barcode:
                                                                e.target.value,
                                                        })
                                                    }
                                                />
                                            </FormField>
                                            <FormField
                                                id={`v${i}-low`}
                                                label="Low-stock alert at"
                                                error={
                                                    errors[
                                                        `variants.${i}.low_stock_threshold`
                                                    ]
                                                }
                                            >
                                                <Input
                                                    id={`v${i}-low`}
                                                    type="number"
                                                    min={0}
                                                    value={
                                                        variant.low_stock_threshold
                                                    }
                                                    placeholder="Store default"
                                                    onChange={(e) =>
                                                        updateVariant(i, {
                                                            low_stock_threshold:
                                                                e.target.value,
                                                        })
                                                    }
                                                />
                                            </FormField>
                                        </div>
                                        <div className="flex flex-wrap gap-5 text-sm">
                                            <label className="flex items-center gap-2">
                                                <Checkbox
                                                    checked={
                                                        variant.track_inventory
                                                    }
                                                    onCheckedChange={(c) =>
                                                        updateVariant(i, {
                                                            track_inventory:
                                                                c === true,
                                                        })
                                                    }
                                                />
                                                Track inventory
                                            </label>
                                            <label className="flex items-center gap-2">
                                                <Checkbox
                                                    checked={
                                                        variant.allow_backorder
                                                    }
                                                    disabled={
                                                        !variant.track_inventory
                                                    }
                                                    onCheckedChange={(c) =>
                                                        updateVariant(i, {
                                                            allow_backorder:
                                                                c === true,
                                                        })
                                                    }
                                                />
                                                Allow backorders (sell when out
                                                of stock)
                                            </label>
                                            <label className="flex items-center gap-2">
                                                <Checkbox
                                                    checked={variant.is_active}
                                                    onCheckedChange={(c) =>
                                                        updateVariant(i, {
                                                            is_active:
                                                                c === true,
                                                        })
                                                    }
                                                />
                                                Available for sale
                                            </label>
                                        </div>
                                    </fieldset>
                                ))}
                                {isVariable && (
                                    <div>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                setData('variants', [
                                                    ...data.variants,
                                                    emptyVariant(),
                                                ])
                                            }
                                        >
                                            <Plus /> Add variant
                                        </Button>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Search engine listing</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <FormField
                                    id="slug"
                                    label="URL slug"
                                    error={errors.slug}
                                    hint="Lowercase letters, numbers and hyphens. Generated from the name when empty."
                                >
                                    <Input
                                        id="slug"
                                        value={data.slug}
                                        onChange={(e) =>
                                            setData('slug', e.target.value)
                                        }
                                    />
                                </FormField>
                                <FormField
                                    id="meta_title"
                                    label="Meta title"
                                    error={errors.meta_title}
                                >
                                    <Input
                                        id="meta_title"
                                        value={data.meta_title}
                                        onChange={(e) =>
                                            setData(
                                                'meta_title',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                                <FormField
                                    id="meta_description"
                                    label="Meta description"
                                    error={errors.meta_description}
                                >
                                    <Textarea
                                        id="meta_description"
                                        rows={2}
                                        value={data.meta_description}
                                        onChange={(e) =>
                                            setData(
                                                'meta_description',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="grid content-start gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Status</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                {!product && (
                                    <FormField
                                        id="type"
                                        label="Product type"
                                        error={errors.type}
                                    >
                                        <select
                                            id="type"
                                            value={data.type}
                                            onChange={(e) =>
                                                setData('type', e.target.value)
                                            }
                                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm dark:bg-input/30"
                                        >
                                            {types.map((t) => (
                                                <option
                                                    key={t.value}
                                                    value={t.value}
                                                >
                                                    {t.label}
                                                </option>
                                            ))}
                                        </select>
                                    </FormField>
                                )}
                                <FormField
                                    id="status"
                                    label="Status"
                                    error={errors.status}
                                >
                                    <select
                                        id="status"
                                        value={data.status}
                                        onChange={(e) =>
                                            setData('status', e.target.value)
                                        }
                                        className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm dark:bg-input/30"
                                    >
                                        {statuses.map((s) => (
                                            <option
                                                key={s.value}
                                                value={s.value}
                                            >
                                                {s.label}
                                            </option>
                                        ))}
                                    </select>
                                </FormField>
                                <FormField
                                    id="published_at"
                                    label="Publish at"
                                    error={errors.published_at}
                                    hint="Optional. Leave empty to publish immediately."
                                >
                                    <Input
                                        id="published_at"
                                        type="datetime-local"
                                        value={data.published_at}
                                        onChange={(e) =>
                                            setData(
                                                'published_at',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Categories</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {categories.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No categories yet.
                                    </p>
                                ) : (
                                    <div className="grid max-h-72 gap-2 overflow-y-auto">
                                        {categories.map((category) => (
                                            <label
                                                key={category.id}
                                                className="flex items-center gap-2 text-sm"
                                                style={{
                                                    paddingLeft: `${category.depth * 1.25}rem`,
                                                }}
                                            >
                                                <Checkbox
                                                    checked={data.category_ids.includes(
                                                        category.id,
                                                    )}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        setData(
                                                            'category_ids',
                                                            checked === true
                                                                ? [
                                                                      ...data.category_ids,
                                                                      category.id,
                                                                  ]
                                                                : data.category_ids.filter(
                                                                      (id) =>
                                                                          id !==
                                                                          category.id,
                                                                  ),
                                                        )
                                                    }
                                                />
                                                {category.name}
                                            </label>
                                        ))}
                                    </div>
                                )}
                                <InputError message={errors.category_ids} />
                            </CardContent>
                        </Card>

                        <div className="flex gap-2">
                            <Button type="submit" disabled={processing}>
                                {product ? 'Save product' : 'Create product'}
                            </Button>
                            <Button variant="ghost" asChild>
                                <Link href={index()}>Cancel</Link>
                            </Button>
                        </div>
                    </div>
                </form>

                {product ? (
                    <ProductImages
                        productId={product.id}
                        images={product.images}
                    />
                ) : (
                    <p className="text-sm text-muted-foreground">
                        You can add images after creating the product.
                    </p>
                )}
            </AdminPage>
        </>
    );
}

function DeleteProductDialog({ product }: { product: ProductData }) {
    const form = useForm({});

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="destructive">Delete</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Delete “{product.name}”?</DialogTitle>
                <DialogDescription>
                    The product is removed from the catalog and storefront.
                    Existing orders keep their own copy of the product details.
                </DialogDescription>
                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>
                    <Button
                        variant="destructive"
                        disabled={form.processing}
                        onClick={() =>
                            form.submit(ProductController.destroy(product.id))
                        }
                    >
                        Delete product
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

ProductFormPage.layout = {
    breadcrumbs: [{ title: 'Products', href: index() }],
};
