import { Head, Link, router } from '@inertiajs/react';
import {
    Percent,
    Plus,
    Settings,
    ShoppingBag,
    Sparkles,
    Trash2,
    TrendingUp,
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

type Deal = {
    id: number;
    title: string;
    badge_text: string | null;
    trigger_type: string;
    bundle_product_ids: number[];
    discount_type: 'percent' | 'fixed';
    discount_value: string;
    priority: number;
    is_active: boolean;
    views_count: number;
    conversions_count: number;
    created_at: string;
};

type Props = {
    deals: {
        data: Deal[];
        links: any[];
    };
    settings: {
        enabled: boolean;
        heading: string;
        auto_bundle_fallback: boolean;
    };
    stats: {
        total_deals: number;
        active_deals: number;
        total_views: number;
        total_conversions: number;
        conversion_rate: number;
    };
    urls: {
        create: string;
        settings: string;
    };
};

export default function BuyTogetherIndex({ deals, settings, stats, urls }: Props) {
    const handleToggle = (id: number) => {
        router.post(`/admin/m/modrixcommerce/buy-together-save/${id}/toggle`, {}, { preserveScroll: true });
    };

    const handleDelete = (id: number) => {
        if (confirm('Are you sure you want to delete this bundle deal?')) {
            router.delete(`/admin/m/modrixcommerce/buy-together-save/${id}`, { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title="Buy Together & Save Deals" />

            <AdminPage>
                <PageHeader
                    title="Buy Together & Save"
                    description="Create curated product bundle deals with instant discounts on single product pages."
                    actions={
                        <div className="flex items-center gap-2">
                            <Link href={urls.settings}>
                                <Button variant="outline" size="sm">
                                    <Settings className="mr-2 size-4" />
                                    Settings
                                </Button>
                            </Link>
                            <Link href={urls.create}>
                                <Button size="sm" className="bg-emerald-600 hover:bg-emerald-700 text-white">
                                    <Plus className="mr-2 size-4" />
                                    New Bundle Deal
                                </Button>
                            </Link>
                        </div>
                    }
                />

                {/* Stats Overview */}
                <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">Active Deals</CardTitle>
                            <Sparkles className="size-4 text-emerald-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {stats.active_deals} <span className="text-xs font-normal text-muted-foreground">/ {stats.total_deals}</span>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                {settings.enabled ? 'Module is globally enabled' : 'Module is disabled'}
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">Total Views</CardTitle>
                            <TrendingUp className="size-4 text-blue-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.total_views}</div>
                            <p className="text-xs text-muted-foreground">Shopper impressions on product pages</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">Bundle Conversions</CardTitle>
                            <ShoppingBag className="size-4 text-emerald-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.total_conversions}</div>
                            <p className="text-xs text-muted-foreground">Bundles added to cart</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">Conversion Rate</CardTitle>
                            <Percent className="size-4 text-purple-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.conversion_rate}%</div>
                            <p className="text-xs text-muted-foreground">Clicks per impression</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Deals Table Card */}
                <Card>
                    <CardHeader>
                        <CardTitle>Bundle Deals</CardTitle>
                        <CardDescription>
                            Curated and promotional bundles currently displayed on product pages.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {deals.data.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-12 text-center">
                                <Sparkles className="size-10 text-muted-foreground/60" />
                                <h3 className="mt-4 text-lg font-semibold">No bundle deals created yet</h3>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Create your first "Buy Together & Save" offer to bundle companion accessories or products.
                                </p>
                                <div className="mt-4">
                                    <Link href={urls.create}>
                                        <Button className="bg-emerald-600 hover:bg-emerald-700 text-white">
                                            <Plus className="mr-2 size-4" />
                                            Create Bundle Deal
                                        </Button>
                                    </Link>
                                </div>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Deal Title</TableHead>
                                            <TableHead>Trigger</TableHead>
                                            <TableHead>Bundle Products</TableHead>
                                            <TableHead>Savings Offer</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead className="text-right">Actions</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {deals.data.map((deal) => (
                                            <TableRow key={deal.id}>
                                                <TableCell className="font-medium">
                                                    <div>{deal.title}</div>
                                                    {deal.badge_text && (
                                                        <span className="text-xs text-muted-foreground">
                                                            Badge: {deal.badge_text}
                                                        </span>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant="outline" className="capitalize">
                                                        {deal.trigger_type}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <span className="text-sm font-semibold">
                                                        {deal.bundle_product_ids?.length || 0} products
                                                    </span>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge className="bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/30">
                                                        {deal.discount_type === 'percent'
                                                            ? `${parseFloat(deal.discount_value)}% OFF`
                                                            : `$${parseFloat(deal.discount_value)} OFF`}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <button
                                                        type="button"
                                                        onClick={() => handleToggle(deal.id)}
                                                        className="cursor-pointer"
                                                    >
                                                        {deal.is_active ? (
                                                            <Badge className="bg-emerald-600 text-white hover:bg-emerald-700">
                                                                Active
                                                            </Badge>
                                                        ) : (
                                                            <Badge variant="secondary">Inactive</Badge>
                                                        )}
                                                    </button>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <div className="flex items-center justify-end gap-2">
                                                        <Link
                                                            href={`/admin/m/modrixcommerce/buy-together-save/${deal.id}/edit`}
                                                        >
                                                            <Button variant="outline" size="sm">
                                                                Edit
                                                            </Button>
                                                        </Link>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => handleDelete(deal.id)}
                                                            className="text-destructive hover:text-destructive"
                                                        >
                                                            <Trash2 className="size-4" />
                                                        </Button>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </AdminPage>
        </>
    );
}
