import { Form, Head, Link, router } from '@inertiajs/react';
import { ImageOff, Plus, Search, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { FormField, NativeSelect } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
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
import type { MoneyValue } from '@/types';
import { discountLabel } from './index';
import type { SaleState } from './index';

type Item = {
    id: number;
    product_id: number;
    name: string;
    image: string | null;
    price: MoneyValue | null;
    sale_price: MoneyValue | null;
    discount_type: 'percent' | 'fixed' | null;
    discount_value: string | null;
    update_url: string;
    remove_url: string;
};

type Sale = {
    id: number;
    name: string;
    discount_type: 'percent' | 'fixed';
    discount_value: string;
    starts_at_local: string;
    ends_at_local: string;
    is_active: boolean;
    show_banner: boolean;
    state: SaleState;
    update_url: string;
    add_products_url: string;
    items: Item[];
};

type Props = {
    sale: Sale | null;
    currency: string;
    timezone: string;
    storeUrl: string;
    indexUrl: string;
    searchUrl: string;
};

/** The price after a discount, for the preview column. */
export default function FlashSaleForm({
    sale,
    currency,
    timezone,
    storeUrl,
    indexUrl,
    searchUrl,
}: Props) {
    const [type, setType] = useState<'percent' | 'fixed'>(
        sale?.discount_type ?? 'percent',
    );

    return (
        <>
            <Head
                title={sale ? `Flash sale: ${sale.name}` : 'New flash sale'}
            />
            <AdminPage>
                <PageHeader
                    title={sale ? sale.name : 'New flash sale'}
                    description={`Times are in the store time zone (${timezone}).`}
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={indexUrl}>All flash sales</Link>
                        </Button>
                    }
                />

                <Form
                    action={sale ? sale.update_url : storeUrl}
                    method={sale ? 'put' : 'post'}
                    options={{ preserveScroll: true }}
                    className="grid gap-6"
                >
                    {({ processing, errors }) => (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    Sale details
                                    {sale && (
                                        <Badge
                                            variant={
                                                sale.state === 'live'
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                        >
                                            {sale.state === 'live'
                                                ? 'Live'
                                                : sale.state === 'scheduled'
                                                  ? 'Scheduled'
                                                  : sale.state === 'ended'
                                                    ? 'Ended'
                                                    : 'Off'}
                                        </Badge>
                                    )}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <div className="sm:col-span-2">
                                    <FormField
                                        id="name"
                                        label="Name"
                                        error={errors.name}
                                        hint="Shown to shoppers, e.g. “Weekend Flash Sale”."
                                    >
                                        <Input
                                            id="name"
                                            name="name"
                                            defaultValue={sale?.name}
                                            maxLength={120}
                                        />
                                    </FormField>
                                </div>
                                <FormField
                                    id="discount_type"
                                    label="Discount"
                                    error={errors.discount_type}
                                >
                                    <NativeSelect
                                        id="discount_type"
                                        name="discount_type"
                                        value={type}
                                        onChange={(e) =>
                                            setType(
                                                e.target.value as
                                                    | 'percent'
                                                    | 'fixed',
                                            )
                                        }
                                    >
                                        <option value="percent">
                                            Percentage off
                                        </option>
                                        <option value="fixed">
                                            Fixed amount off ({currency})
                                        </option>
                                    </NativeSelect>
                                </FormField>
                                <FormField
                                    id="discount_value"
                                    label={
                                        type === 'percent'
                                            ? 'Percentage (1–90)'
                                            : `Amount (${currency})`
                                    }
                                    error={errors.discount_value}
                                >
                                    <Input
                                        id="discount_value"
                                        name="discount_value"
                                        inputMode="decimal"
                                        defaultValue={
                                            sale
                                                ? String(
                                                      Number(
                                                          sale.discount_value,
                                                      ),
                                                  )
                                                : ''
                                        }
                                        placeholder={
                                            type === 'percent' ? '20' : '5.00'
                                        }
                                    />
                                </FormField>
                                <FormField
                                    id="starts_at"
                                    label="Starts"
                                    error={errors.starts_at}
                                >
                                    <Input
                                        id="starts_at"
                                        name="starts_at"
                                        type="datetime-local"
                                        defaultValue={sale?.starts_at_local}
                                    />
                                </FormField>
                                <FormField
                                    id="ends_at"
                                    label="Ends"
                                    error={errors.ends_at}
                                >
                                    <Input
                                        id="ends_at"
                                        name="ends_at"
                                        type="datetime-local"
                                        defaultValue={sale?.ends_at_local}
                                    />
                                </FormField>
                                <label className="flex items-start gap-2 text-sm">
                                    <input
                                        type="hidden"
                                        name="is_active"
                                        value="0"
                                    />
                                    <Checkbox
                                        name="is_active"
                                        value="1"
                                        defaultChecked={sale?.is_active ?? true}
                                        className="mt-0.5"
                                    />
                                    <span>
                                        Active
                                        <span className="block text-xs text-muted-foreground">
                                            Switch off to pause the sale without
                                            changing its dates.
                                        </span>
                                    </span>
                                </label>
                                <label className="flex items-start gap-2 text-sm">
                                    <input
                                        type="hidden"
                                        name="show_banner"
                                        value="0"
                                    />
                                    <Checkbox
                                        name="show_banner"
                                        value="1"
                                        defaultChecked={
                                            sale?.show_banner ?? false
                                        }
                                        className="mt-0.5"
                                    />
                                    <span>
                                        Home page banner
                                        <span className="block text-xs text-muted-foreground">
                                            Shows the sale with a countdown and
                                            its products while it is live.
                                        </span>
                                    </span>
                                </label>
                                <div className="sm:col-span-2">
                                    <Button type="submit" disabled={processing}>
                                        {sale ? 'Save sale' : 'Create sale'}
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </Form>

                {sale && (
                    <SaleProducts
                        sale={sale}
                        currency={currency}
                        searchUrl={searchUrl}
                    />
                )}
            </AdminPage>
        </>
    );
}

function SaleProducts({
    sale,
    currency,
    searchUrl,
}: {
    sale: Sale;
    currency: string;
    searchUrl: string;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>
                    Products in this sale ({sale.items.length})
                </CardTitle>
                <CardDescription>
                    Every product gets{' '}
                    {discountLabel(
                        sale.discount_type,
                        sale.discount_value,
                        currency,
                    )}
                    , unless you set a discount of its own. You can also add a
                    product from its editor (“Flash sale” panel).
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-4">
                <ProductPicker sale={sale} searchUrl={searchUrl} />

                {sale.items.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No products yet.
                    </p>
                ) : (
                    <ul className="grid divide-y rounded-lg border">
                        {sale.items.map((item) => (
                            <SaleItemRow
                                key={item.id}
                                item={item}
                                sale={sale}
                                currency={currency}
                            />
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}

function SaleItemRow({
    item,
    sale,
    currency,
}: {
    item: Item;
    sale: Sale;
    currency: string;
}) {
    const [own, setOwn] = useState(item.discount_type !== null);
    const [type, setType] = useState<'percent' | 'fixed'>(
        item.discount_type ?? sale.discount_type,
    );
    const effectiveType = item.discount_type ?? sale.discount_type;
    const effectiveValue = item.discount_value ?? sale.discount_value;

    return (
        <li className="grid gap-3 p-3 sm:grid-cols-[auto_1fr_auto] sm:items-center">
            {item.image ? (
                <img
                    src={item.image}
                    alt=""
                    className="size-10 rounded border object-cover"
                />
            ) : (
                <span className="flex size-10 items-center justify-center rounded border text-muted-foreground">
                    <ImageOff className="size-4" />
                </span>
            )}
            <div className="grid gap-0.5">
                <span className="font-medium">{item.name}</span>
                <span className="text-sm text-muted-foreground tabular-nums">
                    {item.price ? (
                        <>
                            <s>{item.price.formatted}</s> →{' '}
                            {item.sale_price?.formatted} ·{' '}
                            {discountLabel(
                                effectiveType,
                                effectiveValue,
                                currency,
                            )}
                            {item.discount_type && ' (own discount)'}
                        </>
                    ) : (
                        'No price'
                    )}
                </span>
            </div>
            <div className="flex flex-wrap items-center gap-2">
                <Form
                    action={item.update_url}
                    method="put"
                    options={{ preserveScroll: true }}
                    className="flex flex-wrap items-center gap-2"
                >
                    {({ processing, errors }) => (
                        <>
                            <label className="flex items-center gap-1.5 text-sm">
                                <Checkbox
                                    checked={own}
                                    onCheckedChange={(c) => setOwn(c === true)}
                                    aria-label={`Own discount for ${item.name}`}
                                />
                                Own discount
                            </label>
                            {own && (
                                <>
                                    <select
                                        name="discount_type"
                                        value={type}
                                        onChange={(e) =>
                                            setType(
                                                e.target.value as
                                                    | 'percent'
                                                    | 'fixed',
                                            )
                                        }
                                        aria-label="Discount type"
                                        className="h-8 rounded-md border border-input bg-transparent px-2 text-sm"
                                    >
                                        <option value="percent">%</option>
                                        <option value="fixed">
                                            {currency}
                                        </option>
                                    </select>
                                    <Input
                                        name="discount_value"
                                        aria-label="Discount value"
                                        inputMode="decimal"
                                        defaultValue={
                                            item.discount_value
                                                ? String(
                                                      Number(
                                                          item.discount_value,
                                                      ),
                                                  )
                                                : ''
                                        }
                                        className="h-8 w-20"
                                    />
                                </>
                            )}
                            <Button
                                type="submit"
                                size="sm"
                                variant="outline"
                                disabled={processing}
                            >
                                Save
                            </Button>
                            <InputError
                                className="w-full"
                                message={
                                    errors.discount_value ??
                                    errors.discount_type
                                }
                            />
                        </>
                    )}
                </Form>
                <Button
                    type="button"
                    size="icon"
                    variant="ghost"
                    aria-label={`Remove ${item.name} from the sale`}
                    onClick={() =>
                        router.delete(item.remove_url, {
                            preserveScroll: true,
                        })
                    }
                >
                    <Trash2 />
                </Button>
            </div>
        </li>
    );
}

type Found = {
    id: number;
    name: string;
    status: string;
    price: MoneyValue | null;
    image: string | null;
    in_sale: boolean;
};

function ProductPicker({ sale, searchUrl }: { sale: Sale; searchUrl: string }) {
    const [q, setQ] = useState('');
    const [found, setFound] = useState<Found[]>([]);
    const [picked, setPicked] = useState<number[]>([]);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            setLoading(true);
            const url = new URL(searchUrl, window.location.origin);
            url.searchParams.set('sale', String(sale.id));
            if (q.trim() !== '') {
                url.searchParams.set('q', q.trim());
            }
            fetch(url, {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            })
                .then((r) => r.json() as Promise<{ products: Found[] }>)
                .then((data) => setFound(data.products))
                .catch(() => undefined)
                .finally(() => setLoading(false));
        }, 250);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [q, searchUrl, sale.id, sale.items.length]);

    const add = () =>
        router.post(
            sale.add_products_url,
            { product_ids: picked },
            {
                preserveScroll: true,
                onSuccess: () => setPicked([]),
            },
        );

    return (
        <div className="grid gap-2 rounded-lg border p-3">
            <div className="flex flex-wrap items-center gap-2">
                <div className="relative min-w-56 flex-1">
                    <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                    <Input
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        placeholder="Find products by name or SKU"
                        aria-label="Find products"
                        className="pl-8"
                    />
                </div>
                <Button
                    type="button"
                    onClick={add}
                    disabled={picked.length === 0}
                >
                    <Plus /> Add {picked.length > 0 ? picked.length : ''} to
                    sale
                </Button>
            </div>
            <ul
                className="grid max-h-64 gap-1 overflow-y-auto"
                aria-busy={loading}
            >
                {found.map((p) => (
                    <li key={p.id}>
                        <label className="flex items-center gap-3 rounded-md px-2 py-1.5 text-sm hover:bg-muted">
                            <Checkbox
                                checked={p.in_sale || picked.includes(p.id)}
                                disabled={p.in_sale}
                                onCheckedChange={(c) =>
                                    setPicked((current) =>
                                        c === true
                                            ? [...current, p.id]
                                            : current.filter(
                                                  (id) => id !== p.id,
                                              ),
                                    )
                                }
                            />
                            <span className="flex-1">
                                {p.name}
                                {p.status !== 'published' && (
                                    <span className="text-muted-foreground">
                                        {' '}
                                        ({p.status})
                                    </span>
                                )}
                            </span>
                            <span className="text-muted-foreground tabular-nums">
                                {p.in_sale
                                    ? 'In this sale'
                                    : (p.price?.formatted ?? '')}
                            </span>
                        </label>
                    </li>
                ))}
                {!loading && found.length === 0 && (
                    <li className="px-2 py-1.5 text-sm text-muted-foreground">
                        No products found.
                    </li>
                )}
            </ul>
        </div>
    );
}
