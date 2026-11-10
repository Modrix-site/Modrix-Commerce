import { Head, Link, useForm } from '@inertiajs/react';
import { Search, X } from 'lucide-react';
import { type FormEvent, useEffect, useState } from 'react';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

interface ProductPick {
    id: number;
    name: string;
    slug: string;
    image: string | null;
}

interface CategoryOption {
    id: number;
    name: string;
}

interface AddonFormPayload {
    id?: number;
    name: string;
    type: string;
    badge: string;
    description: string;
    price: string;
    target_type: 'all' | 'products' | 'categories';
    target_ids: number[];
    selected_products?: ProductPick[];
    priority: number;
    is_active: boolean;
}

const basePath = '/admin/m/modrixcommerce/product-addons';

export default function ProductAddonForm({
    addon,
    currency,
    categories,
}: {
    addon: AddonFormPayload | null;
    currency: string;
    categories: CategoryOption[];
}) {
    const isEdit = addon !== null;

    const [selectedProducts, setSelectedProducts] = useState<ProductPick[]>(addon?.selected_products ?? []);
    const [searchQuery, setSearchQuery] = useState('');
    const [searchResults, setSearchResults] = useState<ProductPick[]>([]);

    const { data, setData, post, put, processing, errors } = useForm({
        name: addon?.name ?? '',
        type: addon?.type ?? 'warranty',
        badge: addon?.badge ?? '2 years',
        description: addon?.description ?? '',
        price: addon?.price ?? '19.99',
        target_type: (addon?.target_type ?? 'all') as 'all' | 'products' | 'categories',
        target_ids: addon?.target_ids ?? ([] as number[]),
        priority: addon?.priority ?? 0,
        is_active: addon?.is_active ?? true,
    });

    useEffect(() => {
        if (data.target_type !== 'products') return;

        const timer = setTimeout(() => {
            fetch(`${basePath}/products?q=${encodeURIComponent(searchQuery)}`, {
                headers: { Accept: 'application/json' },
            })
                .then((r) => r.json())
                .then((res: { products: ProductPick[] }) => setSearchResults(res.products ?? []));
        }, 180);

        return () => clearTimeout(timer);
    }, [searchQuery, data.target_type]);

    function changeTargetType(nextType: 'all' | 'products' | 'categories') {
        setData((prev) => ({
            ...prev,
            target_type: nextType,
            target_ids: [],
        }));
    }

    function addProduct(product: ProductPick) {
        if (data.target_ids.includes(product.id)) return;
        setSelectedProducts((prev) => [...prev.filter((p) => p.id !== product.id), product]);
        setData('target_ids', [...data.target_ids, product.id]);
    }

    function removeProduct(id: number) {
        setSelectedProducts((prev) => prev.filter((p) => p.id !== id));
        setData(
            'target_ids',
            data.target_ids.filter((tid) => tid !== id),
        );
    }

    function toggleCategory(categoryId: number, checked: boolean) {
        if (checked) {
            setData('target_ids', Array.from(new Set([...data.target_ids, categoryId])));
        } else {
            setData(
                'target_ids',
                data.target_ids.filter((id) => id !== categoryId),
            );
        }
    }

    function submit(e: FormEvent) {
        e.preventDefault();
        if (isEdit) {
            put(`${basePath}/${addon.id}`);
        } else {
            post(basePath);
        }
    }

    return (
        <>
            <Head title={isEdit ? `Edit ${addon.name}` : 'New Product Addon'} />
            <AdminPage>
                <form onSubmit={submit} className="max-w-3xl space-y-6">
                    <PageHeader
                        title={isEdit ? `Edit ${addon.name}` : 'New Product Addon / Warranty'}
                        description="Configure pricing, coverage details, and which products or categories offer this option."
                        actions={
                            <div className="flex gap-2">
                                <Button type="button" variant="outline" asChild>
                                    <Link href={basePath}>Cancel</Link>
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    Save Addon
                                </Button>
                            </div>
                        }
                    />

                    <Card>
                        <CardHeader>
                            <CardTitle>Addon Details & Pricing</CardTitle>
                            <CardDescription>
                                How this warranty or extra feature appears to shoppers on the product page, cart, and checkout.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-3">
                                <div className="space-y-2 sm:col-span-2">
                                    <Label htmlFor="name">Addon Name</Label>
                                    <Input
                                        id="name"
                                        placeholder="e.g. 2-Year Extended Warranty"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                    />
                                    <InputError message={errors.name} />
                                </div>
                                <div className="space-y-2">
                                    <Label>Type</Label>
                                    <Select value={data.type} onValueChange={(v) => setData('type', v)}>
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="warranty">Warranty / Protection</SelectItem>
                                            <SelectItem value="feature">Extra Feature / Upgrade</SelectItem>
                                            <SelectItem value="service">Service / Installation</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.type} />
                                </div>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-3">
                                <div className="space-y-2">
                                    <Label htmlFor="price">Price ({currency})</Label>
                                    <Input
                                        id="price"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        value={data.price}
                                        onChange={(e) => setData('price', e.target.value)}
                                    />
                                    <InputError message={errors.price} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="badge">Badge / Duration (optional)</Label>
                                    <Input
                                        id="badge"
                                        placeholder="e.g. 2 years, Popular, VIP"
                                        value={data.badge}
                                        onChange={(e) => setData('badge', e.target.value)}
                                    />
                                    <InputError message={errors.badge} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="priority">Display Priority</Label>
                                    <Input
                                        id="priority"
                                        type="number"
                                        value={data.priority}
                                        onChange={(e) => setData('priority', Number(e.target.value))}
                                    />
                                    <InputError message={errors.priority} />
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="description">Short Description</Label>
                                <Input
                                    id="description"
                                    placeholder="e.g. Covers accidental damage, mechanical failure, and free express replacement."
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                />
                                <InputError message={errors.description} />
                            </div>

                            <div className="flex items-center gap-2 pt-1">
                                <Checkbox
                                    id="is_active"
                                    checked={data.is_active}
                                    onCheckedChange={(v) => setData('is_active', v === true)}
                                />
                                <Label htmlFor="is_active">Active and selectable on storefront</Label>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Product & Category Assignment</CardTitle>
                            <CardDescription>
                                Choose whether this addon is offered on all products, specific products, or products in selected categories.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label>Applies To</Label>
                                <Select
                                    value={data.target_type}
                                    onValueChange={(v) => changeTargetType(v as 'all' | 'products' | 'categories')}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Products in Catalog</SelectItem>
                                        <SelectItem value="products">Specific Products</SelectItem>
                                        <SelectItem value="categories">Specific Categories</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.target_type} />
                            </div>

                            {data.target_type === 'products' && (
                                <div className="space-y-3">
                                    <Label>Selected Products ({data.target_ids.length})</Label>
                                    {selectedProducts.length > 0 && (
                                        <div className="flex flex-wrap gap-2">
                                            {selectedProducts.map((p) => (
                                                <Badge key={p.id} variant="secondary" className="gap-1.5 py-1 pr-1.5 pl-2.5">
                                                    <span>{p.name}</span>
                                                    <button
                                                        type="button"
                                                        onClick={() => removeProduct(p.id)}
                                                        className="rounded-full p-0.5 hover:bg-muted"
                                                    >
                                                        <X className="size-3" />
                                                    </button>
                                                </Badge>
                                            ))}
                                        </div>
                                    )}

                                    <div className="relative">
                                        <Search className="pointer-events-none absolute top-2.5 left-3 size-4 text-muted-foreground" />
                                        <Input
                                            className="pl-9"
                                            placeholder="Search products by name or slug..."
                                            value={searchQuery}
                                            onChange={(e) => setSearchQuery(e.target.value)}
                                        />
                                    </div>

                                    <div className="max-h-56 divide-y overflow-y-auto rounded-md border">
                                        {searchResults.map((p) => {
                                            const picked = data.target_ids.includes(p.id);
                                            return (
                                                <div
                                                    key={p.id}
                                                    className="flex items-center justify-between px-3 py-2 text-sm hover:bg-muted/50"
                                                >
                                                    <div className="flex items-center gap-2.5">
                                                        {p.image ? (
                                                            <img
                                                                src={p.image}
                                                                alt=""
                                                                className="size-7 rounded object-cover"
                                                            />
                                                        ) : (
                                                            <div className="size-7 rounded bg-muted" />
                                                        )}
                                                        <div>
                                                            <p className="font-medium">{p.name}</p>
                                                            <p className="text-xs text-muted-foreground">/{p.slug}</p>
                                                        </div>
                                                    </div>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant={picked ? 'secondary' : 'outline'}
                                                        onClick={() => (picked ? removeProduct(p.id) : addProduct(p))}
                                                    >
                                                        {picked ? 'Selected' : 'Select'}
                                                    </Button>
                                                </div>
                                            );
                                        })}
                                        {searchResults.length === 0 && (
                                            <p className="p-4 text-center text-xs text-muted-foreground">
                                                No matching products found.
                                            </p>
                                        )}
                                    </div>
                                </div>
                            )}

                            {data.target_type === 'categories' && (
                                <div className="space-y-2">
                                    <Label>Select Categories ({data.target_ids.length} selected)</Label>
                                    <div className="grid gap-2 rounded-md border p-3 sm:grid-cols-2">
                                        {categories.map((cat) => {
                                            const checked = data.target_ids.includes(cat.id);
                                            return (
                                                <label
                                                    key={cat.id}
                                                    className="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm hover:bg-muted/50"
                                                >
                                                    <Checkbox
                                                        checked={checked}
                                                        onCheckedChange={(v) => toggleCategory(cat.id, v === true)}
                                                    />
                                                    <span>{cat.name}</span>
                                                </label>
                                            );
                                        })}
                                        {categories.length === 0 && (
                                            <p className="col-span-2 text-xs text-muted-foreground">
                                                No categories found in catalog.
                                            </p>
                                        )}
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </form>
            </AdminPage>
        </>
    );
}
