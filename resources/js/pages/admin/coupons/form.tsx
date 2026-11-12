import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import CouponController from '@/actions/App/Http/Controllers/Admin/CouponController';
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
import { trimDecimal } from '@/lib/decimal';
import { index } from '@/routes/admin/coupons';
import type { CategoryOption } from '@/types';

type CouponData = {
    id: number;
    code: string;
    description: string | null;
    type: string;
    value: string;
    min_subtotal: string | null;
    starts_at: string | null;
    ends_at: string | null;
    usage_limit: number | null;
    usage_limit_per_customer: number | null;
    applies_to: string;
    is_active: boolean;
    times_used: number;
    product_ids: number[];
    category_ids: number[];
};

type Props = {
    coupon: CouponData | null;
    currency: string;
    products: { id: number; name: string }[];
    categories: CategoryOption[];
};

export default function CouponForm({
    coupon,
    currency,
    products,
    categories,
}: Props) {
    const form = useForm({
        code: coupon?.code ?? '',
        description: coupon?.description ?? '',
        type: coupon?.type ?? 'percentage',
        value: trimDecimal(coupon?.value ?? null),
        min_subtotal: trimDecimal(coupon?.min_subtotal ?? null),
        starts_at: coupon?.starts_at ?? '',
        ends_at: coupon?.ends_at ?? '',
        usage_limit: coupon?.usage_limit?.toString() ?? '',
        usage_limit_per_customer:
            coupon?.usage_limit_per_customer?.toString() ?? '',
        applies_to: coupon?.applies_to ?? 'all',
        product_ids: coupon?.product_ids ?? [],
        category_ids: coupon?.category_ids ?? [],
        is_active: coupon?.is_active ?? true,
    });
    const { data, setData } = form;
    const errors = form.errors as Record<string, string | undefined>;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((d) => ({
            ...d,
            min_subtotal: d.min_subtotal || null,
            starts_at: d.starts_at || null,
            ends_at: d.ends_at || null,
            usage_limit: d.usage_limit || null,
            usage_limit_per_customer: d.usage_limit_per_customer || null,
        }));
        form.submit(
            coupon
                ? CouponController.update(coupon.id)
                : CouponController.store(),
        );
    };

    const remove = () => {
        if (
            coupon &&
            window.confirm(
                `Delete coupon ${coupon.code}? Past redemptions are kept.`,
            )
        ) {
            form.submit(CouponController.destroy(coupon.id));
        }
    };

    const toggle = (
        list: 'product_ids' | 'category_ids',
        id: number,
        checked: boolean,
    ) =>
        setData(
            list,
            checked ? [...data[list], id] : data[list].filter((x) => x !== id),
        );

    return (
        <>
            <Head title={coupon ? `Coupon ${coupon.code}` : 'New coupon'} />
            <AdminPage>
                <PageHeader
                    title={coupon ? coupon.code : 'New coupon'}
                    description={
                        coupon ? `Used ${coupon.times_used} times.` : undefined
                    }
                    actions={
                        coupon && (
                            <Button variant="destructive" onClick={remove}>
                                Delete
                            </Button>
                        )
                    }
                />
                <form onSubmit={submit} className="grid gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Discount</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <FormField
                                id="code"
                                label="Code"
                                error={errors.code}
                                hint="Letters, numbers, hyphens and underscores. Not case-sensitive for customers."
                            >
                                <Input
                                    id="code"
                                    value={data.code}
                                    onChange={(e) =>
                                        setData(
                                            'code',
                                            e.target.value.toUpperCase(),
                                        )
                                    }
                                    required
                                />
                            </FormField>
                            <FormField
                                id="description"
                                label="Internal description"
                                error={errors.description}
                            >
                                <Input
                                    id="description"
                                    value={data.description}
                                    onChange={(e) =>
                                        setData('description', e.target.value)
                                    }
                                />
                            </FormField>
                            <FormField
                                id="type"
                                label="Type"
                                error={errors.type}
                            >
                                <select
                                    id="type"
                                    value={data.type}
                                    onChange={(e) =>
                                        setData('type', e.target.value)
                                    }
                                    className="h-9 rounded-md border border-input bg-transparent px-3 text-sm dark:bg-input/30"
                                >
                                    <option value="percentage">
                                        Percentage
                                    </option>
                                    <option value="fixed">
                                        Fixed amount ({currency})
                                    </option>
                                </select>
                            </FormField>
                            <FormField
                                id="value"
                                label={
                                    data.type === 'percentage'
                                        ? 'Percent off'
                                        : `Amount off (${currency})`
                                }
                                error={errors.value}
                            >
                                <Input
                                    id="value"
                                    inputMode="decimal"
                                    value={data.value}
                                    onChange={(e) =>
                                        setData('value', e.target.value)
                                    }
                                    required
                                />
                            </FormField>
                            <FormField
                                id="min_subtotal"
                                label={`Minimum subtotal (${currency}, optional)`}
                                error={errors.min_subtotal}
                            >
                                <Input
                                    id="min_subtotal"
                                    inputMode="decimal"
                                    value={data.min_subtotal}
                                    onChange={(e) =>
                                        setData('min_subtotal', e.target.value)
                                    }
                                />
                            </FormField>
                            <label className="flex items-center gap-2 self-end pb-2 text-sm">
                                <Checkbox
                                    checked={data.is_active}
                                    onCheckedChange={(c) =>
                                        setData('is_active', c === true)
                                    }
                                />
                                Active
                            </label>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Limits</CardTitle>
                            <CardDescription>
                                Dates use the store timezone. Leave empty for no
                                limit.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <FormField
                                id="starts_at"
                                label="Starts"
                                error={errors.starts_at}
                            >
                                <Input
                                    id="starts_at"
                                    type="datetime-local"
                                    value={data.starts_at}
                                    onChange={(e) =>
                                        setData('starts_at', e.target.value)
                                    }
                                />
                            </FormField>
                            <FormField
                                id="ends_at"
                                label="Ends"
                                error={errors.ends_at}
                            >
                                <Input
                                    id="ends_at"
                                    type="datetime-local"
                                    value={data.ends_at}
                                    onChange={(e) =>
                                        setData('ends_at', e.target.value)
                                    }
                                />
                            </FormField>
                            <FormField
                                id="usage_limit"
                                label="Total uses"
                                error={errors.usage_limit}
                            >
                                <Input
                                    id="usage_limit"
                                    type="number"
                                    min={1}
                                    value={data.usage_limit}
                                    onChange={(e) =>
                                        setData('usage_limit', e.target.value)
                                    }
                                />
                            </FormField>
                            <FormField
                                id="usage_limit_per_customer"
                                label="Uses per customer"
                                error={errors.usage_limit_per_customer}
                            >
                                <Input
                                    id="usage_limit_per_customer"
                                    type="number"
                                    min={1}
                                    value={data.usage_limit_per_customer}
                                    onChange={(e) =>
                                        setData(
                                            'usage_limit_per_customer',
                                            e.target.value,
                                        )
                                    }
                                />
                            </FormField>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Applies to</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <select
                                aria-label="Applies to"
                                value={data.applies_to}
                                onChange={(e) =>
                                    setData('applies_to', e.target.value)
                                }
                                className="h-9 w-full max-w-xs rounded-md border border-input bg-transparent px-3 text-sm dark:bg-input/30"
                            >
                                <option value="all">All products</option>
                                <option value="products">
                                    Specific products
                                </option>
                                <option value="categories">
                                    Specific categories (including
                                    subcategories)
                                </option>
                            </select>
                            {data.applies_to === 'products' && (
                                <div className="grid max-h-64 gap-2 overflow-y-auto sm:grid-cols-2">
                                    {products.map((p) => (
                                        <label
                                            key={p.id}
                                            className="flex items-center gap-2 text-sm"
                                        >
                                            <Checkbox
                                                checked={data.product_ids.includes(
                                                    p.id,
                                                )}
                                                onCheckedChange={(c) =>
                                                    toggle(
                                                        'product_ids',
                                                        p.id,
                                                        c === true,
                                                    )
                                                }
                                            />
                                            {p.name}
                                        </label>
                                    ))}
                                </div>
                            )}
                            {data.applies_to === 'categories' && (
                                <div className="grid max-h-64 gap-2 overflow-y-auto">
                                    {categories.map((c) => (
                                        <label
                                            key={c.id}
                                            className="flex items-center gap-2 text-sm"
                                            style={{
                                                paddingLeft: `${c.depth * 1.25}rem`,
                                            }}
                                        >
                                            <Checkbox
                                                checked={data.category_ids.includes(
                                                    c.id,
                                                )}
                                                onCheckedChange={(v) =>
                                                    toggle(
                                                        'category_ids',
                                                        c.id,
                                                        v === true,
                                                    )
                                                }
                                            />
                                            {c.name}
                                        </label>
                                    ))}
                                </div>
                            )}
                            <p className="text-sm text-destructive">
                                {errors.product_ids ?? errors.category_ids}
                            </p>
                        </CardContent>
                    </Card>

                    <div className="flex gap-2">
                        <Button type="submit" disabled={form.processing}>
                            {coupon ? 'Save coupon' : 'Create coupon'}
                        </Button>
                        <Button variant="ghost" asChild>
                            <Link href={index()}>Cancel</Link>
                        </Button>
                    </div>
                </form>
            </AdminPage>
        </>
    );
}

CouponForm.layout = {
    breadcrumbs: [{ title: 'Discounts', href: index() }],
};
