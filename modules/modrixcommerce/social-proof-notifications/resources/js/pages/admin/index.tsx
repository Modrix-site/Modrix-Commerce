import { Head, router } from '@inertiajs/react';
import {
    Activity,
    Bell,
    CheckCircle2,
    Clock,
    Eye,
    Flame,
    MousePointerClick,
    Plus,
    RefreshCw,
    Search,
    ShoppingBag,
    Sparkles,
    Trash2,
    Users,
    Zap,
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

type SocialEvent = {
    id: number;
    eventType: string;
    productName: string;
    productId: number | null;
    productImageUrl?: string | null;
    productUrl?: string | null;
    customerName?: string | null;
    maskedName?: string | null;
    customerCity?: string | null;
    customerCountry?: string | null;
    location?: string | null;
    message?: string | null;
    timeAgo: string;
    occurredAt?: string | null;
    isActive: boolean;
    isSimulated: boolean;
    impressionsCount: number;
    clicksCount: number;
    ctr: number;
};

type Metrics = {
    totalEvents: number;
    activeEvents: number;
    totalImpressions: number;
    totalClicks: number;
    ctr: number;
    recentPurchasesCount: number;
    activeViewersNow: number;
};

type Props = {
    metrics: Metrics;
    events: {
        data: SocialEvent[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        total: number;
        current_page: number;
        last_page: number;
    };
    filters: {
        type: string;
        status: string;
        search: string;
    };
    config: Record<string, unknown>;
    availableProducts: Array<{ id: number; name: string; slug: string }>;
};

export default function SocialProofAdminIndex({
    metrics,
    events,
    filters,
    availableProducts,
}: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [createOpen, setCreateOpen] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [seeding, setSeeding] = useState(false);

    // Create form state
    const [formType, setFormType] = useState('recent_purchase');
    const [selectedProductId, setSelectedProductId] = useState<number | ''>('');
    const [productName, setProductName] = useState('');
    const [customerName, setCustomerName] = useState('');
    const [customerCity, setCustomerCity] = useState('');
    const [customerCountry] = useState('US');
    const [customMessage, setCustomMessage] = useState('');

    function handleSearch(e: React.FormEvent) {
        e.preventDefault();
        router.get(
            '/admin/m/modrixcommerce/social-proof-notifications',
            {
                ...filters,
                search,
            },
            { preserveState: true },
        );
    }

    function handleFilterStatus(status: string) {
        router.get(
            '/admin/m/modrixcommerce/social-proof-notifications',
            {
                ...filters,
                status: status === filters.status ? '' : status,
            },
            { preserveState: true },
        );
    }

    function handleFilterType(type: string) {
        router.get(
            '/admin/m/modrixcommerce/social-proof-notifications',
            {
                ...filters,
                type: type === filters.type ? '' : type,
            },
            { preserveState: true },
        );
    }

    function handleToggle(event: SocialEvent) {
        router.post(
            `/admin/m/modrixcommerce/social-proof-notifications/${event.id}/toggle`,
            {},
            { preserveScroll: true },
        );
    }

    function handleDelete(event: SocialEvent) {
        if (
            !confirm(
                `Are you sure you want to delete this notification for "${event.productName}"?`,
            )
        ) {
            return;
        }

        router.delete(
            `/admin/m/modrixcommerce/social-proof-notifications/${event.id}`,
            { preserveScroll: true },
        );
    }

    function handleSeedSample() {
        setSeeding(true);
        router.post(
            '/admin/m/modrixcommerce/social-proof-notifications/seed-sample',
            {},
            {
                preserveScroll: true,
                onFinish: () => setSeeding(false),
            },
        );
    }

    function handleProductSelect(idStr: string) {
        if (!idStr) {
            setSelectedProductId('');
            return;
        }
        const id = parseInt(idStr, 10);
        setSelectedProductId(id);
        const p = availableProducts.find((item) => item.id === id);
        if (p) {
            setProductName(p.name);
        }
    }

    function handleCreateSubmit(e: React.FormEvent) {
        e.preventDefault();
        setSubmitting(true);

        router.post(
            '/admin/m/modrixcommerce/social-proof-notifications',
            {
                event_type: formType,
                product_id: selectedProductId || null,
                product_name: productName,
                customer_name: customerName || null,
                customer_city: customerCity || null,
                customer_country: customerCountry || null,
                message: customMessage || null,
            },
            {
                onSuccess: () => {
                    setCreateOpen(false);
                    setProductName('');
                    setSelectedProductId('');
                    setCustomerName('');
                    setCustomerCity('');
                    setCustomMessage('');
                },
                onFinish: () => setSubmitting(false),
            },
        );
    }

    return (
        <AdminPage>
            <Head title="Social Proof Notifications" />

            <PageHeader
                title="Social Proof Notifications"
                description="Live buyer activity alerts, realtime product viewer counters, and urgency notifications."
                actions={
                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleSeedSample}
                            disabled={seeding}
                            className="gap-1.5"
                        >
                            <RefreshCw
                                className={`h-3.5 w-3.5 ${seeding ? 'animate-spin' : ''}`}
                            />
                            <span>Seed Sample Data</span>
                        </Button>

                        <Button
                            size="sm"
                            onClick={() => setCreateOpen(true)}
                            className="gap-1.5 bg-indigo-600 text-white hover:bg-indigo-700"
                        >
                            <Plus className="h-3.5 w-3.5" />
                            <span>Create Alert</span>
                        </Button>
                    </div>
                }
            />

            {/* KPI Metrics Cards */}
            <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Card className="border-gray-200/80 shadow-xs">
                    <CardContent className="flex items-center justify-between p-4 sm:p-5">
                        <div>
                            <p className="text-xs font-medium tracking-wider text-gray-500 uppercase">
                                Active Notifications
                            </p>
                            <h3 className="mt-1 text-2xl font-bold text-gray-900">
                                {metrics.activeEvents}
                                <span className="ml-1.5 text-xs font-normal text-gray-400">
                                    / {metrics.totalEvents} total
                                </span>
                            </h3>
                            <p className="mt-1 flex items-center gap-1 text-xs font-medium text-emerald-600">
                                <CheckCircle2 className="h-3 w-3" />
                                {metrics.recentPurchasesCount} verified orders
                            </p>
                        </div>
                        <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                            <Bell className="h-5 w-5" />
                        </div>
                    </CardContent>
                </Card>

                <Card className="border-gray-200/80 shadow-xs">
                    <CardContent className="flex items-center justify-between p-4 sm:p-5">
                        <div>
                            <p className="text-xs font-medium tracking-wider text-gray-500 uppercase">
                                Total Impressions
                            </p>
                            <h3 className="mt-1 text-2xl font-bold text-gray-900">
                                {metrics.totalImpressions.toLocaleString()}
                            </h3>
                            <p className="mt-1 text-xs text-gray-500">
                                Storefront popup displays
                            </p>
                        </div>
                        <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                            <Eye className="h-5 w-5" />
                        </div>
                    </CardContent>
                </Card>

                <Card className="border-gray-200/80 shadow-xs">
                    <CardContent className="flex items-center justify-between p-4 sm:p-5">
                        <div>
                            <p className="text-xs font-medium tracking-wider text-gray-500 uppercase">
                                Clicks & CTR
                            </p>
                            <h3 className="mt-1 text-2xl font-bold text-gray-900">
                                {metrics.totalClicks.toLocaleString()}
                                <span className="ml-2 rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-600">
                                    {metrics.ctr}% CTR
                                </span>
                            </h3>
                            <p className="mt-1 text-xs text-gray-500">
                                Shoppers engaged with toasts
                            </p>
                        </div>
                        <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                            <MousePointerClick className="h-5 w-5" />
                        </div>
                    </CardContent>
                </Card>

                <Card className="border-gray-200/80 shadow-xs">
                    <CardContent className="flex items-center justify-between p-4 sm:p-5">
                        <div>
                            <p className="text-xs font-medium tracking-wider text-gray-500 uppercase">
                                Active Live Viewers
                            </p>
                            <h3 className="mt-1 flex items-center gap-2 text-2xl font-bold text-gray-900">
                                <span className="relative flex h-3 w-3">
                                    <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" />
                                    <span className="relative inline-flex h-3 w-3 rounded-full bg-emerald-500" />
                                </span>
                                {metrics.activeViewersNow}
                            </h3>
                            <p className="mt-1 text-xs text-gray-500">
                                Visitors browsing right now
                            </p>
                        </div>
                        <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                            <Users className="h-5 w-5" />
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Filter Bar & Search */}
            <div className="mb-5 space-y-3.5 rounded-xl border border-gray-200/80 bg-white p-4 shadow-xs">
                <div className="flex flex-col items-stretch justify-between gap-3 sm:flex-row sm:items-center">
                    {/* Status Tabs */}
                    <div className="flex flex-wrap items-center gap-1.5">
                        {[
                            { key: '', label: 'All' },
                            { key: 'active', label: 'Active' },
                            { key: 'inactive', label: 'Paused' },
                            { key: 'real', label: 'Real Purchases' },
                            { key: 'simulated', label: 'Simulated' },
                        ].map((tab) => (
                            <button
                                key={tab.key}
                                type="button"
                                onClick={() => handleFilterStatus(tab.key)}
                                className={`rounded-lg px-3 py-1.5 text-xs font-medium transition-colors ${
                                    filters.status === tab.key
                                        ? 'bg-gray-900 text-white shadow-xs'
                                        : 'bg-gray-100 text-gray-600 hover:bg-gray-200/80'
                                }`}
                            >
                                {tab.label}
                            </button>
                        ))}
                    </div>

                    {/* Search Form */}
                    <form onSubmit={handleSearch} className="relative sm:w-72">
                        <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400" />
                        <Input
                            type="search"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search product, customer..."
                            className="h-9 pl-9 text-xs"
                        />
                    </form>
                </div>

                {/* Event Type Filter Pills */}
                <div className="flex flex-wrap items-center gap-1.5 border-t border-gray-100 pt-2 text-xs text-gray-500">
                    <span className="mr-1 font-medium">Types:</span>
                    {[
                        { key: '', label: 'All' },
                        { key: 'recent_purchase', label: 'Purchases' },
                        { key: 'product_viewers', label: 'Viewers' },
                        { key: 'low_stock', label: 'Low Stock' },
                        { key: 'recent_review', label: 'Reviews' },
                        { key: 'custom_alert', label: 'Custom' },
                    ].map((typeTab) => (
                        <button
                            key={typeTab.key}
                            type="button"
                            onClick={() => handleFilterType(typeTab.key)}
                            className={`rounded-md px-2.5 py-1 text-xs transition-colors ${
                                filters.type === typeTab.key
                                    ? 'bg-indigo-100 font-semibold text-indigo-700'
                                    : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'
                            }`}
                        >
                            {typeTab.label}
                        </button>
                    ))}
                </div>
            </div>

            {/* Events Table */}
            <div className="overflow-hidden rounded-xl border border-gray-200/80 bg-white shadow-xs">
                {events.data.length === 0 ? (
                    <EmptyState
                        title="No social proof events found"
                        description="Customer purchases and alerts will appear here automatically. You can also seed realistic sample data or create custom alerts."
                        action={
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={handleSeedSample}
                                disabled={seeding}
                                className="gap-1.5"
                            >
                                <RefreshCw
                                    className={`h-3.5 w-3.5 ${seeding ? 'animate-spin' : ''}`}
                                />
                                <span>Seed Sample Data</span>
                            </Button>
                        }
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="border-b border-gray-200 bg-gray-50/75 font-semibold tracking-wider text-gray-500 uppercase">
                                <tr>
                                    <th className="px-4 py-3">Product</th>
                                    <th className="px-4 py-3">Type</th>
                                    <th className="px-4 py-3">
                                        Customer & Location
                                    </th>
                                    <th className="px-4 py-3">Occurred</th>
                                    <th className="px-4 py-3">Performance</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3 text-right">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {events.data.map((item) => (
                                    <tr
                                        key={item.id}
                                        className="transition-colors hover:bg-gray-50/60"
                                    >
                                        {/* Product info */}
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-3">
                                                <div className="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-gray-200/60 bg-gray-100">
                                                    {item.productImageUrl ? (
                                                        <img
                                                            src={
                                                                item.productImageUrl
                                                            }
                                                            alt={
                                                                item.productName
                                                            }
                                                            className="h-full w-full object-cover"
                                                        />
                                                    ) : (
                                                        <ShoppingBag className="h-4 w-4 text-gray-400" />
                                                    )}
                                                </div>
                                                <div className="min-w-0">
                                                    <p className="max-w-xs truncate font-semibold text-gray-900">
                                                        {item.productName}
                                                    </p>
                                                    {item.message && (
                                                        <p className="max-w-xs truncate text-[11px] text-gray-400">
                                                            {item.message}
                                                        </p>
                                                    )}
                                                </div>
                                            </div>
                                        </td>

                                        {/* Event Type */}
                                        <td className="px-4 py-3">
                                            {item.eventType ===
                                                'recent_purchase' && (
                                                <Badge
                                                    variant="outline"
                                                    className="gap-1 border-emerald-200 bg-emerald-50 text-emerald-700"
                                                >
                                                    <CheckCircle2 className="h-3 w-3" />
                                                    Purchase
                                                </Badge>
                                            )}
                                            {item.eventType === 'low_stock' && (
                                                <Badge
                                                    variant="outline"
                                                    className="gap-1 border-rose-200 bg-rose-50 text-rose-700"
                                                >
                                                    <Zap className="h-3 w-3 fill-current" />
                                                    Low Stock
                                                </Badge>
                                            )}
                                            {item.eventType ===
                                                'product_viewers' && (
                                                <Badge
                                                    variant="outline"
                                                    className="gap-1 border-amber-200 bg-amber-50 text-amber-700"
                                                >
                                                    <Flame className="h-3 w-3" />
                                                    Viewers
                                                </Badge>
                                            )}
                                            {item.eventType ===
                                                'recent_review' && (
                                                <Badge
                                                    variant="outline"
                                                    className="gap-1 border-blue-200 bg-blue-50 text-blue-700"
                                                >
                                                    <Sparkles className="h-3 w-3" />
                                                    Review
                                                </Badge>
                                            )}
                                            {item.eventType ===
                                                'custom_alert' && (
                                                <Badge
                                                    variant="outline"
                                                    className="gap-1 border-indigo-200 bg-indigo-50 text-indigo-700"
                                                >
                                                    <Activity className="h-3 w-3" />
                                                    Custom
                                                </Badge>
                                            )}
                                        </td>

                                        {/* Customer & Location */}
                                        <td className="px-4 py-3">
                                            <p className="font-medium text-gray-800">
                                                {item.maskedName || 'Anonymous'}
                                            </p>
                                            <p className="text-[11px] text-gray-400">
                                                {item.location ||
                                                    'Verified Buyer'}
                                            </p>
                                        </td>

                                        {/* Occurred */}
                                        <td className="px-4 py-3 whitespace-nowrap text-gray-500">
                                            <div className="flex items-center gap-1">
                                                <Clock className="h-3 w-3 text-gray-400" />
                                                <span>{item.timeAgo}</span>
                                            </div>
                                        </td>

                                        {/* Impressions & Clicks */}
                                        <td className="px-4 py-3 whitespace-nowrap">
                                            <div className="flex items-center gap-2">
                                                <span className="font-medium text-gray-900">
                                                    {item.impressionsCount}{' '}
                                                    views
                                                </span>
                                                <span className="text-gray-300">
                                                    •
                                                </span>
                                                <span className="font-medium text-indigo-600">
                                                    {item.clicksCount} clicks
                                                </span>
                                                <span className="text-[11px] text-gray-400">
                                                    ({item.ctr}%)
                                                </span>
                                            </div>
                                        </td>

                                        {/* Active / Paused */}
                                        <td className="px-4 py-3 whitespace-nowrap">
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    handleToggle(item)
                                                }
                                                className={`inline-flex cursor-pointer items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold transition-colors ${
                                                    item.isActive
                                                        ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200'
                                                        : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                                                }`}
                                            >
                                                <span
                                                    className={`h-1.5 w-1.5 rounded-full ${item.isActive ? 'bg-emerald-500' : 'bg-gray-400'}`}
                                                />
                                                {item.isActive
                                                    ? 'Active'
                                                    : 'Paused'}
                                            </button>
                                            {item.isSimulated && (
                                                <span className="ml-1.5 text-[10px] font-semibold tracking-wider text-gray-400 uppercase">
                                                    Sample
                                                </span>
                                            )}
                                        </td>

                                        {/* Actions */}
                                        <td className="px-4 py-3 text-right whitespace-nowrap">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    handleDelete(item)
                                                }
                                                className="h-auto p-1.5 text-gray-400 hover:text-rose-600"
                                                title="Delete notification"
                                            >
                                                <Trash2 className="h-3.5 w-3.5" />
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            {/* Create Custom Alert Dialog */}
            <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Create Social Proof Alert</DialogTitle>
                        <DialogDescription>
                            Create a custom notification banner for promotions,
                            popular products, or flash events.
                        </DialogDescription>
                    </DialogHeader>

                    <form
                        onSubmit={handleCreateSubmit}
                        className="space-y-4 pt-2"
                    >
                        <div>
                            <label className="mb-1 block text-xs font-semibold text-gray-700">
                                Notification Type
                            </label>
                            <select
                                value={formType}
                                onChange={(e) => setFormType(e.target.value)}
                                className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-xs"
                            >
                                <option value="recent_purchase">
                                    Recent Purchase
                                </option>
                                <option value="low_stock">
                                    Low Stock Urgency
                                </option>
                                <option value="product_viewers">
                                    Live Visitor Viewing
                                </option>
                                <option value="recent_review">
                                    Recent 5-Star Review
                                </option>
                                <option value="custom_alert">
                                    Custom Highlight
                                </option>
                            </select>
                        </div>

                        <div>
                            <label className="mb-1 block text-xs font-semibold text-gray-700">
                                Pick from Catalog (Optional)
                            </label>
                            <select
                                value={selectedProductId}
                                onChange={(e) =>
                                    handleProductSelect(e.target.value)
                                }
                                className="mb-2 w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-xs"
                            >
                                <option value="">Select a product...</option>
                                {availableProducts.map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="mb-1 block text-xs font-semibold text-gray-700">
                                Product Name *
                            </label>
                            <Input
                                required
                                value={productName}
                                onChange={(e) => setProductName(e.target.value)}
                                placeholder="e.g. Wireless Noise-Cancelling Headphones"
                                className="text-xs"
                            />
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className="mb-1 block text-xs font-semibold text-gray-700">
                                    Customer Name
                                </label>
                                <Input
                                    value={customerName}
                                    onChange={(e) =>
                                        setCustomerName(e.target.value)
                                    }
                                    placeholder="e.g. Sarah Miller"
                                    className="text-xs"
                                />
                            </div>
                            <div>
                                <label className="mb-1 block text-xs font-semibold text-gray-700">
                                    City
                                </label>
                                <Input
                                    value={customerCity}
                                    onChange={(e) =>
                                        setCustomerCity(e.target.value)
                                    }
                                    placeholder="e.g. Chicago"
                                    className="text-xs"
                                />
                            </div>
                        </div>

                        <div>
                            <label className="mb-1 block text-xs font-semibold text-gray-700">
                                Custom Message / Subtitle (Optional)
                            </label>
                            <Input
                                value={customMessage}
                                onChange={(e) =>
                                    setCustomMessage(e.target.value)
                                }
                                placeholder="e.g. High demand - 14 bought in the last 2 hours"
                                className="text-xs"
                            />
                        </div>

                        <div className="flex justify-end gap-2 border-t border-gray-100 pt-3">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => setCreateOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                size="sm"
                                disabled={submitting}
                                className="bg-indigo-600 text-white hover:bg-indigo-700"
                            >
                                {submitting ? 'Creating...' : 'Create Alert'}
                            </Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>
        </AdminPage>
    );
}
