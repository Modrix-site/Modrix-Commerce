import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Plus, Save, Trash2 } from 'lucide-react';
import { FormEvent } from 'react';
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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type TierItem = {
    min_qty: number;
    max_qty: number | null;
    discount: number;
};

type Rule = {
    id: number;
    title: string;
    description: string | null;
    applies_to: 'product' | 'category' | 'all';
    target_ids: number[];
    discount_type: 'percent' | 'fixed_per_unit';
    tiers: TierItem[];
    priority: number;
    is_active: boolean;
    starts_at: string | null;
    ends_at: string | null;
};

type ProductOption = {
    id: number;
    name: string;
    sku: string;
};

type CategoryOption = {
    id: number;
    name: string;
};

type Props = {
    rule: Rule | null;
    products: ProductOption[];
    categories: CategoryOption[];
    actionUrl: string;
};

function FormField({
    id,
    label,
    error,
    hint,
    children,
}: {
    id: string;
    label: string;
    error?: string;
    hint?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id} className="text-sm font-medium">
                {label}
            </Label>
            {children}
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
            {error && <p className="text-xs text-destructive">{error}</p>}
        </div>
    );
}

export default function TieredPricingEdit({
    rule,
    products,
    categories,
    actionUrl,
}: Props) {
    const isEdit = rule !== null;

    const form = useForm({
        title: rule?.title ?? '',
        description: rule?.description ?? '',
        applies_to: (rule?.applies_to ?? 'product') as
            | 'product'
            | 'category'
            | 'all',
        target_ids: (rule?.target_ids ?? []) as number[],
        discount_type: (rule?.discount_type ?? 'percent') as
            | 'percent'
            | 'fixed_per_unit',
        tiers: (rule?.tiers && rule.tiers.length > 0
            ? rule.tiers
            : [
                  { min_qty: 2, max_qty: 4, discount: 5 },
                  { min_qty: 5, max_qty: 9, discount: 10 },
                  { min_qty: 10, max_qty: null, discount: 15 },
              ]) as TierItem[],
        priority: rule?.priority ?? 0,
        is_active: rule?.is_active ?? true,
        starts_at: rule?.starts_at ? rule.starts_at.slice(0, 16) : '',
        ends_at: rule?.ends_at ? rule.ends_at.slice(0, 16) : '',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        if (isEdit) {
            form.put(actionUrl);
        } else {
            form.post(actionUrl);
        }
    };

    const addTier = () => {
        const lastTier = form.data.tiers[form.data.tiers.length - 1];
        const nextMin = lastTier
            ? lastTier.max_qty
                ? lastTier.max_qty + 1
                : lastTier.min_qty + 5
            : 2;
        form.setData('tiers', [
            ...form.data.tiers,
            {
                min_qty: nextMin,
                max_qty: null,
                discount: (lastTier?.discount ?? 5) + 5,
            },
        ]);
    };

    const removeTier = (index: number) => {
        if (form.data.tiers.length <= 1) {
            return;
        }
        form.setData(
            'tiers',
            form.data.tiers.filter((_, idx) => idx !== index),
        );
    };

    const updateTier = (index: number, field: keyof TierItem, value: any) => {
        const updated = [...form.data.tiers];
        updated[index] = { ...updated[index], [field]: value };
        form.setData('tiers', updated);
    };

    const toggleTarget = (id: number) => {
        const current = form.data.target_ids;
        form.setData(
            'target_ids',
            current.includes(id)
                ? current.filter((item) => item !== id)
                : [...current, id],
        );
    };

    return (
        <>
            <Head
                title={
                    isEdit
                        ? `Edit Rule: ${rule.title}`
                        : 'New Tiered Pricing Rule'
                }
            />

            <AdminPage>
                <PageHeader
                    title={
                        isEdit
                            ? `Edit Rule: ${rule.title}`
                            : 'Create Tiered Pricing Rule'
                    }
                    description="Configure quantity-break volume tiers and savings to boost bulk sales."
                    actions={
                        <Link href="/admin/m/modrixcommerce/tiered-pricing">
                            <Button variant="outline" size="sm">
                                <ArrowLeft className="mr-2 size-4" />
                                Back to Rules
                            </Button>
                        </Link>
                    }
                />

                <form onSubmit={handleSubmit} className="grid gap-6">
                    {/* Basic Info */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Rule Details</CardTitle>
                            <CardDescription>
                                Set the rule name, description, priority, and
                                schedule.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-2">
                            <FormField
                                id="title"
                                label="Rule Title"
                                error={form.errors.title}
                            >
                                <Input
                                    id="title"
                                    value={form.data.title}
                                    onChange={(e) =>
                                        form.setData('title', e.target.value)
                                    }
                                    placeholder="e.g. Bulk Buy Savings"
                                    required
                                />
                            </FormField>

                            <FormField
                                id="priority"
                                label="Priority"
                                error={form.errors.priority}
                                hint="Higher priority rules match first"
                            >
                                <Input
                                    id="priority"
                                    type="number"
                                    value={form.data.priority}
                                    onChange={(e) =>
                                        form.setData(
                                            'priority',
                                            parseInt(e.target.value, 10) || 0,
                                        )
                                    }
                                />
                            </FormField>

                            <div className="md:col-span-2">
                                <FormField
                                    id="description"
                                    label="Internal Description"
                                    error={form.errors.description}
                                >
                                    <Input
                                        id="description"
                                        value={form.data.description}
                                        onChange={(e) =>
                                            form.setData(
                                                'description',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Optional merchant note"
                                    />
                                </FormField>
                            </div>

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
                                <Label
                                    htmlFor="is_active"
                                    className="text-sm font-medium"
                                >
                                    Enable this tiered pricing rule
                                </Label>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Targets */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Target Products</CardTitle>
                            <CardDescription>
                                Select which products or categories this volume
                                discount applies to.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <FormField
                                id="applies_to"
                                label="Applies To"
                                error={form.errors.applies_to}
                            >
                                <Select
                                    value={form.data.applies_to}
                                    onValueChange={(val: string) =>
                                        form.setData(
                                            'applies_to',
                                            val as
                                                | 'product'
                                                | 'category'
                                                | 'all',
                                        )
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select target scope" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="product">
                                            Specific Products
                                        </SelectItem>
                                        <SelectItem value="category">
                                            Categories
                                        </SelectItem>
                                        <SelectItem value="all">
                                            All Products
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormField>

                            {form.data.applies_to === 'product' && (
                                <div className="grid gap-2">
                                    <Label className="text-sm font-medium">
                                        Select Products
                                    </Label>
                                    <div className="max-h-60 space-y-2 overflow-y-auto rounded-md border p-3">
                                        {products.map((p) => (
                                            <div
                                                key={p.id}
                                                className="flex items-center space-x-2"
                                            >
                                                <Checkbox
                                                    id={`prod-${p.id}`}
                                                    checked={form.data.target_ids.includes(
                                                        p.id,
                                                    )}
                                                    onCheckedChange={() =>
                                                        toggleTarget(p.id)
                                                    }
                                                />
                                                <Label
                                                    htmlFor={`prod-${p.id}`}
                                                    className="cursor-pointer text-xs"
                                                >
                                                    {p.name}{' '}
                                                    <span className="text-muted-foreground">
                                                        ({p.sku})
                                                    </span>
                                                </Label>
                                            </div>
                                        ))}
                                    </div>
                                    {form.errors.target_ids && (
                                        <p className="text-xs text-destructive">
                                            {form.errors.target_ids}
                                        </p>
                                    )}
                                </div>
                            )}

                            {form.data.applies_to === 'category' && (
                                <div className="grid gap-2">
                                    <Label className="text-sm font-medium">
                                        Select Categories
                                    </Label>
                                    <div className="max-h-60 space-y-2 overflow-y-auto rounded-md border p-3">
                                        {categories.map((c) => (
                                            <div
                                                key={c.id}
                                                className="flex items-center space-x-2"
                                            >
                                                <Checkbox
                                                    id={`cat-${c.id}`}
                                                    checked={form.data.target_ids.includes(
                                                        c.id,
                                                    )}
                                                    onCheckedChange={() =>
                                                        toggleTarget(c.id)
                                                    }
                                                />
                                                <Label
                                                    htmlFor={`cat-${c.id}`}
                                                    className="cursor-pointer text-xs"
                                                >
                                                    {c.name}
                                                </Label>
                                            </div>
                                        ))}
                                    </div>
                                    {form.errors.target_ids && (
                                        <p className="text-xs text-destructive">
                                            {form.errors.target_ids}
                                        </p>
                                    )}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Quantity Tiers Builder */}
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle>
                                    Quantity Tiers & Discounts
                                </CardTitle>
                                <CardDescription>
                                    Define the minimum quantity thresholds and
                                    discounts.
                                </CardDescription>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={addTier}
                            >
                                <Plus className="mr-1.5 size-4" />
                                Add Tier
                            </Button>
                        </CardHeader>
                        <CardContent className="grid gap-4">
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
                                            val as 'percent' | 'fixed_per_unit',
                                        )
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Discount Type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="percent">
                                            Percentage Off Each Unit (%)
                                        </SelectItem>
                                        <SelectItem value="fixed_per_unit">
                                            Fixed Amount Off Each Unit ($ /
                                            Currency)
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormField>

                            <div className="space-y-3">
                                {form.data.tiers.map((tier, idx) => (
                                    <div
                                        key={idx}
                                        className="flex flex-wrap items-center gap-3 rounded-lg border bg-muted/20 p-3"
                                    >
                                        <div className="w-28">
                                            <Label className="text-xs text-muted-foreground">
                                                Min Qty
                                            </Label>
                                            <Input
                                                type="number"
                                                min="2"
                                                value={tier.min_qty}
                                                onChange={(e) =>
                                                    updateTier(
                                                        idx,
                                                        'min_qty',
                                                        parseInt(
                                                            e.target.value,
                                                            10,
                                                        ) || 2,
                                                    )
                                                }
                                                required
                                            />
                                        </div>

                                        <div className="w-28">
                                            <Label className="text-xs text-muted-foreground">
                                                Max Qty (optional)
                                            </Label>
                                            <Input
                                                type="number"
                                                min={tier.min_qty}
                                                placeholder="No max (+)"
                                                value={tier.max_qty ?? ''}
                                                onChange={(e) =>
                                                    updateTier(
                                                        idx,
                                                        'max_qty',
                                                        e.target.value !== ''
                                                            ? parseInt(
                                                                  e.target
                                                                      .value,
                                                                  10,
                                                              )
                                                            : null,
                                                    )
                                                }
                                            />
                                        </div>

                                        <div className="w-36">
                                            <Label className="text-xs text-muted-foreground">
                                                {form.data.discount_type ===
                                                'percent'
                                                    ? 'Discount (%)'
                                                    : 'Discount per Unit'}
                                            </Label>
                                            <Input
                                                type="number"
                                                step="0.01"
                                                min="0.01"
                                                value={tier.discount}
                                                onChange={(e) =>
                                                    updateTier(
                                                        idx,
                                                        'discount',
                                                        parseFloat(
                                                            e.target.value,
                                                        ) || 0,
                                                    )
                                                }
                                                required
                                            />
                                        </div>

                                        <div className="pt-5">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => removeTier(idx)}
                                                disabled={
                                                    form.data.tiers.length <= 1
                                                }
                                                className="text-destructive hover:text-destructive"
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>

                    <div className="flex justify-end gap-3">
                        <Link href="/admin/m/modrixcommerce/tiered-pricing">
                            <Button variant="outline" type="button">
                                Cancel
                            </Button>
                        </Link>
                        <Button
                            type="submit"
                            disabled={form.processing}
                            className="bg-blue-600 text-white hover:bg-blue-700"
                        >
                            <Save className="mr-2 size-4" />
                            {isEdit ? 'Save Changes' : 'Create Rule'}
                        </Button>
                    </div>
                </form>
            </AdminPage>
        </>
    );
}

TieredPricingEdit.layout = {
    breadcrumbs: [
        {
            title: 'Tiered Pricing',
            href: '/admin/m/modrixcommerce/tiered-pricing',
        },
    ],
};
