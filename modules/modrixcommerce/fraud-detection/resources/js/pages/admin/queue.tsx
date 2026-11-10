import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Clock,
    Eye,
    Filter,
    Globe,
    Mail,
    Search,
    Shield,
    ShieldAlert,
    ShieldCheck,
    SortDesc,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { EmptyState } from '@/components/admin/empty-state';
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

type Indicator = {
    key: string;
    label: string;
    score: number;
    details: string;
};

type FraudScoreRow = {
    id: number;
    order_id: number;
    order_number: string;
    customer_email: string;
    customer_id: number | null;
    total_score: number;
    risk_level: 'low' | 'medium' | 'high';
    review_status: 'pending' | 'approved' | 'rejected' | 'under_review';
    indicators: Indicator[];
    indicators_count: number;
    ip_address: string | null;
    order_total: string;
    currency: string;
    is_vpn: boolean;
    is_proxy: boolean;
    is_disposable_email: boolean;
    address_mismatch: boolean;
    reviewed_by: string | null;
    reviewed_at: string | null;
    created_at: string;
    created_date: string;
    approve_url: string;
    reject_url: string;
    review_url: string;
    note_url: string;
    detail_url: string;
};

type Props = {
    fraudScores: {
        data: FraudScoreRow[];
        links: { url: string | null; label: string; active: boolean }[];
        current_page: number;
        last_page: number;
        total: number;
    };
    filters: {
        status: string;
        risk_level: string;
        search: string;
        sort: string;
    };
};

function riskBadge(level: string) {
    switch (level) {
        case 'high':
            return (
                <Badge className="bg-red-500/15 text-red-700 hover:bg-red-500/25 border-red-200 dark:text-red-400">
                    <ShieldAlert className="mr-1 size-3" /> High
                </Badge>
            );
        case 'medium':
            return (
                <Badge className="bg-amber-500/15 text-amber-700 hover:bg-amber-500/25 border-amber-200 dark:text-amber-400">
                    <AlertTriangle className="mr-1 size-3" /> Medium
                </Badge>
            );
        case 'low':
            return (
                <Badge className="bg-emerald-500/15 text-emerald-700 hover:bg-emerald-500/25 border-emerald-200 dark:text-emerald-400">
                    <ShieldCheck className="mr-1 size-3" /> Low
                </Badge>
            );
        default:
            return <Badge variant="outline">{level}</Badge>;
    }
}

function statusBadge(status: string) {
    switch (status) {
        case 'approved':
            return <Badge className="bg-emerald-500/15 text-emerald-700 border-emerald-200 dark:text-emerald-400"><CheckCircle2 className="mr-1 size-3" /> Approved</Badge>;
        case 'rejected':
            return <Badge className="bg-red-500/15 text-red-700 border-red-200 dark:text-red-400"><XCircle className="mr-1 size-3" /> Rejected</Badge>;
        case 'under_review':
            return <Badge className="bg-blue-500/15 text-blue-700 border-blue-200 dark:text-blue-400"><Eye className="mr-1 size-3" /> Under Review</Badge>;
        case 'pending':
            return <Badge className="bg-amber-500/15 text-amber-700 border-amber-200 dark:text-amber-400"><Clock className="mr-1 size-3" /> Pending</Badge>;
        default:
            return <Badge variant="outline">{status}</Badge>;
    }
}

function ScoreCircle({ score }: { score: number }) {
    const color = score >= 66 ? 'text-red-600 dark:text-red-400' : score >= 31 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400';
    const bg = score >= 66 ? 'bg-red-500/10' : score >= 31 ? 'bg-amber-500/10' : 'bg-emerald-500/10';
    return (
        <div className={`flex size-10 shrink-0 items-center justify-center rounded-full ${bg}`}>
            <span className={`text-sm font-bold ${color}`}>{score}</span>
        </div>
    );
}

export default function FraudQueue({ fraudScores, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [selectedOrder, setSelectedOrder] = useState<FraudScoreRow | null>(null);
    const [actionNotes, setActionNotes] = useState('');
    const [actionLoading, setActionLoading] = useState(false);

    function handleSearch(e: React.FormEvent) {
        e.preventDefault();
        router.get(window.location.pathname, { status: filters.status, risk_level: filters.risk_level, search, sort: filters.sort }, { preserveState: true });
    }

    function handleFilter(key: string, value: string) {
        router.get(window.location.pathname, { ...filters, [key]: value, search: filters.search }, { preserveState: true });
    }

    function handleAction(url: string) {
        setActionLoading(true);
        router.post(url, { notes: actionNotes }, {
            preserveScroll: true,
            onFinish: () => { setActionLoading(false); setSelectedOrder(null); setActionNotes(''); },
        });
    }

    const statusFilters = [
        { value: 'all', label: 'All' },
        { value: 'pending', label: 'Pending' },
        { value: 'under_review', label: 'Under Review' },
        { value: 'approved', label: 'Approved' },
        { value: 'rejected', label: 'Rejected' },
    ];

    const riskFilters = [
        { value: 'all', label: 'All Levels' },
        { value: 'high', label: 'High Risk' },
        { value: 'medium', label: 'Medium Risk' },
        { value: 'low', label: 'Low Risk' },
    ];

    return (
        <>
            <Head title="Fraud Detection — Queue" />
            <AdminPage>
                <PageHeader
                    title="Fraud Queue"
                    description={`${fraudScores.total} orders to review`}
                />

                {/* Filters */}
                <div className="flex flex-wrap items-center gap-3 mb-4">
                    <form onSubmit={handleSearch} className="flex items-center gap-2">
                        <div className="relative">
                            <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                placeholder="Search email, IP, order..."
                                value={search}
                                onChange={e => setSearch(e.target.value)}
                                className="pl-10 w-64"
                            />
                        </div>
                    </form>

                    <div className="flex items-center gap-1 rounded-lg border bg-background p-0.5">
                        {statusFilters.map(f => (
                            <button
                                key={f.value}
                                onClick={() => handleFilter('status', f.value)}
                                className={`rounded-md px-3 py-1.5 text-xs font-medium transition-colors ${
                                    filters.status === f.value
                                        ? 'bg-foreground text-background shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground'
                                }`}
                            >
                                {f.label}
                            </button>
                        ))}
                    </div>

                    <div className="flex items-center gap-1 rounded-lg border bg-background p-0.5">
                        {riskFilters.map(f => (
                            <button
                                key={f.value}
                                onClick={() => handleFilter('risk_level', f.value)}
                                className={`rounded-md px-3 py-1.5 text-xs font-medium transition-colors ${
                                    filters.risk_level === f.value
                                        ? 'bg-foreground text-background shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground'
                                }`}
                            >
                                {f.label}
                            </button>
                        ))}
                    </div>

                    <button
                        onClick={() => handleFilter('sort', filters.sort === 'highest_score' ? 'newest' : 'highest_score')}
                        className="flex items-center gap-1 rounded-lg border px-3 py-1.5 text-xs font-medium text-muted-foreground hover:text-foreground transition-colors"
                    >
                        <SortDesc className="size-3" />
                        {filters.sort === 'highest_score' ? 'Highest Score' : 'Newest'}
                    </button>
                </div>

                {/* Queue List */}
                {fraudScores.data.length === 0 ? (
                    <EmptyState
                        title="No orders match your filters"
                        description="Adjust your filters or wait for new orders to be analyzed."
                    />
                ) : (
                    <div className="space-y-2">
                        {fraudScores.data.map((score) => (
                            <Card key={score.id} className="transition-shadow hover:shadow-md">
                                <CardContent className="p-4">
                                    <div className="flex items-center gap-4">
                                        <ScoreCircle score={score.total_score} />

                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <Link href={score.detail_url} className="text-sm font-semibold hover:underline">
                                                    {score.order_number}
                                                </Link>
                                                {riskBadge(score.risk_level)}
                                                {statusBadge(score.review_status)}
                                                {score.is_vpn && <Badge variant="outline" className="text-[10px]">VPN</Badge>}
                                                {score.is_disposable_email && <Badge variant="outline" className="text-[10px]">Disposable</Badge>}
                                                {score.address_mismatch && <Badge variant="outline" className="text-[10px]">Addr Mismatch</Badge>}
                                            </div>
                                            <div className="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                                <span className="flex items-center gap-1">
                                                    <Mail className="size-3" /> {score.customer_email}
                                                </span>
                                                {score.ip_address && (
                                                    <span className="flex items-center gap-1">
                                                        <Globe className="size-3" /> {score.ip_address}
                                                    </span>
                                                )}
                                                <span>{score.order_total} {score.currency}</span>
                                                <span>{score.indicators_count} indicators</span>
                                                <span>{score.created_at}</span>
                                            </div>
                                        </div>

                                        <div className="flex shrink-0 items-center gap-1">
                                            {score.review_status !== 'approved' && (
                                                <Button size="sm" variant="ghost" className="text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950/30"
                                                    onClick={() => { setSelectedOrder(score); setActionNotes(''); }}>
                                                    <CheckCircle2 className="size-4" />
                                                </Button>
                                            )}
                                            {score.review_status !== 'rejected' && (
                                                <Button size="sm" variant="ghost" className="text-red-600 hover:bg-red-50 dark:hover:bg-red-950/30"
                                                    onClick={() => { setSelectedOrder(score); setActionNotes(''); }}>
                                                    <XCircle className="size-4" />
                                                </Button>
                                            )}
                                            <Button size="sm" variant="ghost" asChild>
                                                <Link href={score.detail_url}>
                                                    <Eye className="size-4" />
                                                </Link>
                                            </Button>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}

                {/* Pagination */}
                {fraudScores.last_page > 1 && (
                    <div className="mt-4 flex items-center justify-between">
                        <span className="text-xs text-muted-foreground">
                            Page {fraudScores.current_page} of {fraudScores.last_page} ({fraudScores.total} total)
                        </span>
                        <div className="flex items-center gap-1">
                            {fraudScores.links.map((link, i) => {
                                if (!link.url) return null;
                                return (
                                    <Link
                                        key={i}
                                        href={link.url}
                                        preserveState
                                        className={`rounded-md px-3 py-1.5 text-xs transition-colors ${
                                            link.active
                                                ? 'bg-foreground text-background font-semibold'
                                                : 'text-muted-foreground hover:bg-muted'
                                        }`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                );
                            })}
                        </div>
                    </div>
                )}
            </AdminPage>

            {/* Action Dialog */}
            <Dialog open={!!selectedOrder} onOpenChange={() => setSelectedOrder(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Review Order {selectedOrder?.order_number}</DialogTitle>
                        <DialogDescription>
                            Risk Score: {selectedOrder?.total_score}/100 · {selectedOrder?.indicators_count} indicators
                        </DialogDescription>
                    </DialogHeader>

                    {selectedOrder && (
                        <div className="space-y-4">
                            {/* Indicators Summary */}
                            <div className="max-h-48 overflow-y-auto space-y-2">
                                {selectedOrder.indicators.map((ind, i) => (
                                    <div key={i} className="flex items-start gap-2 rounded border p-2 text-xs">
                                        <span className="shrink-0 rounded bg-red-100 px-1.5 py-0.5 font-semibold text-red-700 dark:bg-red-900/30 dark:text-red-400">
                                            +{ind.score}
                                        </span>
                                        <div>
                                            <span className="font-medium">{ind.label}</span>
                                            <p className="text-muted-foreground mt-0.5">{ind.details}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>

                            <div>
                                <label className="text-sm font-medium mb-1 block">Notes (optional)</label>
                                <textarea
                                    value={actionNotes}
                                    onChange={e => setActionNotes(e.target.value)}
                                    rows={3}
                                    className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                                    placeholder="Add investigation notes..."
                                />
                            </div>

                            <div className="flex items-center gap-2">
                                <Button
                                    onClick={() => handleAction(selectedOrder.approve_url)}
                                    disabled={actionLoading}
                                    className="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white"
                                >
                                    <CheckCircle2 className="size-4 mr-2" /> Approve
                                </Button>
                                <Button
                                    onClick={() => handleAction(selectedOrder.reject_url)}
                                    disabled={actionLoading}
                                    variant="destructive"
                                    className="flex-1"
                                >
                                    <XCircle className="size-4 mr-2" /> Reject
                                </Button>
                                <Button
                                    onClick={() => handleAction(selectedOrder.review_url)}
                                    disabled={actionLoading}
                                    variant="outline"
                                    className="flex-1"
                                >
                                    <Eye className="size-4 mr-2" /> Review
                                </Button>
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
