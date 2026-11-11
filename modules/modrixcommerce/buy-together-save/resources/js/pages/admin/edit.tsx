import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import { FormField } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type ProductOption = {
    id: number;
    name: string;
    sku: string | null;
};

type CategoryOption = {
    id: number;
    name: string;
};

type Deal = {
    id: number;
    title: string;
    badge_text: string | null;
    description: string | null;
    trigger_type: 'product' | 'category' | 'all';
    trigger_ids: number[];
    bundle_product_ids: number[];
    discount_type: 'percent' | 'fixed';
    discount_value: string | number;
    min_items_required: number;
    priority: number;
    is_active: boolean;
    starts_at: string | null;
    ends_at: string | null;
};

type Props = {
    deal: Deal | null;
    products: ProductOption[];
    categories: CategoryOption[];
    actionUrl: string;
};

export default function BuyTogetherEdit({
    deal,
    products,
    categories,
    actionUrl,
}: Props) {
    const isEdit = deal !== null;

    const form = useForm({
        _method: isEdit ? 'PUT' : 'POST',
        title: deal?.title ?? '',
        badge_text: deal?.badge_text ?? '',
        description: deal?.description ?? '',
        trigger_type: deal?.trigger_type ?? 'product',
        trigger_ids: deal?.trigger_ids ?? [],
        bundle_product_ids: deal?.bundle_product_ids ?? [],
        discount_type: deal?.discount_type ?? 'percent',
        discount_value: deal ? parseFloat(String(deal.discount_value)) : 10,
        min_items_required: deal?.min_items_required ?? 2,
        priority: deal?.priority ?? 0,
        is_active: deal?.is_active ?? true,
        starts_at: deal?.starts_at ? deal.starts_at.slice(0, 10) : '',
        ends_at: deal?.ends_at ? deal.ends_at.slice(0, 10) : '',
    });

    const toggleBundleProduct = (productId: number) => {
        const current = [...form.data.bundle_product_ids];
        if (current.includes(productId)) {
            form.setData(
                'bundle_product_ids',
                current.filter((id) => id !== productId),
            );
        } else {
            form.setData('bundle_product_ids', [...current, productId]);
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isEdit) {
            form.put(actionUrl);
        } else {
            form.post(actionUrl);
        }
    };

    return (
        <>
            <Head
                title={
                    isEdit
                        ? `Edit Deal: ${deal.title}`
                        : 'New Buy Together & Save Deal'
                }
            />

            <AdminPage>
                <PageHeader
                    title={
                        isEdit
                            ? `Edit Deal: ${deal.title}`
                            : 'Create Bundle Deal'
                    }
                    description="Configure products and discount offers for this Buy Together & Save combo."
                    actions={
                        <Link href="/admin/m/modrixcommerce/buy-together-save">
                            <Button variant="outline" size="sm">
                                <ArrowLeft className="mr-2 size-4" />
                                Back to Deals
                            </Button>
                        </Link>
                    }
                />

                <form onSubmit={handleSubmit} className="grid gap-6">
                    {/* Deal Basics */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Deal Information</CardTitle>
                            <CardDescription>
                                Set the title, marketing badge, and status for
                                this bundle.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-2">
                            <FormField
                                id="title"
                                label="Deal Title"
                                error={form.errors.title}
                                hint="Internal and promotional title for this bundle deal."
                            >
                                <Input
                                    id="title"
                                    value={form.data.title}
                                    onChange={(e) =>
                                        form.setData('title', e.target.value)
                                    }
                                    placeholder="e.g. Camera + Lens Starter Combo"
                                    required
                                />
                            </FormField>

                            <FormField
                                id="badge_text"
                                label="Promotional Badge Text"
                                error={form.errors.badge_text}
                                hint="Displayed as a pill above the widget (e.g. Save 15%, Bundle Deal, Best Value)."
                            >
                                <Input
                                    id="badge_text"
                                    value={form.data.badge_text}
                                    onChange={(e) =>
                                        form.setData(
                                            'badge_text',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="e.g. Save 15%"
                                />
                            </FormField>

                            <div className="flex items-center space-x-2 md:col-span-2">
                                <Checkbox
                                    id="is_active"
                                    checked={form.data.is_active}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'is_active',
                                            Boolean(checked),
                                        )
                                    }
                                />
                                <label
                                    htmlFor="is_active"
                                    className="text-sm leading-none font-medium"
                                >
                                    Enable this deal on product pages
                                </label>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Trigger Rules */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Trigger Target</CardTitle>
                            <CardDescription>
                                Choose which product pages display this bundle
                                deal.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-2">
                            <FormField
                                id="trigger_type"
                                label="Trigger Type"
                                error={form.errors.trigger_type}
                            >
                                <Select
                                    value={form.data.trigger_type}
                                    onValueChange={(val: string) =>
                                        form.setData(
                                            'trigger_type',
                                            val as
                                                | 'product'
                                                | 'category'
                                                | 'all',
                                        )
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select trigger type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="product">
                                            Specific Product
                                        </SelectItem>
                                        <SelectItem value="category">
                                            Category
                                        </SelectItem>
                                        <SelectItem value="all">
                                            All Products
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormField>

                            {form.data.trigger_type === 'product' && (
                                <FormField
                                    id="trigger_ids"
                                    label="Target Product"
                                    error={form.errors.trigger_ids}
                                    hint="Select the product page where this offer is shown."
                                >
                                    <Select
                                        value={
                                            form.data.trigger_ids[0]?.toString() ??
                                            ''
                                        }
                                        onValueChange={(val) =>
                                            form.setData('trigger_ids', [
                                                parseInt(val, 10),
                                            ])
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Choose a product" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {products.map((p) => (
                                                <SelectItem
                                                    key={p.id}
                                                    value={p.id.toString()}
                                                >
                                                    {p.name}{' '}
                                                    {p.sku ? `(${p.sku})` : ''}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </FormField>
                            )}

                            {form.data.trigger_type === 'category' && (
                                <FormField
                                    id="trigger_ids"
                                    label="Target Category"
                                    error={form.errors.trigger_ids}
                                    hint="Select the category whose products will show this offer."
                                >
                                    <Select
                                        value={
                                            form.data.trigger_ids[0]?.toString() ??
                                            ''
                                        }
                                        onValueChange={(val) =>
                                            form.setData('trigger_ids', [
                                                parseInt(val, 10),
                                            ])
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Choose a category" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {categories.map((c) => (
                                                <SelectItem
                                                    key={c.id}
                                                    value={c.id.toString()}
                                                >
                                                    {c.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </FormField>
                            )}
                        </CardContent>
                    </Card>

                    {/* Companion Bundle Items */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Bundle Companion Products</CardTitle>
                            <CardDescription>
                                Select companion products to bundle together
                                with the main product (select 1 to 5 items).
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="max-h-60 space-y-2 overflow-y-auto rounded-md border p-3">
                                {products.map((p) => {
                                    const isSelected =
                                        form.data.bundle_product_ids.includes(
                                            p.id,
                                        );

                                    return (
                                        <div
                                            key={p.id}
                                            className="flex cursor-pointer items-center space-x-2 rounded-sm p-1.5 hover:bg-muted/50"
                                            onClick={() =>
                                                toggleBundleProduct(p.id)
                                            }
                                        >
                                            <Checkbox
                                                id={`prod-${p.id}`}
                                                checked={isSelected}
                                                onCheckedChange={() =>
                                                    toggleBundleProduct(p.id)
                                                }
                                            />
                                            <label
                                                htmlFor={`prod-${p.id}`}
                                                className="cursor-pointer text-sm leading-none font-medium"
                                            >
                                                {p.name}{' '}
                                                {p.sku ? `(${p.sku})` : ''}
                                            </label>
                                        </div>
                                    );
                                })}
                            </div>
                            {form.errors.bundle_product_ids && (
                                <p className="mt-1 text-xs text-destructive">
                                    {form.errors.bundle_product_ids}
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    {/* Discount & Savings Offer */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Bundle Savings Offer</CardTitle>
                            <CardDescription>
                                Set the discount type and amount applied when
                                buying the items together.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-2">
                            <FormField
                                id="discount_type"
                                label="Discount Type"
                                error={form.errors.discount_type}
                            >
                                <Select
                                    value={form.data.discount_type}
                                    onValueChange={(val: string) =>
                                        form.setData(
                                            'discount_type',
                                            val as 'percent' | 'fixed',
                                        )
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Discount Type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="percent">
                                            Percentage Off (%)
                                        </SelectItem>
                                        <SelectItem value="fixed">
                                            Fixed Amount Off ($ / Currency)
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormField>

                            <FormField
                                id="discount_value"
                                label={
                                    form.data.discount_type === 'percent'
                                        ? 'Discount Percentage (%)'
                                        : 'Discount Amount'
                                }
                                error={form.errors.discount_value}
                                hint={
                                    form.data.discount_type === 'percent'
                                        ? 'e.g. 15 for 15% off'
                                        : 'e.g. 25.00'
                                }
                            >
                                <Input
                                    id="discount_value"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    value={form.data.discount_value}
                                    onChange={(e) =>
                                        form.setData(
                                            'discount_value',
                                            parseFloat(e.target.value),
                                        )
                                    }
                                    required
                                />
                            </FormField>

                            <FormField
                                id="priority"
                                label="Priority"
                                error={form.errors.priority}
                                hint="Higher priority deals are selected first when multiple deals match a product."
                            >
                                <Input
                                    id="priority"
                                    type="number"
                                    value={form.data.priority}
                                    onChange={(e) =>
                                        form.setData(
                                            'priority',
                                            parseInt(e.target.value, 10),
                                        )
                                    }
                                />
                            </FormField>

                            <FormField
                                id="min_items_required"
                                label="Minimum Items Required"
                                error={form.errors.min_items_required}
                                hint="Minimum items needed in bundle to trigger savings (usually 2)."
                            >
                                <Input
                                    id="min_items_required"
                                    type="number"
                                    min="2"
                                    max="6"
                                    value={form.data.min_items_required}
                                    onChange={(e) =>
                                        form.setData(
                                            'min_items_required',
                                            parseInt(e.target.value, 10),
                                        )
                                    }
                                />
                            </FormField>
                        </CardContent>
                    </Card>

                    <div className="flex justify-end gap-3">
                        <Link href="/admin/m/modrixcommerce/buy-together-save">
                            <Button variant="outline" type="button">
                                Cancel
                            </Button>
                        </Link>
                        <Button
                            type="submit"
                            disabled={form.processing}
                            className="bg-emerald-600 text-white hover:bg-emerald-700"
                        >
                            <Save className="mr-2 size-4" />
                            {isEdit ? 'Save Changes' : 'Create Deal'}
                        </Button>
                    </div>
                </form>
            </AdminPage>
        </>
    );
}
