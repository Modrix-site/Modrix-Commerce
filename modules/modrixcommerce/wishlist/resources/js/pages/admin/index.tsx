import { Head, Link } from '@inertiajs/react';
import { Heart, Settings, TrendingUp, Users } from 'lucide-react';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

type TopProduct = {
    id: number;
    name: string;
    sku: string;
    image: string | null;
    saves_count: number;
    price_formatted: string;
};

type Props = {
    stats: {
        total_wishlists: number;
        customer_wishlists: number;
        guest_wishlists: number;
        total_saved_items: number;
    };
    top_products: TopProduct[];
    settings: {
        enabled: boolean;
        require_login: boolean;
        allow_sharing: boolean;
        button_label: string;
    };
    urls: {
        settings: string;
    };
};

export default function WishlistAdminIndex({ stats, top_products, settings }: Props) {
    return (
        <>
            <Head title="Wishlist Analytics" />

            <AdminPage>
                <PageHeader
                    title="Wishlist Analytics & Customer Demand"
                    description="Monitor high-intent customer wishlists, save-for-later trends, and restock demand."
                    actions={
                        <Link href="/admin/m/modrixcommerce/wishlist/settings">
                            <Button variant="outline" size="sm">
                                <Settings className="mr-2 size-4" />
                                Wishlist Settings
                            </Button>
                        </Link>
                    }
                />

                {/* Metrics Grid */}
                <div className="grid gap-4 md:grid-cols-4">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Total Saved Items
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center justify-between">
                                <div className="text-2xl font-bold text-rose-600">
                                    {stats.total_saved_items}
                                </div>
                                <Heart className="size-5 text-rose-500 fill-rose-500/20" />
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">Across all customer lists</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Active Wishlists
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.total_wishlists}</div>
                            <p className="mt-1 text-xs text-muted-foreground">Combined guest & accounts</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Customer Wishlists
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center justify-between">
                                <div className="text-2xl font-bold">{stats.customer_wishlists}</div>
                                <Users className="size-4 text-muted-foreground" />
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Signed-in persistent accounts
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Module Status
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center gap-2">
                                <Badge variant={settings.enabled ? 'default' : 'secondary'}>
                                    {settings.enabled ? 'Enabled' : 'Disabled'}
                                </Badge>
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {settings.require_login ? 'Sign-in required' : 'Guest saves enabled'}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Most Wishlisted Products */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Most Wishlisted Products</CardTitle>
                                <CardDescription>
                                    Items with highest shopper interest and future purchase intent.
                                </CardDescription>
                            </div>
                            <TrendingUp className="size-5 text-muted-foreground" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        {top_products.length === 0 ? (
                            <div className="py-12 text-center">
                                <Heart className="mx-auto size-10 text-muted-foreground/30" />
                                <h3 className="mt-3 text-base font-semibold">No saved items recorded yet</h3>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    When shoppers tap the wishlist button, their saved items will appear here.
                                </p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Product</TableHead>
                                            <TableHead>SKU</TableHead>
                                            <TableHead>Price</TableHead>
                                            <TableHead className="text-right">Wishlist Saves</TableHead>
                                            <TableHead className="text-right">Action</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {top_products.map((p) => (
                                            <TableRow key={p.id}>
                                                <TableCell className="font-medium">
                                                    <div className="flex items-center gap-3">
                                                        <div className="size-10 shrink-0 overflow-hidden rounded-md bg-muted">
                                                            {p.image ? (
                                                                <img
                                                                    src={p.image}
                                                                    alt={p.name}
                                                                    className="size-full object-cover"
                                                                />
                                                            ) : (
                                                                <div className="flex size-full items-center justify-center text-[10px] text-muted-foreground">
                                                                    No img
                                                                </div>
                                                            )}
                                                        </div>
                                                        <span className="font-semibold text-foreground">
                                                            {p.name}
                                                        </span>
                                                    </div>
                                                </TableCell>
                                                <TableCell className="font-mono text-xs">{p.sku}</TableCell>
                                                <TableCell>{p.price_formatted}</TableCell>
                                                <TableCell className="text-right">
                                                    <Badge className="bg-rose-100 font-bold text-rose-800 dark:bg-rose-950 dark:text-rose-200">
                                                        {p.saves_count} {p.saves_count === 1 ? 'save' : 'saves'}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <Link href={`/admin/catalog/products/${p.id}/edit`}>
                                                        <Button variant="ghost" size="sm">
                                                            Inspect
                                                        </Button>
                                                    </Link>
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

WishlistAdminIndex.layout = {
    breadcrumbs: [
        {
            title: 'Wishlist Analytics',
            href: '/admin/m/modrixcommerce/wishlist',
        },
    ],
};
