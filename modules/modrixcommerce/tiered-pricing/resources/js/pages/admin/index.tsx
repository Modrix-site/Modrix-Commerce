import { Head, Link, router } from '@inertiajs/react';
import { Layers, Plus, Settings, Trash2 } from 'lucide-react';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

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
    created_at: string;
};

type Props = {
    rules: {
        data: Rule[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        total: number;
    };
    settings: {
        enabled: boolean;
        table_heading: string;
        table_subheading: string;
        show_savings_badge: boolean;
        highlight_active_tier: boolean;
    };
    stats: {
        total_rules: number;
        active_rules: number;
    };
    urls: {
        create: string;
        settings: string;
    };
};

export default function TieredPricingIndex({
    rules,
    settings,
    stats,
    urls: _urls,
}: Props) {
    const handleToggle = (id: number) => {
        router.post(
            `/admin/m/modrixcommerce/tiered-pricing/${id}/toggle`,
            {},
            { preserveScroll: true },
        );
    };

    const handleDelete = (id: number) => {
        if (
            confirm('Are you sure you want to delete this tiered pricing rule?')
        ) {
            router.delete(`/admin/m/modrixcommerce/tiered-pricing/${id}`, {
                preserveScroll: true,
            });
        }
    };

    return (
        <>
            <Head title="Tiered Pricing & Volume Discounts" />

            <AdminPage>
                <PageHeader
                    title="Tiered Pricing & Volume Discounts"
                    description="Configure quantity-break bulk discounts and volume tier tables to incentivize larger order sizes."
                    actions={
                        <div className="flex items-center gap-2">
                            <Link href="/admin/m/modrixcommerce/tiered-pricing/settings">
                                <Button variant="outline" size="sm">
                                    <Settings className="mr-2 size-4" />
                                    Settings
                                </Button>
                            </Link>
                            <Link href="/admin/m/modrixcommerce/tiered-pricing/create">
                                <Button
                                    size="sm"
                                    className="bg-blue-600 text-white hover:bg-blue-700"
                                >
                                    <Plus className="mr-2 size-4" />
                                    New Tiered Rule
                                </Button>
                            </Link>
                        </div>
                    }
                />

                {/* Overview Stats */}
                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Total Pricing Rules
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {stats.total_rules}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Configured volume discount rules
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Active Rules
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-blue-600">
                                {stats.active_rules}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Live on storefront products
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Storefront Status
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center gap-2">
                                <Badge
                                    variant={
                                        settings.enabled
                                            ? 'default'
                                            : 'secondary'
                                    }
                                >
                                    {settings.enabled ? 'Active' : 'Disabled'}
                                </Badge>
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {settings.enabled
                                    ? 'Volume tables showing on products'
                                    : 'Module is globally disabled'}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Rules Table */}
                <Card>
                    <CardHeader>
                        <CardTitle>Volume Discount Rules</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {rules.data.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-12 text-center">
                                <Layers className="size-12 text-muted-foreground/40" />
                                <h3 className="mt-4 text-base font-semibold">
                                    No tiered pricing rules yet
                                </h3>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Create your first quantity-break rule to
                                    encourage bulk orders.
                                </p>
                                <Link
                                    href="/admin/m/modrixcommerce/tiered-pricing/create"
                                    className="mt-4"
                                >
                                    <Button
                                        size="sm"
                                        className="bg-blue-600 text-white hover:bg-blue-700"
                                    >
                                        <Plus className="mr-2 size-4" />
                                        Create Rule
                                    </Button>
                                </Link>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Rule Title</TableHead>
                                            <TableHead>Target</TableHead>
                                            <TableHead>Discount Type</TableHead>
                                            <TableHead>Tiers</TableHead>
                                            <TableHead>Priority</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead className="text-right">
                                                Actions
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {rules.data.map((rule) => (
                                            <TableRow key={rule.id}>
                                                <TableCell className="font-medium">
                                                    <div>
                                                        <span className="font-semibold text-foreground">
                                                            {rule.title}
                                                        </span>
                                                        {rule.description && (
                                                            <p className="line-clamp-1 text-xs text-muted-foreground">
                                                                {
                                                                    rule.description
                                                                }
                                                            </p>
                                                        )}
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge
                                                        variant="outline"
                                                        className="capitalize"
                                                    >
                                                        {rule.applies_to ===
                                                        'all'
                                                            ? 'All Products'
                                                            : `${rule.applies_to} (${rule.target_ids.length})`}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge
                                                        variant="secondary"
                                                        className="capitalize"
                                                    >
                                                        {rule.discount_type ===
                                                        'percent'
                                                            ? '% Off'
                                                            : 'Fixed / Unit'}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex flex-wrap gap-1">
                                                        {rule.tiers.map(
                                                            (t, idx) => (
                                                                <span
                                                                    key={idx}
                                                                    className="rounded bg-muted px-1.5 py-0.5 font-mono text-[11px] text-muted-foreground"
                                                                >
                                                                    {t.min_qty}
                                                                    {t.max_qty
                                                                        ? `-${t.max_qty}`
                                                                        : '+'}
                                                                    :{' '}
                                                                    {rule.discount_type ===
                                                                    'percent'
                                                                        ? `${t.discount}%`
                                                                        : `$${t.discount}`}
                                                                </span>
                                                            ),
                                                        )}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="font-mono text-xs">
                                                    {rule.priority}
                                                </TableCell>
                                                <TableCell>
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            handleToggle(
                                                                rule.id,
                                                            )
                                                        }
                                                        className="cursor-pointer"
                                                    >
                                                        {rule.is_active ? (
                                                            <Badge className="bg-blue-600 text-white hover:bg-blue-700">
                                                                Active
                                                            </Badge>
                                                        ) : (
                                                            <Badge variant="secondary">
                                                                Inactive
                                                            </Badge>
                                                        )}
                                                    </button>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <div className="flex items-center justify-end gap-2">
                                                        <Link
                                                            href={`/admin/m/modrixcommerce/tiered-pricing/${rule.id}/edit`}
                                                        >
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                            >
                                                                Edit
                                                            </Button>
                                                        </Link>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() =>
                                                                handleDelete(
                                                                    rule.id,
                                                                )
                                                            }
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

TieredPricingIndex.layout = {
    breadcrumbs: [
        {
            title: 'Tiered Pricing',
            href: '/admin/m/modrixcommerce/tiered-pricing',
        },
    ],
};
