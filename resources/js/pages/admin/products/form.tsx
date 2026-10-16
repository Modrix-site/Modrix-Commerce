import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Copy, ExternalLink, Languages } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import ProductController from '@/actions/App/Http/Controllers/Admin/Catalog/ProductController';
import { FormField, NativeSelect } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { ProductImages } from '@/components/admin/product-images';
import {
    SaveBar,
    useUnsavedChangesGuard,
} from '@/components/admin/products/save-bar';
import { DigitalFiles } from '@/components/admin/products/digital-files';
import { ProductPicker } from '@/components/admin/products/product-picker';
import type { PickerItem } from '@/components/admin/products/product-picker';
import { SectionNav } from '@/components/admin/products/section-nav';
import { TagsInput } from '@/components/admin/products/tags-input';
import { emptyVariant, toRow } from '@/components/admin/products/types';
import type {
    Errors,
    ProductFormData,
    ProductFormProps,
    VariantRow,
} from '@/components/admin/products/types';
import {
    ShippingFields,
    VariantFields,
} from '@/components/admin/products/variant-fields';
import { VariantsTable } from '@/components/admin/products/variants-table';
import { ExtensionSlot } from '@/components/extensions/extension-slot';
import type { SlotItem } from '@/components/extensions/extension-slot';
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
import { Textarea } from '@/components/ui/textarea';
import { useCan } from '@/hooks/use-can';
import { index } from '@/routes/admin/products';
import { edit as translationsEdit } from '@/routes/admin/products/translations';

/**
 * Inertia reuses this component when redirecting from create to edit, which
 * would keep stale useForm state (e.g. new variants without their ids).
 * Keying by product and variant ids forces a fresh form after each save.
 */
export default function ProductFormPage(props: ProductFormProps) {
    const key = props.product
        ? `${props.product.id}:${props.product.variants.map((v) => v.id).join(',')}`
        : 'new';

    return <ProductForm key={key} {...props} />;
}

const SECTION_FIELDS: Record<string, (key: string) => boolean> = {
    general: (k) => ['name', 'short_description', 'description'].includes(k),
    pricing: (k) =>
        k.startsWith('variants') && !/\.(weight|length|width|height)$/.test(k),
    options: (k) => k === 'option_attribute_ids',
    shipping: (k) => /^variants\.\d+\.(weight|length|width|height)$/.test(k),
    seo: (k) => ['slug', 'meta_title', 'meta_description'].includes(k),
};

function ProductForm({
    product,
    currency,
    weightUnit,
    dimensionUnit,
    categories,
    brands,
    tagSuggestions,
    statuses,
    types,
    attributes,
}: ProductFormProps) {
    const can = useCan();
    // Panels added by modules (e.g. Flash Sales); they save on their own.
    const { extensionPanels } = usePage<{ extensionPanels?: SlotItem[] }>()
        .props;
    const form = useForm<ProductFormData>({
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
        brand_id: product?.brand_id ? String(product.brand_id) : '',
        is_featured: product?.is_featured ?? false,
        is_digital: product?.is_digital ?? false,
        tags: product?.tags ?? [],
        related_ids: (product?.related ?? []).map((p) => p.id),
        upsell_ids: (product?.upsells ?? []).map((p) => p.id),
        option_attribute_ids: product?.option_attribute_ids ?? [],
        variants: product ? product.variants.map(toRow) : [emptyVariant()],
    });
    const { data, setData, processing, isDirty } = form;
    // Picked products shown in the pickers; the form sends their ids.
    const [related, setRelated] = useState<PickerItem[]>(
        product?.related ?? [],
    );
    const [upsells, setUpsells] = useState<PickerItem[]>(
        product?.upsells ?? [],
    );
    const errors = form.errors as Errors;
    const errorKeys = Object.keys(errors).filter((k) => errors[k]);
    const isVariable = data.type === 'variable';
    const selectedAttributes = attributes.filter((a) =>
        data.option_attribute_ids.includes(a.id),
    );
    const rows = isVariable ? data.variants : data.variants.slice(0, 1);

    useUnsavedChangesGuard(isDirty && !processing);

    const updateVariant = (i: number, patch: Partial<VariantRow>) =>
        setData(
            'variants',
            data.variants.map((v, j) => (j === i ? { ...v, ...patch } : v)),
        );

    const toggleAttribute = (attributeId: number, checked: boolean) =>
        setData(
            'option_attribute_ids',
            checked
                ? [...data.option_attribute_ids, attributeId].slice(0, 3)
                : data.option_attribute_ids.filter((id) => id !== attributeId),
        );

    const generateCombinations = () => {
        const lists = selectedAttributes.map((a) => a.values.map((v) => v.id));

        if (lists.some((l) => l.length === 0)) {
            return;
        }

        const combos = lists.reduce<number[][]>(
            (acc, values) => acc.flatMap((c) => values.map((v) => [...c, v])),
            [[]],
        );
        const key = (ids: number[]) => [...ids].sort((a, b) => a - b).join('-');
        const existing = new Set(
            data.variants.map((v) => key(v.option_value_ids)),
        );
        const base = data.variants[0];
        const additions = combos
            .filter((c) => !existing.has(key(c)))
            .map((c) => ({
                ...emptyVariant(),
                price: base?.price ?? '',
                cost: base?.cost ?? '',
                option_value_ids: c,
            }));
        setData('variants', [
            ...data.variants.filter(
                (v) => v.option_value_ids.length > 0 || v.id !== null,
            ),
            ...additions,
        ]);
    };

    const submit = () => {
        form.transform((d) => ({
            ...d,
            brand_id: d.brand_id === '' ? null : Number(d.brand_id),
            option_attribute_ids: isVariable ? d.option_attribute_ids : [],
            variants: (isVariable ? d.variants : d.variants.slice(0, 1)).map(
                (v) => ({
                    id: v.id,
                    sku: v.sku,
                    name: v.name || null,
                    price: v.price,
                    compare_at_price: v.compare_at_price || null,
                    cost: v.cost || null,
                    weight: v.weight || null,
                    length: v.length || null,
                    width: v.width || null,
                    height: v.height || null,
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
            {
                preserveScroll: true,
                // The saved values are the new baseline for "unsaved changes".
                onSuccess: () => form.setDefaults(),
            },
        );
    };

    const sectionHasError = (id: string) =>
        errorKeys.some((k) => SECTION_FIELDS[id]?.(k));
    const sections = [
        { id: 'general', label: 'General' },
        { id: 'media', label: 'Media' },
        ...(isVariable ? [{ id: 'options', label: 'Options' }] : []),
        {
            id: 'pricing',
            label: isVariable ? 'Variants' : 'Pricing & inventory',
        },
        data.is_digital
            ? { id: 'files', label: 'Digital files' }
            : { id: 'shipping', label: 'Shipping' },
        { id: 'related', label: 'Related products' },
        { id: 'seo', label: 'Search listing' },
    ].map((s) => ({ ...s, hasError: sectionHasError(s.id) }));

    return (
        <>
            <Head title={product ? `Edit ${product.name}` : 'Add product'} />
            <AdminPage>
                <PageHeader
                    title={product ? product.name || 'Untitled' : 'Add product'}
                    description={`Prices are in ${currency}.`}
                    actions={
                        product && (
                            <>
                                {product.view_url && (
                                    <Button variant="outline" asChild>
                                        <a
                                            href={product.view_url}
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            <ExternalLink /> View on store
                                        </a>
                                    </Button>
                                )}
                                <Button variant="outline" asChild>
                                    <Link href={translationsEdit(product.id)}>
                                        <Languages /> Translations
                                    </Link>
                                </Button>
                                {can('products.create') && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => {
                                            if (
                                                isDirty &&
                                                !window.confirm(
                                                    'You have unsaved changes. The copy is made from the last saved version. Continue?',
                                                )
                                            ) {
                                                return;
                                            }

                                            router.post(
                                                ProductController.duplicate.url(
                                                    product.id,
                                                ),
                                            );
                                        }}
                                    >
                                        <Copy /> Duplicate
                                    </Button>
                                )}
                                {can('products.delete') && (
                                    <DeleteProductDialog
                                        id={product.id}
                                        name={product.name}
                                    />
                                )}
                            </>
                        )
                    }
                />

                {/* A plain container, not a <form>: the image manager inside
                    has its own forms, and nested forms would submit this one. */}
                <div className="grid gap-6">
                    <SectionNav sections={sections} />

                    <div className="grid gap-6 lg:grid-cols-3">
                        <div className="grid min-w-0 content-start gap-6 lg:col-span-2">
                            <Section id="general" title="General">
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
                                    hint="Shown under the name and on product cards."
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
                                        rows={8}
                                        value={data.description}
                                        onChange={(e) =>
                                            setData(
                                                'description',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                            </Section>

                            <Section
                                id="media"
                                title="Media"
                                description={
                                    product
                                        ? 'Use the arrows to reorder. The first image is the main one.'
                                        : undefined
                                }
                            >
                                {product ? (
                                    <ProductImages
                                        productId={product.id}
                                        images={product.images}
                                    />
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        Save the product first, then add images
                                        here.
                                    </p>
                                )}
                            </Section>

                            {isVariable && (
                                <Section
                                    id="options"
                                    title="Options"
                                    description="Choose up to three options, such as Size and Color. Each variant needs one value per option."
                                >
                                    {attributes.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">
                                            No options defined yet. Create
                                            options under Catalog → Options.
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
                                                        onCheckedChange={(c) =>
                                                            toggleAttribute(
                                                                attribute.id,
                                                                c === true,
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
                                </Section>
                            )}

                            <Section
                                id="pricing"
                                title={
                                    isVariable
                                        ? `Variants (${data.variants.length})`
                                        : 'Pricing & inventory'
                                }
                            >
                                <InputError message={errors.variants} />
                                {isVariable ? (
                                    <VariantsTable
                                        variants={data.variants}
                                        attributes={selectedAttributes}
                                        currency={currency}
                                        errors={errors}
                                        onChange={updateVariant}
                                        onRemove={(i) =>
                                            setData(
                                                'variants',
                                                data.variants.filter(
                                                    (_, j) => j !== i,
                                                ),
                                            )
                                        }
                                        onAdd={() =>
                                            setData('variants', [
                                                ...data.variants,
                                                {
                                                    ...emptyVariant(),
                                                    price:
                                                        data.variants[0]
                                                            ?.price ?? '',
                                                },
                                            ])
                                        }
                                    />
                                ) : (
                                    <VariantFields
                                        index={0}
                                        variant={data.variants[0]}
                                        currency={currency}
                                        errors={errors}
                                        withBasics
                                        onChange={(patch) =>
                                            updateVariant(0, patch)
                                        }
                                    />
                                )}
                            </Section>

                            {data.is_digital ? (
                                <Section
                                    id="files"
                                    title="Digital files"
                                    description="What customers download after paying. Digital products are not shipped."
                                >
                                    {product ? (
                                        <DigitalFiles
                                            productId={product.id}
                                            files={product.files ?? []}
                                        />
                                    ) : (
                                        <p className="text-sm text-muted-foreground">
                                            Save the product first, then upload
                                            its files here.
                                        </p>
                                    )}
                                </Section>
                            ) : (
                                <Section
                                    id="shipping"
                                    title="Shipping"
                                    description="Weight and package size, used by shipping rates and packing."
                                >
                                    {rows.map((variant, i) => (
                                        <div key={variant.id ?? `new-${i}`}>
                                            {isVariable && (
                                                <p className="mb-2 text-sm font-medium">
                                                    {variant.sku ||
                                                        `Variant ${i + 1}`}
                                                </p>
                                            )}
                                            <ShippingFields
                                                index={i}
                                                variant={variant}
                                                errors={errors}
                                                weightUnit={weightUnit}
                                                dimensionUnit={dimensionUnit}
                                                onChange={(patch) =>
                                                    updateVariant(i, patch)
                                                }
                                            />
                                        </div>
                                    ))}
                                </Section>
                            )}

                            <Section
                                id="related"
                                title="Related products"
                                description="Upsells show as “Customers also bought”, related products as “You may also like”. Without related products, products from the same category are shown."
                            >
                                <ProductPicker
                                    id="upsell_ids"
                                    label="Upsells"
                                    value={upsells}
                                    max={12}
                                    excludeId={product?.id}
                                    searchUrl={ProductController.relatedSearch.url()}
                                    onChange={(items) => {
                                        setUpsells(items);
                                        setData(
                                            'upsell_ids',
                                            items.map((p) => p.id),
                                        );
                                    }}
                                />
                                <ProductPicker
                                    id="related_ids"
                                    label="Related products"
                                    value={related}
                                    max={12}
                                    excludeId={product?.id}
                                    searchUrl={ProductController.relatedSearch.url()}
                                    onChange={(items) => {
                                        setRelated(items);
                                        setData(
                                            'related_ids',
                                            items.map((p) => p.id),
                                        );
                                    }}
                                />
                                {(errors.related_ids || errors.upsell_ids) && (
                                    <p className="text-sm text-destructive">
                                        {errors.related_ids ??
                                            errors.upsell_ids}
                                    </p>
                                )}
                            </Section>

                            <Section
                                id="seo"
                                title="Search engine listing"
                                description="How the product appears in search results and shared links."
                            >
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
                                    hint={`${data.meta_title.length}/191 · the product name is used when empty.`}
                                >
                                    <Input
                                        id="meta_title"
                                        value={data.meta_title}
                                        maxLength={191}
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
                                    hint={`${data.meta_description.length}/500`}
                                >
                                    <Textarea
                                        id="meta_description"
                                        rows={2}
                                        maxLength={500}
                                        value={data.meta_description}
                                        onChange={(e) =>
                                            setData(
                                                'meta_description',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                            </Section>
                        </div>

                        <aside className="grid content-start gap-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle>
                                        Status &amp; visibility
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-4">
                                    {!product && (
                                        <FormField
                                            id="type"
                                            label="Product type"
                                            error={errors.type}
                                            hint="Variable products have options such as size or color."
                                        >
                                            <NativeSelect
                                                id="type"
                                                value={data.type}
                                                onChange={(e) =>
                                                    setData(
                                                        'type',
                                                        e.target.value,
                                                    )
                                                }
                                            >
                                                {types.map((t) => (
                                                    <option
                                                        key={t.value}
                                                        value={t.value}
                                                    >
                                                        {t.label}
                                                    </option>
                                                ))}
                                            </NativeSelect>
                                        </FormField>
                                    )}
                                    <FormField
                                        id="status"
                                        label="Status"
                                        error={errors.status}
                                    >
                                        <NativeSelect
                                            id="status"
                                            value={data.status}
                                            onChange={(e) =>
                                                setData(
                                                    'status',
                                                    e.target.value,
                                                )
                                            }
                                        >
                                            {statuses.map((s) => (
                                                <option
                                                    key={s.value}
                                                    value={s.value}
                                                >
                                                    {s.label}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                    </FormField>
                                    <FormField
                                        id="published_at"
                                        label="Publish at"
                                        error={errors.published_at}
                                        hint="Optional. Schedule a future date, or leave empty to publish now."
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
                                    <label className="flex items-start gap-2 text-sm">
                                        <Checkbox
                                            checked={data.is_featured}
                                            onCheckedChange={(c) =>
                                                setData(
                                                    'is_featured',
                                                    c === true,
                                                )
                                            }
                                            className="mt-0.5"
                                        />
                                        <span>
                                            Featured
                                            <span className="block text-xs text-muted-foreground">
                                                Can be shown in the home page
                                                “Featured products” section.
                                            </span>
                                        </span>
                                    </label>
                                    <label className="flex items-start gap-2 text-sm">
                                        <Checkbox
                                            checked={data.is_digital}
                                            onCheckedChange={(c) =>
                                                setData(
                                                    'is_digital',
                                                    c === true,
                                                )
                                            }
                                            className="mt-0.5"
                                        />
                                        <span>
                                            Digital product (no shipping)
                                            <span className="block text-xs text-muted-foreground">
                                                Customers download its files
                                                after paying.
                                            </span>
                                        </span>
                                    </label>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Organisation</CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-4">
                                    <FormField
                                        id="brand_id"
                                        label="Brand"
                                        error={errors.brand_id}
                                    >
                                        <NativeSelect
                                            id="brand_id"
                                            value={data.brand_id}
                                            onChange={(e) =>
                                                setData(
                                                    'brand_id',
                                                    e.target.value,
                                                )
                                            }
                                        >
                                            <option value="">No brand</option>
                                            {brands.map((b) => (
                                                <option key={b.id} value={b.id}>
                                                    {b.name}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                    </FormField>
                                    <FormField
                                        id="tags"
                                        label="Tags"
                                        error={
                                            errors.tags ??
                                            errorKeys
                                                .filter((k) =>
                                                    k.startsWith('tags.'),
                                                )
                                                .map((k) => errors[k])[0]
                                        }
                                        hint="Used for filtering and search. Not shown to shoppers."
                                    >
                                        <TagsInput
                                            id="tags"
                                            value={data.tags}
                                            onChange={(tags) =>
                                                setData('tags', tags)
                                            }
                                            suggestions={tagSuggestions}
                                        />
                                    </FormField>
                                    <div className="grid gap-2">
                                        <span className="text-sm font-medium">
                                            Categories
                                        </span>
                                        {categories.length === 0 ? (
                                            <p className="text-sm text-muted-foreground">
                                                No categories yet.
                                            </p>
                                        ) : (
                                            <div className="grid max-h-64 gap-2 overflow-y-auto rounded-md border p-3">
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
                                                                c,
                                                            ) =>
                                                                setData(
                                                                    'category_ids',
                                                                    c === true
                                                                        ? [
                                                                              ...data.category_ids,
                                                                              category.id,
                                                                          ]
                                                                        : data.category_ids.filter(
                                                                              (
                                                                                  id,
                                                                              ) =>
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
                                        <InputError
                                            message={errors.category_ids}
                                        />
                                    </div>
                                </CardContent>
                            </Card>
                            <ExtensionSlot
                                items={extensionPanels}
                                className="grid gap-6"
                            />
                        </aside>
                    </div>

                    <SaveBar
                        dirty={isDirty}
                        processing={processing}
                        isNew={!product}
                        errorCount={errorKeys.length}
                        onSave={submit}
                        onDiscard={() => {
                            form.reset();
                            form.clearErrors();
                        }}
                    />
                </div>
            </AdminPage>
        </>
    );
}

function Section({
    id,
    title,
    description,
    children,
}: {
    id: string;
    title: string;
    description?: string;
    children: ReactNode;
}) {
    return (
        <Card id={id} className="scroll-mt-16">
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                {description && (
                    <CardDescription>{description}</CardDescription>
                )}
            </CardHeader>
            <CardContent className="grid gap-4">{children}</CardContent>
        </Card>
    );
}

function DeleteProductDialog({ id, name }: { id: number; name: string }) {
    const form = useForm({});

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="destructive">Delete</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Delete “{name}”?</DialogTitle>
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
                            form.submit(ProductController.destroy(id))
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
