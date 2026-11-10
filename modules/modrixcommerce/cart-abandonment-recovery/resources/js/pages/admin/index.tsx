import { Form, Head, router } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowUpRight,
    CheckCircle2,
    Clock,
    Copy,
    Download,
    Eye,
    Mail,
    RefreshCw,
    Search,
    ShoppingBag,
    ShoppingCart,
    TrendingUp,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/admin/empty-state';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';

type CartItemSnapshot = {
    variant_id: number;
    product_id?: number;
    product_name: string;
    variant_name?: string | null;
    sku?: string;
    quantity: number;
    unit_price: string;
    formatted_price: string;
    image_url?: string | null;
    url?: string | null;
};

type AbandonedCartRow = {
    id: number;
    email: string;
    customer_name: string | null;
    phone: string | null;
    formatted_subtotal: string;
    currency: string;
    status: 'abandoned' | 'in_recovery' | 'recovered' | 'lost' | 'unsubscribed';
    items_count: number;
    items_snapshot: CartItemSnapshot[];
    abandoned_at: string;
    abandoned_date: string;
    recovered_at: string | null;
    recovered_order_number: string | null;
    recovery_revenue: string | null;
    emails_sent_count: number;
    last_email_sent_at: string | null;
    next_email_scheduled_at: string | null;
    recovery_url: string;
    send_email_url: string;
    mark_lost_url: string;
    mark_recovered_url: string;
};

type Props = {
    stats: {
        currency: string;
        total_abandoned: number;
        total_recovered: number;
        total_lost: number;
        active_abandoned: number;
        recovered_revenue: number;
        formatted_recovered_revenue: string;
        abandoned_revenue: number;
        formatted_abandoned_revenue: string;
        recovery_rate: number;
        total_emails_sent: number;
        open_rate: number;
        click_rate: number;
    };
    carts: {
        data: AbandonedCartRow[];
        links: { url: string | null; label: string; active: boolean }[];
        current_page: number;
        last_page: number;
        total: number;
    };
    filters: {
        status: string;
        search: string;
    };
    scanUrl: string;
    exportUrl: string;
};

export default function CartAbandonmentAdmin({
    stats,
    carts,
    filters,
    scanUrl,
    exportUrl,
}: Props) {
    const [selectedCart, setSelectedCart] = useState<AbandonedCartRow | null>(null);
    const [search, setSearch] = useState(filters.search || '');
    const [isScanning, setIsScanning] = useState(false);
    const [copiedId, setCopiedId] = useState<number | null>(null);

    function handleSearch(e: React.FormEvent) {
        e.preventDefault();
        router.get(
            window.location.pathname,
            { status: filters.status, search },
            { preserveState: true }
        );
    }

    function handleStatusFilter(status: string) {
        router.get(
            window.location.pathname,
            { status, search: filters.search },
            { preserveState: true }
        );
    }

    function handleScan() {
        setIsScanning(true);
        router.post(scanUrl, {}, {
            preserveScroll: true,
            onFinish: () => setIsScanning(false),
        });
    }

    function copyRecoveryUrl(cart: AbandonedCartRow) {
        navigator.clipboard.writeText(cart.recovery_url);
        setCopiedId(cart.id);
        setTimeout(() => setCopiedId(null), 2000);
    }

    function statusBadge(status: AbandonedCartRow['status']) {
        switch (status) {
            case 'recovered':
                return (
                    <Badge className="bg-emerald-500/15 text-emerald-700 hover:bg-emerald-500/25 border-emerald-200 dark:text-emerald-400">
                        <CheckCircle2 className="mr-1 size-3" /> Recovered
                    </Badge>
                );
            case 'in_recovery':
                return (
                    <Badge className="bg-blue-500/15 text-blue-700 hover:bg-blue-500/25 border-blue-200 dark:text-blue-400">
                        <TrendingUp className="mr-1 size-3" /> In Recovery
                    </Badge>
                );
            case 'abandoned':
                return (
                    <Badge className="bg-amber-500/15 text-amber-700 hover:bg-amber-500/25 border-amber-200 dark:text-amber-400">
                        <Clock className="mr-1 size-3" /> Abandoned
                    </Badge>
                );
            case 'lost':
                return (
                    <Badge variant="outline" className="text-muted-foreground">
                        <XCircle className="mr-1 size-3" /> Lost
                    </Badge>
                );
            case 'unsubscribed':
                return (
                    <Badge variant="outline" className="border-purple-300 text-purple-700 dark:text-purple-400">
                        Unsubscribed
                    </Badge>
                );
            default:
                return <Badge variant="outline">{status}</Badge>;
        }
    }

    return (
        <>
            <Head title="Cart Abandonment Recovery" />
            <AdminPage>
                <PageHeader
                    title="Cart Abandonment Recovery"
                    description="Automatically re-engage shoppers who left items behind with personalized sequences, exit-intent modals, and one-click cart restoration."
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <Button
                                variant="outline"
                                onClick={handleScan}
                                disabled={isScanning}
                            >
                                <RefreshCw className={`size-4 mr-2 ${isScanning ? 'animate-spin' : ''}`} />
                                Run Scanner
                            </Button>
                            <Button variant="outline" asChild>
                                <a href={exportUrl}>
                                    <Download className="size-4 mr-2" /> Export CSV
                                </a>
                            </Button>
                        </div>
                    }
                />

                {/* KPI Metrics Dashboard */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardContent className="p-5">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                    Recovered Revenue
                                </span>
                                <div className="rounded-full bg-emerald-500/10 p-2 text-emerald-600 dark:text-emerald-400">
                                    <TrendingUp className="size-4" />
                                </div>
                            </div>
                            <div className="mt-2 flex items-baseline gap-2">
                                <span className="text-2xl font-bold tracking-tight">
                                    {stats.formatted_recovered_revenue}
                                </span>
                                <span className="text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                                    {stats.total_recovered} orders
                                </span>
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Total sales saved through recovery
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-5">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                    Recovery Rate
                                </span>
                                <div className="rounded-full bg-blue-500/10 p-2 text-blue-600 dark:text-blue-400">
                                    <ArrowUpRight className="size-4" />
                                </div>
                            </div>
                            <div className="mt-2 flex items-baseline gap-2">
                                <span className="text-2xl font-bold tracking-tight">
                                    {stats.recovery_rate}%
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    conversion
                                </span>
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {stats.total_recovered} of {stats.total_abandoned} abandoned carts
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-5">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                    Potential Lost Value
                                </span>
                                <div className="rounded-full bg-amber-500/10 p-2 text-amber-600 dark:text-amber-400">
                                    <ShoppingCart className="size-4" />
                                </div>
                            </div>
                            <div className="mt-2 flex items-baseline gap-2">
                                <span className="text-2xl font-bold tracking-tight">
                                    {stats.formatted_abandoned_revenue}
                                </span>
                                <span className="text-xs text-amber-600 dark:text-amber-400">
                                    {stats.active_abandoned} active
                                </span>
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Unclaimed items in abandoned carts
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-5">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                    Email Campaign Stats
                                </span>
                                <div className="rounded-full bg-indigo-500/10 p-2 text-indigo-600 dark:text-indigo-400">
                                    <Mail className="size-4" />
                                </div>
                            </div>
                            <div className="mt-2 flex items-baseline gap-2">
                                <span className="text-2xl font-bold tracking-tight">
                                    {stats.total_emails_sent}
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    sent
                                </span>
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {stats.open_rate}% open &middot; {stats.click_rate}% click rate
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters & Search */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex flex-wrap items-center gap-1.5">
                        {[
                            { key: 'all', label: 'All Carts' },
                            { key: 'abandoned', label: 'Abandoned' },
                            { key: 'in_recovery', label: 'In Recovery' },
                            { key: 'recovered', label: 'Recovered' },
                            { key: 'lost', label: 'Lost' },
                        ].map((tab) => (
                            <Button
                                key={tab.key}
                                size="sm"
                                variant={filters.status === tab.key ? 'default' : 'outline'}
                                onClick={() => handleStatusFilter(tab.key)}
                                className="h-8 text-xs font-medium"
                            >
                                {tab.label}
                            </Button>
                        ))}
                    </div>

                    <form onSubmit={handleSearch} className="flex items-center gap-2">
                        <div className="relative">
                            <Search className="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                            <Input
                                type="text"
                                placeholder="Search by email, name..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="h-9 w-60 pl-8 text-sm"
                            />
                        </div>
                        <Button type="submit" size="sm" variant="secondary" className="h-9">
                            Search
                        </Button>
                    </form>
                </div>

                {/* Table of Abandoned Carts */}
                {carts.data.length === 0 ? (
                    <EmptyState
                        title="No abandoned carts found"
                        description={
                            filters.status !== 'all' || filters.search
                                ? 'No results matched your search or status filter.'
                                : 'When shoppers leave items in their cart, they will appear here with automated recovery options.'
                        }
                    />
                ) : (
                    <div className="overflow-hidden rounded-lg border bg-card text-card-foreground shadow-sm">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="border-b bg-muted/50 text-xs uppercase text-muted-foreground">
                                    <tr>
                                        <th className="px-4 py-3">Customer</th>
                                        <th className="px-4 py-3">Cart Contents</th>
                                        <th className="px-4 py-3">Value</th>
                                        <th className="px-4 py-3">Status</th>
                                        <th className="px-4 py-3">Abandoned</th>
                                        <th className="px-4 py-3">Emails Sent</th>
                                        <th className="px-4 py-3 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {carts.data.map((cart) => (
                                        <tr key={cart.id} className="hover:bg-muted/40 transition-colors">
                                            <td className="px-4 py-3 font-medium">
                                                <div className="flex flex-col">
                                                    <span className="font-semibold text-foreground">
                                                        {cart.customer_name || 'Guest shopper'}
                                                    </span>
                                                    <span className="text-xs text-muted-foreground">
                                                        {cart.email}
                                                    </span>
                                                    {cart.phone && (
                                                        <span className="text-xs text-muted-foreground">
                                                            {cart.phone}
                                                        </span>
                                                    )}
                                                </div>
                                            </td>

                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-2">
                                                    <span className="font-medium text-foreground">
                                                        {cart.items_count} {cart.items_count === 1 ? 'item' : 'items'}
                                                    </span>
                                                    <div className="flex -space-x-2 overflow-hidden">
                                                        {cart.items_snapshot.slice(0, 3).map((item, idx) => (
                                                            item.image_url ? (
                                                                <img
                                                                    key={idx}
                                                                    src={item.image_url}
                                                                    alt={item.product_name}
                                                                    className="inline-block size-6 rounded-full border border-background object-cover"
                                                                />
                                                            ) : (
                                                                <div
                                                                    key={idx}
                                                                    className="inline-flex size-6 items-center justify-center rounded-full border border-background bg-muted text-[10px] font-semibold text-muted-foreground"
                                                                >
                                                                    {item.product_name.charAt(0)}
                                                                </div>
                                                            )
                                                        ))}
                                                    </div>
                                                </div>
                                            </td>

                                            <td className="px-4 py-3 font-semibold text-foreground">
                                                {cart.formatted_subtotal}
                                            </td>

                                            <td className="px-4 py-3">
                                                {statusBadge(cart.status)}
                                            </td>

                                            <td className="px-4 py-3 text-xs text-muted-foreground">
                                                <div>{cart.abandoned_at}</div>
                                                <div>{cart.abandoned_date}</div>
                                            </td>

                                            <td className="px-4 py-3 text-xs">
                                                <div className="flex items-center gap-1.5 font-medium">
                                                    <Mail className="size-3.5 text-muted-foreground" />
                                                    <span>{cart.emails_sent_count} / 3</span>
                                                </div>
                                                {cart.next_email_scheduled_at && cart.status === 'abandoned' && (
                                                    <div className="text-[11px] text-muted-foreground">
                                                        Next: {cart.next_email_scheduled_at}
                                                    </div>
                                                )}
                                            </td>

                                            <td className="px-4 py-3 text-right">
                                                <div className="flex items-center justify-end gap-1.5">
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() => setSelectedCart(cart)}
                                                        className="h-8 px-2"
                                                        title="View cart details"
                                                    >
                                                        <Eye className="size-4" />
                                                    </Button>

                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() => copyRecoveryUrl(cart)}
                                                        className="h-8 px-2"
                                                        title="Copy one-click recovery link"
                                                    >
                                                        {copiedId === cart.id ? (
                                                            <CheckCircle2 className="size-4 text-emerald-600" />
                                                        ) : (
                                                            <Copy className="size-4" />
                                                        )}
                                                    </Button>

                                                    {cart.status === 'abandoned' && (
                                                        <Form
                                                            action={cart.send_email_url}
                                                            method="post"
                                                            options={{ preserveScroll: true }}
                                                        >
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                className="h-8 px-2.5 text-xs"
                                                                title="Send recovery reminder email now"
                                                            >
                                                                <Mail className="size-3.5 mr-1" /> Send
                                                            </Button>
                                                        </Form>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination */}
                        {carts.last_page > 1 && (
                            <div className="flex items-center justify-between border-t px-4 py-3 text-xs text-muted-foreground">
                                <div>
                                    Showing page {carts.current_page} of {carts.last_page} ({carts.total} total)
                                </div>
                                <div className="flex gap-1">
                                    {carts.links.map((link, idx) => (
                                        <Button
                                            key={idx}
                                            size="sm"
                                            variant={link.active ? 'default' : 'outline'}
                                            disabled={!link.url}
                                            asChild={!!link.url}
                                            className="h-7 min-w-7 px-2 text-xs"
                                        >
                                            {link.url ? (
                                                <a
                                                    href={link.url}
                                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                                />
                                            ) : (
                                                <span dangerouslySetInnerHTML={{ __html: link.label }} />
                                            )}
                                        </Button>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                )}

                {/* Cart Details Modal */}
                <Dialog open={selectedCart !== null} onOpenChange={() => setSelectedCart(null)}>
                    <DialogContent className="max-w-2xl">
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2">
                                <ShoppingBag className="size-5" />
                                Abandoned Cart #{selectedCart?.id}
                            </DialogTitle>
                            <DialogDescription>
                                Customer details, cart items snapshot, and recovery actions.
                            </DialogDescription>
                        </DialogHeader>

                        {selectedCart && (
                            <div className="space-y-4 text-sm">
                                <div className="grid grid-cols-2 gap-3 rounded-lg border bg-muted/40 p-3 text-xs sm:grid-cols-4">
                                    <div>
                                        <span className="text-muted-foreground block">Customer:</span>
                                        <span className="font-semibold text-foreground">
                                            {selectedCart.customer_name || 'Guest'}
                                        </span>
                                    </div>
                                    <div>
                                        <span className="text-muted-foreground block">Email:</span>
                                        <span className="font-semibold text-foreground">
                                            {selectedCart.email}
                                        </span>
                                    </div>
                                    <div>
                                        <span className="text-muted-foreground block">Status:</span>
                                        <span className="mt-0.5 inline-block">
                                            {statusBadge(selectedCart.status)}
                                        </span>
                                    </div>
                                    <div>
                                        <span className="text-muted-foreground block">Total Value:</span>
                                        <span className="font-semibold text-foreground">
                                            {selectedCart.formatted_subtotal}
                                        </span>
                                    </div>
                                </div>

                                <div>
                                    <h4 className="font-semibold text-xs uppercase tracking-wider text-muted-foreground mb-2">
                                        Cart Items ({selectedCart.items_count})
                                    </h4>
                                    <div className="divide-y rounded-md border">
                                        {selectedCart.items_snapshot.map((item, i) => (
                                            <div key={i} className="flex items-center justify-between p-3 text-xs">
                                                <div className="flex items-center gap-3">
                                                    {item.image_url ? (
                                                        <img
                                                            src={item.image_url}
                                                            alt={item.product_name}
                                                            className="size-10 rounded border object-cover"
                                                        />
                                                    ) : (
                                                        <div className="flex size-10 items-center justify-center rounded border bg-muted font-bold text-muted-foreground">
                                                            {item.product_name.charAt(0)}
                                                        </div>
                                                    )}
                                                    <div>
                                                        <div className="font-medium text-foreground">
                                                            {item.product_name}
                                                        </div>
                                                        {item.variant_name && (
                                                            <div className="text-muted-foreground">
                                                                {item.variant_name}
                                                            </div>
                                                        )}
                                                        {item.sku && (
                                                            <div className="text-[11px] text-muted-foreground font-mono">
                                                                SKU: {item.sku}
                                                            </div>
                                                        )}
                                                    </div>
                                                </div>
                                                <div className="text-right">
                                                    <div className="font-semibold text-foreground">
                                                        {item.formatted_price}
                                                    </div>
                                                    <div className="text-muted-foreground">
                                                        Qty: {item.quantity}
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>

                                <div className="rounded-lg border p-3 bg-muted/20">
                                    <span className="text-xs font-semibold text-foreground block mb-1">
                                        One-Click Recovery Link
                                    </span>
                                    <div className="flex items-center gap-2">
                                        <Input
                                            readOnly
                                            value={selectedCart.recovery_url}
                                            className="h-8 text-xs font-mono bg-background"
                                        />
                                        <Button
                                            size="sm"
                                            variant="secondary"
                                            onClick={() => copyRecoveryUrl(selectedCart)}
                                            className="h-8 shrink-0"
                                        >
                                            {copiedId === selectedCart.id ? 'Copied!' : 'Copy'}
                                        </Button>
                                    </div>
                                </div>

                                <div className="flex flex-wrap items-center justify-between gap-2 pt-2 border-t">
                                    <div className="flex items-center gap-2">
                                        {selectedCart.status !== 'lost' && selectedCart.status !== 'recovered' && (
                                            <Form
                                                action={selectedCart.mark_lost_url}
                                                method="post"
                                                options={{ preserveScroll: true }}
                                            >
                                                <Button size="sm" variant="ghost" className="text-xs text-muted-foreground">
                                                    Mark as Lost
                                                </Button>
                                            </Form>
                                        )}
                                        {selectedCart.status !== 'recovered' && (
                                            <Form
                                                action={selectedCart.mark_recovered_url}
                                                method="post"
                                                options={{ preserveScroll: true }}
                                            >
                                                <Button size="sm" variant="ghost" className="text-xs text-emerald-600">
                                                    Mark as Recovered
                                                </Button>
                                            </Form>
                                        )}
                                    </div>

                                    <div className="flex items-center gap-2">
                                        <Button size="sm" variant="outline" onClick={() => setSelectedCart(null)}>
                                            Close
                                        </Button>
                                        <Form
                                            action={selectedCart.send_email_url}
                                            method="post"
                                            options={{ preserveScroll: true }}
                                        >
                                            <Button size="sm">
                                                <Mail className="size-4 mr-1.5" /> Send Reminder Email
                                            </Button>
                                        </Form>
                                    </div>
                                </div>
                            </div>
                        )}
                    </DialogContent>
                </Dialog>
            </AdminPage>
        </>
    );
}
