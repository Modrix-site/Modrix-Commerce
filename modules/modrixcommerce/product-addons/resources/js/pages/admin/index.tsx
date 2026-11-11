import { Head, Link, router } from '@inertiajs/react';
import {
    Pencil,
    Plus,
    Settings,
    ShieldCheck,
    Sparkles,
    Trash2,
    Wrench,
} from 'lucide-react';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

interface TargetSummary {
    id: number;
    name: string;
}

interface AddonRow {
    id: number;
    name: string;
    type: 'warranty' | 'feature' | 'service' | (string & {});
    badge: string | null;
    description: string | null;
    price: string;
    target_type: 'all' | 'products' | 'categories';
    target_ids: number[];
    targets: TargetSummary[];
    priority: number;
    is_active: boolean;
    units_sold: number;
    revenue: string;
}

const typeLabels: Record<string, { label: string; icon: typeof ShieldCheck }> =
    {
        warranty: { label: 'Warranty', icon: ShieldCheck },
        feature: { label: 'Extra Feature', icon: Sparkles },
        service: { label: 'Service', icon: Wrench },
    };

export default function ProductAddonsIndex({
    addons,
    currency,
}: {
    addons: AddonRow[];
    currency: string;
}) {
    function remove(addon: AddonRow) {
        if (!confirm(`Delete "${addon.name}"?`)) return;
        router.delete(`/admin/m/modrixcommerce/product-addons/${addon.id}`);
    }

    const totalRevenue = addons.reduce(
        (sum, a) => sum + Number(a.revenue || 0),
        0,
    );
    const totalSold = addons.reduce(
        (sum, a) => sum + Number(a.units_sold || 0),
        0,
    );

    return (
        <>
            <Head title="Product Addons & Warranties" />
            <AdminPage>
                <PageHeader
                    title="Product Addons & Warranties"
                    description="Offer optional extended warranties, extra features, or services on specific products, categories, or your entire catalog."
                    actions={
                        <div className="flex items-center gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/extensions/modrixcommerce/product-addons/settings">
                                    <Settings className="mr-1.5 size-4" />{' '}
                                    Display Settings
                                </Link>
                            </Button>
                            <Button asChild>
                                <Link href="/admin/m/modrixcommerce/product-addons/create">
                                    <Plus className="mr-1.5 size-4" /> New Addon
                                </Link>
                            </Button>
                        </div>
                    }
                />

                <div className="grid gap-4 sm:grid-cols-3">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Active Addons</CardDescription>
                            <CardTitle className="text-2xl tabular-nums">
                                {addons.filter((a) => a.is_active).length} /{' '}
                                {addons.length}
                            </CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Addons Sold</CardDescription>
                            <CardTitle className="text-2xl tabular-nums">
                                {totalSold}
                            </CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Addon Revenue</CardDescription>
                            <CardTitle className="text-2xl tabular-nums">
                                {currency} {totalRevenue.toFixed(2)}
                            </CardTitle>
                        </CardHeader>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Configured Addons & Warranties</CardTitle>
                        <CardDescription>
                            When shoppers select an addon on a product page, its
                            price is automatically added to the product line in
                            the cart and checkout order total.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="p-0">
                        <table className="w-full text-sm">
                            <thead className="border-b text-left text-muted-foreground">
                                <tr>
                                    <th className="p-4">Addon</th>
                                    <th className="p-4">Type</th>
                                    <th className="p-4">Applies To</th>
                                    <th className="p-4">Price</th>
                                    <th className="p-4">Sold / Revenue</th>
                                    <th className="p-4">Status</th>
                                    <th className="p-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {addons.map((addon) => {
                                    const meta =
                                        typeLabels[addon.type] ??
                                        typeLabels.warranty;
                                    const Icon = meta.icon;

                                    return (
                                        <tr key={addon.id}>
                                            <td className="p-4">
                                                <div className="flex items-center gap-2 font-medium">
                                                    <span>{addon.name}</span>
                                                    {addon.badge && (
                                                        <Badge
                                                            variant="outline"
                                                            className="text-[11px]"
                                                        >
                                                            {addon.badge}
                                                        </Badge>
                                                    )}
                                                </div>
                                                {addon.description && (
                                                    <p className="mt-0.5 line-clamp-1 text-xs text-muted-foreground">
                                                        {addon.description}
                                                    </p>
                                                )}
                                            </td>
                                            <td className="p-4">
                                                <span className="inline-flex items-center gap-1.5 text-xs font-medium">
                                                    <Icon className="size-3.5 text-muted-foreground" />
                                                    {meta.label}
                                                </span>
                                            </td>
                                            <td className="p-4">
                                                {addon.target_type ===
                                                    'all' && (
                                                    <Badge variant="secondary">
                                                        All Products
                                                    </Badge>
                                                )}
                                                {addon.target_type ===
                                                    'products' && (
                                                    <div className="flex flex-wrap gap-1">
                                                        {addon.targets.map(
                                                            (t) => (
                                                                <Badge
                                                                    key={t.id}
                                                                    variant="outline"
                                                                >
                                                                    {t.name}
                                                                </Badge>
                                                            ),
                                                        )}
                                                        {addon.targets
                                                            .length === 0 && (
                                                            <span className="text-xs text-muted-foreground">
                                                                {
                                                                    addon
                                                                        .target_ids
                                                                        .length
                                                                }{' '}
                                                                product(s)
                                                            </span>
                                                        )}
                                                    </div>
                                                )}
                                                {addon.target_type ===
                                                    'categories' && (
                                                    <div className="flex flex-wrap gap-1">
                                                        {addon.targets.map(
                                                            (t) => (
                                                                <Badge
                                                                    key={t.id}
                                                                    variant="outline"
                                                                >
                                                                    Category:{' '}
                                                                    {t.name}
                                                                </Badge>
                                                            ),
                                                        )}
                                                        {addon.targets
                                                            .length === 0 && (
                                                            <span className="text-xs text-muted-foreground">
                                                                {
                                                                    addon
                                                                        .target_ids
                                                                        .length
                                                                }{' '}
                                                                category(s)
                                                            </span>
                                                        )}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="p-4 font-semibold tabular-nums">
                                                +{currency}{' '}
                                                {Number(addon.price).toFixed(2)}
                                            </td>
                                            <td className="p-4 text-xs text-muted-foreground tabular-nums">
                                                {addon.units_sold} sold ·{' '}
                                                {currency}{' '}
                                                {Number(addon.revenue).toFixed(
                                                    2,
                                                )}
                                            </td>
                                            <td className="p-4">
                                                <Badge
                                                    variant={
                                                        addon.is_active
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {addon.is_active
                                                        ? 'Active'
                                                        : 'Disabled'}
                                                </Badge>
                                            </td>
                                            <td className="space-x-1 p-4 text-right">
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    asChild
                                                >
                                                    <Link
                                                        href={`/admin/m/modrixcommerce/product-addons/${addon.id}/edit`}
                                                    >
                                                        <Pencil className="size-4" />
                                                    </Link>
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        remove(addon)
                                                    }
                                                >
                                                    <Trash2 className="size-4" />
                                                </Button>
                                            </td>
                                        </tr>
                                    );
                                })}
                                {addons.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={7}
                                            className="p-8 text-center text-muted-foreground"
                                        >
                                            No warranties or product addons
                                            created yet. Click &ldquo;New
                                            Addon&rdquo; to add your first one.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </AdminPage>
        </>
    );
}
