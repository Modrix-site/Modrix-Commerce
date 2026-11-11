import { Head, router } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    BarChart3,
    Calendar,
    Download,
    FileSpreadsheet,
    FileText,
    Filter,
    ShieldAlert,
    ShieldCheck,
    TrendingUp,
} from 'lucide-react';
import { useState } from 'react';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type Stats = {
    total_analyzed?: number;
    high_risk_count?: number;
    medium_risk_count?: number;
    low_risk_count?: number;
    pending_review?: number;
    approved_count?: number;
    rejected_count?: number;
    avg_score?: number;
    high_risk_pct?: number;
    fraud_prevented_amount?: number;
};

type TrendPoint = {
    date: string;
    total: number;
    high: number;
    medium: number;
    low: number;
    avg_score: number;
};

type TopIndicator = {
    name: string;
    label: string;
    count: number;
    avg_score: number;
};

type RiskDistribution = {
    low: number;
    medium: number;
    high: number;
};

type DailySummary = {
    date: string;
    total_orders: number;
    high_risk: number;
    medium_risk: number;
    low_risk: number;
    approved: number;
    rejected: number;
    avg_score: number;
};

type Props = {
    stats?: Stats;
    riskTrends?: TrendPoint[];
    topIndicators?: TopIndicator[];
    riskDistribution?: RiskDistribution;
    dailySummary?: DailySummary[] | DailySummary;
    filters?: {
        from?: string | null;
        to?: string | null;
    };
    exportCsvUrl?: string;
    exportExcelUrl?: string;
};

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
        <div className="h-2 w-full rounded-full bg-muted/40">
            <div
                className="h-full rounded-full transition-all duration-700 ease-out"
                style={{ width: `${pct}%`, backgroundColor: color }}
            />
        </div>
    );
}

function TrendChart({ data }: { data?: TrendPoint[] }) {
    const safeData = Array.isArray(data) ? data : [];
    if (safeData.length === 0) {
        return (
            <div className="flex h-64 items-center justify-center text-sm text-muted-foreground">
                No trend data available for selected date window.
            </div>
        );
    }

    const maxVal = Math.max(...safeData.map((d) => d.total), 10);

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between text-xs text-muted-foreground">
                <span className="font-medium">
                    Order Volume & Risk Severity
                </span>
                <div className="flex items-center gap-4">
                    <span className="flex items-center gap-1.5">
                        <span className="size-2 rounded-full bg-red-500" /> High
                        Risk
                    </span>
                    <span className="flex items-center gap-1.5">
                        <span className="size-2 rounded-full bg-amber-500" />{' '}
                        Medium
                    </span>
                    <span className="flex items-center gap-1.5">
                        <span className="size-2 rounded-full bg-emerald-500" />{' '}
                        Low Risk
                    </span>
                </div>
            </div>

            <div className="flex h-56 items-end gap-1.5 rounded-lg border bg-muted/10 p-4">
                {safeData.map((p, i) => {
                    const totalH = Math.max(4, (p.total / maxVal) * 100);
                    const highPct =
                        p.total > 0 ? (p.high / p.total) * totalH : 0;
                    const medPct =
                        p.total > 0 ? (p.medium / p.total) * totalH : 0;
                    const _lowPct = totalH - highPct - medPct;

                    return (
                        <div
                            key={i}
                            className="group relative flex h-full flex-1 cursor-pointer flex-col items-center justify-end"
                        >
                            {/* Hover Tooltip */}
                            <div className="pointer-events-none absolute bottom-full z-20 mb-2 hidden w-36 rounded-md border bg-popover p-2 text-[11px] shadow-lg group-hover:block">
                                <p className="font-semibold">{p.date}</p>
                                <div className="mt-1 space-y-0.5">
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">
                                            Total:
                                        </span>
                                        <span className="font-mono">
                                            {p.total}
                                        </span>
                                    </div>
                                    <div className="flex justify-between text-red-600">
                                        <span>High Risk:</span>
                                        <span className="font-mono">
                                            {p.high}
                                        </span>
                                    </div>
                                    <div className="flex justify-between text-amber-600">
                                        <span>Medium:</span>
                                        <span className="font-mono">
                                            {p.medium}
                                        </span>
                                    </div>
                                    <div className="flex justify-between text-emerald-600">
                                        <span>Low Risk:</span>
                                        <span className="font-mono">
                                            {p.low}
                                        </span>
                                    </div>
                                    <div className="flex justify-between border-t pt-1 text-muted-foreground">
                                        <span>Avg Score:</span>
                                        <span className="font-mono">
                                            {p.avg_score}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            {/* Stacked bar */}
                            <div
                                className="flex w-full max-w-[18px] flex-col justify-end overflow-hidden rounded-t transition-all duration-300 group-hover:brightness-110"
                                style={{ height: `${totalH}%` }}
                            >
                                <div
                                    className="bg-red-500"
                                    style={{
                                        height: `${(highPct / totalH) * 100}%`,
                                    }}
                                />
                                <div
                                    className="bg-amber-500"
                                    style={{
                                        height: `${(medPct / totalH) * 100}%`,
                                    }}
                                />
                                <div className="flex-1 bg-emerald-500" />
                            </div>
                        </div>
                    );
                })}
            </div>
            <div className="flex justify-between px-1 text-[11px] text-muted-foreground">
                <span>{safeData[0]?.date}</span>
                <span>{safeData[safeData.length - 1]?.date}</span>
            </div>
        </div>
    );
}

export default function FraudReports({
    stats,
    riskTrends,
    topIndicators,
    riskDistribution: _riskDistribution,
    dailySummary,
    filters,
    exportCsvUrl,
    exportExcelUrl,
}: Props) {
    const [fromDate, setFromDate] = useState(filters?.from || '');
    const [toDate, setToDate] = useState(filters?.to || '');

    const safeDailySummary = Array.isArray(dailySummary)
        ? dailySummary
        : dailySummary && typeof dailySummary === 'object'
          ? [dailySummary]
          : [];

    const safeTopIndicators = Array.isArray(topIndicators) ? topIndicators : [];
    const safeRiskTrends = Array.isArray(riskTrends) ? riskTrends : [];

    const safeStats = {
        total_analyzed: stats?.total_analyzed ?? 0,
        high_risk_count: stats?.high_risk_count ?? 0,
        medium_risk_count: stats?.medium_risk_count ?? 0,
        low_risk_count: stats?.low_risk_count ?? 0,
        pending_review: stats?.pending_review ?? 0,
        approved_count: stats?.approved_count ?? 0,
        rejected_count: stats?.rejected_count ?? 0,
        avg_score: stats?.avg_score ?? 0,
        high_risk_pct: stats?.high_risk_pct ?? 0,
        fraud_prevented_amount: stats?.fraud_prevented_amount ?? 0,
    };

    const handleFilterSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            '/admin/m/modrixcommerce/fraud-detection/reports',
            { from: fromDate || undefined, to: toDate || undefined },
            { preserveState: true },
        );
    };

    const maxIndicatorCount = Math.max(
        ...safeTopIndicators.map((t) => t.count),
        1,
    );

    return (
        <>
            <Head title="Fraud Detection — Reports & Analytics" />
            <AdminPage>
                <PageHeader
                    title="Fraud Reports & Analytics"
                    description="Detailed risk trends, fraud indicators distribution, loss prevention metrics, and data export."
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            {exportCsvUrl && (
                                <Button variant="outline" asChild>
                                    <a href={exportCsvUrl} download>
                                        <Download className="mr-1.5 size-4" />{' '}
                                        Export CSV
                                    </a>
                                </Button>
                            )}
                            {exportExcelUrl && (
                                <Button variant="outline" asChild>
                                    <a href={exportExcelUrl} download>
                                        <FileSpreadsheet className="mr-1.5 size-4" />{' '}
                                        Export Excel
                                    </a>
                                </Button>
                            )}
                        </div>
                    }
                />

                {/* Filter Bar */}
                <Card className="mb-6 shadow-sm">
                    <CardContent className="p-4">
                        <form
                            onSubmit={handleFilterSubmit}
                            className="flex flex-col items-center justify-between gap-4 sm:flex-row"
                        >
                            <div className="flex w-full flex-wrap items-center gap-3 sm:w-auto">
                                <div className="flex items-center gap-2">
                                    <Calendar className="size-4 text-muted-foreground" />
                                    <span className="text-xs font-semibold text-muted-foreground">
                                        From:
                                    </span>
                                    <Input
                                        type="date"
                                        className="h-9 w-40 text-xs"
                                        value={fromDate}
                                        onChange={(e) =>
                                            setFromDate(e.target.value)
                                        }
                                    />
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className="text-xs font-semibold text-muted-foreground">
                                        To:
                                    </span>
                                    <Input
                                        type="date"
                                        className="h-9 w-40 text-xs"
                                        value={toDate}
                                        onChange={(e) =>
                                            setToDate(e.target.value)
                                        }
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    size="sm"
                                    variant="secondary"
                                >
                                    <Filter className="mr-1.5 size-3.5" /> Apply
                                    Filter
                                </Button>
                            </div>

                            {(fromDate || toDate) && (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    onClick={() => {
                                        setFromDate('');
                                        setToDate('');
                                        router.get(
                                            '/admin/m/modrixcommerce/fraud-detection/reports',
                                        );
                                    }}
                                >
                                    Clear Filter
                                </Button>
                            )}
                        </form>
                    </CardContent>
                </Card>

                {/* Metrics Highlights */}
                <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card className="shadow-sm">
                        <CardContent className="p-5">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                    Total Analyzed
                                </span>
                                <div className="rounded-full bg-blue-500/10 p-2 text-blue-600 dark:text-blue-400">
                                    <Activity className="size-4" />
                                </div>
                            </div>
                            <div className="mt-2 text-2xl font-bold tracking-tight">
                                {safeStats.total_analyzed.toLocaleString()}
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Scored transactions
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm">
                        <CardContent className="p-5">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                    Fraud Prevented Value
                                </span>
                                <div className="rounded-full bg-emerald-500/10 p-2 text-emerald-600 dark:text-emerald-400">
                                    <ShieldCheck className="size-4" />
                                </div>
                            </div>
                            <div className="mt-2 text-2xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400">
                                $
                                {(
                                    safeStats.fraud_prevented_amount || 0
                                ).toLocaleString(undefined, {
                                    minimumFractionDigits: 2,
                                })}
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Rejected fraudulent orders
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm">
                        <CardContent className="p-5">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                    High Risk Rate
                                </span>
                                <div className="rounded-full bg-red-500/10 p-2 text-red-600 dark:text-red-400">
                                    <ShieldAlert className="size-4" />
                                </div>
                            </div>
                            <div className="mt-2 text-2xl font-bold tracking-tight text-red-600 dark:text-red-400">
                                {safeStats.high_risk_pct}%
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {safeStats.high_risk_count} total high-risk
                                orders
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm">
                        <CardContent className="p-5">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                    Average Risk Score
                                </span>
                                <div className="rounded-full bg-amber-500/10 p-2 text-amber-600 dark:text-amber-400">
                                    <BarChart3 className="size-4" />
                                </div>
                            </div>
                            <div className="mt-2 text-2xl font-bold tracking-tight">
                                {safeStats.avg_score}{' '}
                                <span className="text-sm font-normal text-muted-foreground">
                                    / 100
                                </span>
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Portfolio risk baseline
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Trend Chart & Top Indicators */}
                <div className="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <Card className="shadow-sm lg:col-span-2">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base font-semibold">
                                <TrendingUp className="size-4 text-primary" />{' '}
                                Fraud & Risk Trends Over Time
                            </CardTitle>
                            <CardDescription>
                                Daily order volume categorized by evaluated
                                threat level
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <TrendChart data={safeRiskTrends} />
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base font-semibold">
                                <AlertTriangle className="size-4 text-amber-500" />{' '}
                                Top Triggered Indicators
                            </CardTitle>
                            <CardDescription>
                                Most frequent heuristic signals contributing to
                                risk
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {safeTopIndicators.length === 0 ? (
                                <p className="text-xs text-muted-foreground">
                                    No indicators triggered yet.
                                </p>
                            ) : (
                                safeTopIndicators.map((ind, i) => (
                                    <div
                                        key={i}
                                        className="space-y-1.5 text-xs"
                                    >
                                        <div className="flex items-center justify-between font-medium">
                                            <span>{ind.label}</span>
                                            <span className="font-semibold text-foreground">
                                                {ind.count} orders
                                            </span>
                                        </div>
                                        <MiniBar
                                            value={ind.count}
                                            max={maxIndicatorCount}
                                            color="#f59e0b"
                                        />
                                        <div className="flex justify-between text-[10px] text-muted-foreground">
                                            <span>
                                                Avg impact: +{ind.avg_score} pts
                                            </span>
                                        </div>
                                    </div>
                                ))
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Daily Breakdown Table */}
                <Card className="shadow-sm">
                    <CardHeader>
                        <CardTitle className="flex items-center justify-between text-base font-semibold">
                            <span className="flex items-center gap-2">
                                <FileText className="size-4 text-primary" />{' '}
                                Daily Activity Breakdown
                            </span>
                            <span className="text-xs font-normal text-muted-foreground">
                                Last {safeDailySummary.length} days
                            </span>
                        </CardTitle>
                        <CardDescription>
                            Comprehensive daily audit of orders, threat levels,
                            and resolutions
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs">
                                <thead className="border-b bg-muted/30 font-semibold tracking-wider text-muted-foreground uppercase">
                                    <tr>
                                        <th className="p-3.5 pl-6">Date</th>
                                        <th className="p-3.5">Total Orders</th>
                                        <th className="p-3.5">High Risk</th>
                                        <th className="p-3.5">Medium</th>
                                        <th className="p-3.5">Low Risk</th>
                                        <th className="p-3.5">Approved</th>
                                        <th className="p-3.5">Rejected</th>
                                        <th className="p-3.5 pr-6 text-right">
                                            Avg Score
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {safeDailySummary.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={8}
                                                className="p-8 text-center text-muted-foreground"
                                            >
                                                No daily activity recorded in
                                                this period.
                                            </td>
                                        </tr>
                                    ) : (
                                        safeDailySummary.map((d, i) => (
                                            <tr
                                                key={i}
                                                className="transition-colors hover:bg-muted/10"
                                            >
                                                <td className="p-3.5 pl-6 font-medium text-foreground">
                                                    {d.date}
                                                </td>
                                                <td className="p-3.5 font-mono">
                                                    {d.total_orders}
                                                </td>
                                                <td className="p-3.5 font-mono font-semibold text-red-600">
                                                    {d.high_risk}
                                                </td>
                                                <td className="p-3.5 font-mono font-semibold text-amber-600">
                                                    {d.medium_risk}
                                                </td>
                                                <td className="p-3.5 font-mono font-semibold text-emerald-600">
                                                    {d.low_risk}
                                                </td>
                                                <td className="p-3.5 font-mono text-emerald-600">
                                                    {d.approved}
                                                </td>
                                                <td className="p-3.5 font-mono text-red-600">
                                                    {d.rejected}
                                                </td>
                                                <td className="p-3.5 pr-6 text-right font-mono font-bold">
                                                    <span
                                                        className={
                                                            d.avg_score >= 66
                                                                ? 'text-red-600'
                                                                : d.avg_score >=
                                                                    31
                                                                  ? 'text-amber-600'
                                                                  : 'text-emerald-600'
                                                        }
                                                    >
                                                        {d.avg_score}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </AdminPage>
        </>
    );
}
