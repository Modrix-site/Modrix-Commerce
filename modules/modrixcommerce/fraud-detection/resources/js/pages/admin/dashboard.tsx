import { Head, Link } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    ArrowRight,
    BarChart3,
    CheckCircle2,
    Clock,
    Eye,
    Shield,
    ShieldAlert,
    ShieldCheck,
    TrendingUp,
    XCircle,
} from 'lucide-react';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type Stats = {
    total_analyzed: number;
    high_risk: number;
    medium_risk: number;
    low_risk: number;
    fraud_detected: number;
    pending_review: number;
    under_review: number;
    approved: number;
    average_score: number;
    detection_rate: number;
};

type TrendPoint = {
    date: string;
    total: number;
    high_risk: number;
    medium_risk: number;
    low_risk: number;
    avg_score: number;
};

type TopIndicator = {
    key: string;
    label: string;
    count: number;
    avg_score: number;
};

type RiskDistribution = {
    low: number;
    medium: number;
    high: number;
};

type RecentHighRisk = {
    id: number;
    order_id: number;
    order_number: string;
    customer_email: string;
    total_score: number;
    risk_level: string;
    review_status: string;
    indicators_count: number;
    created_at: string;
    created_date: string;
};

type Props = {
    stats: Stats;
    riskTrends: TrendPoint[];
    topIndicators: TopIndicator[];
    riskDistribution: RiskDistribution;
    recentHighRisk: RecentHighRisk[];
};

function riskBadge(level: string) {
    switch (level) {
        case 'high':
            return (
                <Badge className="border-red-200 bg-red-500/15 text-red-700 hover:bg-red-500/25 dark:text-red-400">
                    <ShieldAlert className="mr-1 size-3" /> High
                </Badge>
            );
        case 'medium':
            return (
                <Badge className="border-amber-200 bg-amber-500/15 text-amber-700 hover:bg-amber-500/25 dark:text-amber-400">
                    <AlertTriangle className="mr-1 size-3" /> Medium
                </Badge>
            );
        case 'low':
            return (
                <Badge className="border-emerald-200 bg-emerald-500/15 text-emerald-700 hover:bg-emerald-500/25 dark:text-emerald-400">
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
            return (
                <Badge className="border-emerald-200 bg-emerald-500/15 text-emerald-700 dark:text-emerald-400">
                    <CheckCircle2 className="mr-1 size-3" /> Approved
                </Badge>
            );
        case 'rejected':
            return (
                <Badge className="border-red-200 bg-red-500/15 text-red-700 dark:text-red-400">
                    <XCircle className="mr-1 size-3" /> Rejected
                </Badge>
            );
        case 'under_review':
            return (
                <Badge className="border-blue-200 bg-blue-500/15 text-blue-700 dark:text-blue-400">
                    <Eye className="mr-1 size-3" /> Under Review
                </Badge>
            );
        case 'pending':
            return (
                <Badge className="border-amber-200 bg-amber-500/15 text-amber-700 dark:text-amber-400">
                    <Clock className="mr-1 size-3" /> Pending
                </Badge>
            );
        default:
            return <Badge variant="outline">{status}</Badge>;
    }
}

function ScoreGauge({ score }: { score: number }) {
    const color = score >= 66 ? '#ef4444' : score >= 31 ? '#f59e0b' : '#22c55e';
    const circumference = 2 * Math.PI * 42;
    const strokeDashoffset = circumference - (score / 100) * circumference;

    return (
        <div className="relative flex items-center justify-center">
            <svg
                width="100"
                height="100"
                viewBox="0 0 100 100"
                className="-rotate-90"
            >
                <circle
                    cx="50"
                    cy="50"
                    r="42"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="8"
                    className="text-muted/20"
                />
                <circle
                    cx="50"
                    cy="50"
                    r="42"
                    fill="none"
                    stroke={color}
                    strokeWidth="8"
                    strokeLinecap="round"
                    strokeDasharray={circumference}
                    strokeDashoffset={strokeDashoffset}
                    className="transition-all duration-1000 ease-out"
                />
            </svg>
            <div className="absolute flex flex-col items-center">
                <span className="text-xl font-bold" style={{ color }}>
                    {score}
                </span>
                <span className="text-[10px] text-muted-foreground">AVG</span>
            </div>
        </div>
    );
}

function MiniBar({
    value,
    max,
    color,
}: {
    value: number;
    max: number;
    color: string;
}) {
    const pct = max > 0 ? Math.min(100, (value / max) * 100) : 0;
    return (
        <div className="h-2 w-full rounded-full bg-muted/30">
            <div
                className="h-full rounded-full transition-all duration-700 ease-out"
                style={{ width: `${pct}%`, backgroundColor: color }}
            />
        </div>
    );
}

function TrendChart({ data }: { data: TrendPoint[] }) {
    if (data.length === 0) {
        return (
            <div className="flex h-48 items-center justify-center text-sm text-muted-foreground">
                No trend data available yet.
            </div>
        );
    }

    const maxTotal = Math.max(...data.map((d) => d.total), 1);
    const barWidth = Math.max(8, Math.min(32, Math.floor(100 / data.length)));

    return (
        <div className="relative">
            <div className="flex h-48 items-end gap-1 px-2">
                {data.map((point, i) => {
                    const height = (point.total / maxTotal) * 100;
                    const highPct =
                        point.total > 0
                            ? (point.high_risk / point.total) * 100
                            : 0;
                    const medPct =
                        point.total > 0
                            ? (point.medium_risk / point.total) * 100
                            : 0;

                    return (
                        <div
                            key={i}
                            className="flex flex-1 flex-col items-center gap-1"
                            title={`${point.date}: ${point.total} orders`}
                        >
                            <div
                                className="flex w-full flex-col overflow-hidden rounded-t"
                                style={{
                                    height: `${height}%`,
                                    minHeight: '4px',
                                }}
                            >
                                <div
                                    className="bg-red-400 dark:bg-red-500"
                                    style={{ height: `${highPct}%` }}
                                />
                                <div
                                    className="bg-amber-400 dark:bg-amber-500"
                                    style={{ height: `${medPct}%` }}
                                />
                                <div className="flex-1 bg-emerald-400 dark:bg-emerald-500" />
                            </div>
                        </div>
                    );
                })}
            </div>
            <div className="mt-2 flex justify-between px-2 text-[10px] text-muted-foreground">
                <span>{data[0]?.date}</span>
                <span>{data[data.length - 1]?.date}</span>
            </div>
        </div>
    );
}

export default function FraudDashboard({
    stats,
    riskTrends,
    topIndicators,
    riskDistribution,
    recentHighRisk,
}: Props) {
    const totalDist =
        riskDistribution.low + riskDistribution.medium + riskDistribution.high;

    return (
        <>
            <Head title="Fraud Detection — Dashboard" />
            <AdminPage>
                <PageHeader
                    title="Fraud Detection"
                    description="Monitor fraud risk in real time, review flagged orders, and protect your store from suspicious activity."
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/m/modrixcommerce/fraud-detection/queue">
                                    <Shield className="mr-2 size-4" /> View
                                    Fraud Queue
                                </Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href="/admin/m/modrixcommerce/fraud-detection/reports">
                                    <BarChart3 className="mr-2 size-4" />{' '}
                                    Reports
                                </Link>
                            </Button>
                        </div>
                    }
                />

                {/* KPI Cards */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <Card>
                        <CardContent className="p-5">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                    Orders Analyzed
                                </span>
                                <div className="rounded-full bg-blue-500/10 p-2 text-blue-600 dark:text-blue-400">
                                    <Activity className="size-4" />
                                </div>
                            </div>
                            <div className="mt-2">
                                <span className="text-2xl font-bold tracking-tight">
                                    {stats.total_analyzed.toLocaleString()}
                                </span>
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Total orders scored
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-5">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                    High Risk
                                </span>
                                <div className="rounded-full bg-red-500/10 p-2 text-red-600 dark:text-red-400">
                                    <ShieldAlert className="size-4" />
                                </div>
                            </div>
                            <div className="mt-2 flex items-baseline gap-2">
                                <span className="text-2xl font-bold tracking-tight text-red-600 dark:text-red-400">
                                    {stats.high_risk.toLocaleString()}
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    {stats.detection_rate}%
                                </span>
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Detection rate
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-5">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                    Fraud Rejected
                                </span>
                                <div className="rounded-full bg-rose-500/10 p-2 text-rose-600 dark:text-rose-400">
                                    <XCircle className="size-4" />
                                </div>
                            </div>
                            <div className="mt-2">
                                <span className="text-2xl font-bold tracking-tight">
                                    {stats.fraud_detected.toLocaleString()}
                                </span>
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Confirmed fraud
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-5">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                    Pending Review
                                </span>
                                <div className="rounded-full bg-amber-500/10 p-2 text-amber-600 dark:text-amber-400">
                                    <Clock className="size-4" />
                                </div>
                            </div>
                            <div className="mt-2 flex items-baseline gap-2">
                                <span className="text-2xl font-bold tracking-tight">
                                    {stats.pending_review}
                                </span>
                                {stats.under_review > 0 && (
                                    <span className="text-xs text-blue-600 dark:text-blue-400">
                                        +{stats.under_review} reviewing
                                    </span>
                                )}
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Awaiting merchant action
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-5">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                    Avg Score
                                </span>
                                <ScoreGauge
                                    score={Math.round(stats.average_score)}
                                />
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Average risk score
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* Risk Trends Chart */}
                    <Card className="lg:col-span-2">
                        <CardContent className="p-5">
                            <div className="mb-4 flex items-center justify-between">
                                <h3 className="text-sm font-semibold">
                                    Risk Trends (30 Days)
                                </h3>
                                <div className="flex items-center gap-3 text-[11px]">
                                    <span className="flex items-center gap-1">
                                        <span className="inline-block size-2.5 rounded-full bg-red-400" />{' '}
                                        High
                                    </span>
                                    <span className="flex items-center gap-1">
                                        <span className="inline-block size-2.5 rounded-full bg-amber-400" />{' '}
                                        Medium
                                    </span>
                                    <span className="flex items-center gap-1">
                                        <span className="inline-block size-2.5 rounded-full bg-emerald-400" />{' '}
                                        Low
                                    </span>
                                </div>
                            </div>
                            <TrendChart data={riskTrends} />
                        </CardContent>
                    </Card>

                    {/* Risk Distribution */}
                    <Card>
                        <CardContent className="p-5">
                            <h3 className="mb-4 text-sm font-semibold">
                                Risk Distribution
                            </h3>
                            <div className="space-y-4">
                                <div>
                                    <div className="mb-1 flex justify-between text-sm">
                                        <span className="font-medium text-emerald-600 dark:text-emerald-400">
                                            Low Risk
                                        </span>
                                        <span className="font-semibold">
                                            {riskDistribution.low}
                                        </span>
                                    </div>
                                    <MiniBar
                                        value={riskDistribution.low}
                                        max={totalDist}
                                        color="#22c55e"
                                    />
                                </div>
                                <div>
                                    <div className="mb-1 flex justify-between text-sm">
                                        <span className="font-medium text-amber-600 dark:text-amber-400">
                                            Medium Risk
                                        </span>
                                        <span className="font-semibold">
                                            {riskDistribution.medium}
                                        </span>
                                    </div>
                                    <MiniBar
                                        value={riskDistribution.medium}
                                        max={totalDist}
                                        color="#f59e0b"
                                    />
                                </div>
                                <div>
                                    <div className="mb-1 flex justify-between text-sm">
                                        <span className="font-medium text-red-600 dark:text-red-400">
                                            High Risk
                                        </span>
                                        <span className="font-semibold">
                                            {riskDistribution.high}
                                        </span>
                                    </div>
                                    <MiniBar
                                        value={riskDistribution.high}
                                        max={totalDist}
                                        color="#ef4444"
                                    />
                                </div>
                            </div>

                            {/* Donut */}
                            <div className="mt-6 flex justify-center">
                                <svg
                                    width="120"
                                    height="120"
                                    viewBox="0 0 120 120"
                                >
                                    {(() => {
                                        if (totalDist === 0) {
                                            return (
                                                <circle
                                                    cx="60"
                                                    cy="60"
                                                    r="48"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    strokeWidth="16"
                                                    className="text-muted/20"
                                                />
                                            );
                                        }
                                        const c = 2 * Math.PI * 48;
                                        const lowPct =
                                            riskDistribution.low / totalDist;
                                        const medPct =
                                            riskDistribution.medium / totalDist;
                                        const highPct =
                                            riskDistribution.high / totalDist;
                                        return (
                                            <g className="origin-center -rotate-90">
                                                <circle
                                                    cx="60"
                                                    cy="60"
                                                    r="48"
                                                    fill="none"
                                                    stroke="#22c55e"
                                                    strokeWidth="16"
                                                    strokeDasharray={`${lowPct * c} ${c}`}
                                                    strokeDashoffset="0"
                                                />
                                                <circle
                                                    cx="60"
                                                    cy="60"
                                                    r="48"
                                                    fill="none"
                                                    stroke="#f59e0b"
                                                    strokeWidth="16"
                                                    strokeDasharray={`${medPct * c} ${c}`}
                                                    strokeDashoffset={`${-lowPct * c}`}
                                                />
                                                <circle
                                                    cx="60"
                                                    cy="60"
                                                    r="48"
                                                    fill="none"
                                                    stroke="#ef4444"
                                                    strokeWidth="16"
                                                    strokeDasharray={`${highPct * c} ${c}`}
                                                    strokeDashoffset={`${-(lowPct + medPct) * c}`}
                                                />
                                            </g>
                                        );
                                    })()}
                                    <text
                                        x="60"
                                        y="56"
                                        textAnchor="middle"
                                        className="fill-foreground text-lg font-bold"
                                    >
                                        {totalDist}
                                    </text>
                                    <text
                                        x="60"
                                        y="72"
                                        textAnchor="middle"
                                        className="fill-muted-foreground text-[10px]"
                                    >
                                        total
                                    </text>
                                </svg>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {/* Top Fraud Indicators */}
                    <Card>
                        <CardContent className="p-5">
                            <div className="mb-4 flex items-center justify-between">
                                <h3 className="text-sm font-semibold">
                                    Top Fraud Indicators (30 Days)
                                </h3>
                                <TrendingUp className="size-4 text-muted-foreground" />
                            </div>
                            {topIndicators.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No indicator data available yet.
                                </p>
                            ) : (
                                <div className="space-y-3">
                                    {topIndicators.map((ind, i) => (
                                        <div
                                            key={ind.key}
                                            className="flex items-center gap-3"
                                        >
                                            <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-muted text-[11px] font-semibold text-muted-foreground">
                                                {i + 1}
                                            </span>
                                            <div className="min-w-0 flex-1">
                                                <div className="flex justify-between">
                                                    <span className="truncate text-sm font-medium">
                                                        {ind.label}
                                                    </span>
                                                    <span className="ml-2 shrink-0 text-sm font-semibold">
                                                        {ind.count}
                                                    </span>
                                                </div>
                                                <MiniBar
                                                    value={ind.count}
                                                    max={
                                                        topIndicators[0]
                                                            ?.count ?? 1
                                                    }
                                                    color="#6366f1"
                                                />
                                            </div>
                                            <span className="shrink-0 text-[11px] text-muted-foreground">
                                                avg +{ind.avg_score}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Recent High-Risk Orders */}
                    <Card>
                        <CardContent className="p-5">
                            <div className="mb-4 flex items-center justify-between">
                                <h3 className="text-sm font-semibold">
                                    Recent High-Risk Orders
                                </h3>
                                <Button variant="ghost" size="sm" asChild>
                                    <Link href="/admin/m/modrixcommerce/fraud-detection/queue?risk_level=high">
                                        View All{' '}
                                        <ArrowRight className="ml-1 size-3" />
                                    </Link>
                                </Button>
                            </div>
                            {recentHighRisk.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No high-risk orders found.
                                </p>
                            ) : (
                                <div className="space-y-3">
                                    {recentHighRisk.map((order) => (
                                        <div
                                            key={order.id}
                                            className="flex items-center gap-3 rounded-lg border border-red-100 bg-red-50/50 p-3 dark:border-red-900/30 dark:bg-red-950/20"
                                        >
                                            <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-red-500/10">
                                                <span className="text-sm font-bold text-red-600 dark:text-red-400">
                                                    {order.total_score}
                                                </span>
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-center gap-2">
                                                    <span className="truncate text-sm font-semibold">
                                                        {order.order_number}
                                                    </span>
                                                    {statusBadge(
                                                        order.review_status,
                                                    )}
                                                </div>
                                                <p className="truncate text-xs text-muted-foreground">
                                                    {order.customer_email} ·{' '}
                                                    {order.indicators_count}{' '}
                                                    indicators ·{' '}
                                                    {order.created_at}
                                                </p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </AdminPage>
        </>
    );
}
