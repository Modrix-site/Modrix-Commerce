import { Head, router } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    CheckCircle2,
    Download,
    FileSpreadsheet,
    FileText,
    Filter,
    Globe,
    RefreshCw,
    ShieldAlert,
    TrendingUp,
    Users,
    XCircle,
} from 'lucide-react';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type DailyRow = {
    date: string;
    total_attempts: number;
    successful: number;
    failed: number;
    suspicious: number;
    incidents: number;
    avg_risk_score: number;
};

type FailureReason = {
    reason: string;
    count: number;
};

type TargetedEmail = {
    email: string;
    attempts: number;
    failed_count: number;
};

type TopIp = {
    ip_address: string;
    country: string;
    count: number;
    max_score: number;
};

type Props = {
    days: number;
    breakdown: DailyRow[];
    failureReasons: FailureReason[];
    topTargetedEmails: TargetedEmail[];
    topIps: TopIp[];
};

export default function LoginReports({
    days = 14,
    breakdown = [],
    failureReasons = [],
    topTargetedEmails = [],
    topIps = [],
}: Props) {
    const safeBreakdown = Array.isArray(breakdown) ? breakdown : [];
    const safeReasons = Array.isArray(failureReasons) ? failureReasons : [];
    const safeEmails = Array.isArray(topTargetedEmails)
        ? topTargetedEmails
        : [];
    const safeIps = Array.isArray(topIps) ? topIps : [];

    const totalAttempts = safeBreakdown.reduce(
        (acc, r) => acc + (r.total_attempts || 0),
        0,
    );
    const totalFailed = safeBreakdown.reduce(
        (acc, r) => acc + (r.failed || 0),
        0,
    );
    const totalSuspicious = safeBreakdown.reduce(
        (acc, r) => acc + (r.suspicious || 0),
        0,
    );
    const totalIncidents = safeBreakdown.reduce(
        (acc, r) => acc + (r.incidents || 0),
        0,
    );

    const handleDaysChange = (newDays: number) => {
        router.get('/admin/m/modrixcommerce/login-monitoring/reports', {
            days: newDays,
        });
    };

    return (
        <AdminPage>
            <Head title="Login Reports & Analytics - Login Monitoring" />

            <PageHeader
                title="Reports & Analytics"
                description="Comprehensive historical authentication telemetry, credential attack vectors, and security trends."
                actions={
                    <div className="flex items-center gap-2">
                        <a
                            href={`/admin/m/modrixcommerce/login-monitoring/reports/export/csv?days=${days}`}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <Button
                                variant="outline"
                                size="sm"
                                className="gap-2"
                            >
                                <FileText className="h-4 w-4" /> Export CSV
                            </Button>
                        </a>
                        <a
                            href={`/admin/m/modrixcommerce/login-monitoring/reports/export/excel?days=${days}`}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <Button
                                variant="outline"
                                size="sm"
                                className="gap-2"
                            >
                                <FileSpreadsheet className="h-4 w-4" /> Export
                                Excel
                            </Button>
                        </a>
                    </div>
                }
            />

            {/* Timeframe Selector & Aggregate Summary */}
            <div className="mb-6 flex flex-col items-center justify-between gap-4 sm:flex-row">
                <div className="flex items-center gap-1.5 rounded-lg border border-border/50 bg-muted/60 p-1">
                    {[7, 14, 30, 90].map((d) => (
                        <button
                            key={d}
                            onClick={() => handleDaysChange(d)}
                            className={`rounded-md px-3 py-1.5 text-xs font-medium transition ${
                                days === d
                                    ? 'bg-background text-foreground shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            Last {d} Days
                        </button>
                    ))}
                </div>

                <div className="text-xs text-muted-foreground">
                    Displaying authentication activity from past{' '}
                    <span className="font-bold text-foreground">
                        {days} days
                    </span>
                </div>
            </div>

            {/* High-Level Overview Metrics */}
            <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Card className="border border-border/60 shadow-sm">
                    <CardContent className="p-4">
                        <span className="text-xs font-semibold text-muted-foreground uppercase">
                            Total Inbound Logins
                        </span>
                        <div className="mt-2 text-2xl font-bold text-foreground">
                            {totalAttempts.toLocaleString()}
                        </div>
                    </CardContent>
                </Card>

                <Card className="border border-border/60 shadow-sm">
                    <CardContent className="p-4">
                        <span className="text-xs font-semibold text-muted-foreground uppercase">
                            Failed Authentications
                        </span>
                        <div className="mt-2 text-2xl font-bold text-rose-600 dark:text-rose-400">
                            {totalFailed.toLocaleString()}
                        </div>
                    </CardContent>
                </Card>

                <Card className="border border-border/60 shadow-sm">
                    <CardContent className="p-4">
                        <span className="text-xs font-semibold text-muted-foreground uppercase">
                            Suspicious Logins
                        </span>
                        <div className="mt-2 text-2xl font-bold text-amber-600 dark:text-amber-400">
                            {totalSuspicious.toLocaleString()}
                        </div>
                    </CardContent>
                </Card>

                <Card className="border border-border/60 shadow-sm">
                    <CardContent className="p-4">
                        <span className="text-xs font-semibold text-muted-foreground uppercase">
                            Security Incidents Triggered
                        </span>
                        <div className="mt-2 text-2xl font-bold text-red-600">
                            {totalIncidents.toLocaleString()}
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Attack Vectors & Risk Distribution Tables */}
            <div className="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
                {/* Failure Reasons */}
                <Card className="border border-border/60 shadow-sm">
                    <CardContent className="p-5">
                        <h3 className="mb-1 flex items-center gap-2 text-sm font-semibold">
                            <XCircle className="h-4 w-4 text-rose-500" /> Top
                            Failure Reasons
                        </h3>
                        <p className="mb-4 text-xs text-muted-foreground">
                            Most frequent login rejection causes.
                        </p>

                        <div className="space-y-3">
                            {safeReasons.length === 0 ? (
                                <p className="py-4 text-center text-xs text-muted-foreground">
                                    No login failures recorded.
                                </p>
                            ) : (
                                safeReasons.map((r, i) => (
                                    <div
                                        key={i}
                                        className="flex items-center justify-between border-b border-border/30 pb-2 text-xs"
                                    >
                                        <span className="max-w-[200px] truncate text-foreground">
                                            {r.reason}
                                        </span>
                                        <Badge
                                            variant="outline"
                                            className="font-bold text-rose-600"
                                        >
                                            {r.count}
                                        </Badge>
                                    </div>
                                ))
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Top Targeted Accounts */}
                <Card className="border border-border/60 shadow-sm">
                    <CardContent className="p-5">
                        <h3 className="mb-1 flex items-center gap-2 text-sm font-semibold">
                            <Users className="h-4 w-4 text-amber-500" />{' '}
                            Targeted Accounts
                        </h3>
                        <p className="mb-4 text-xs text-muted-foreground">
                            Emails encountering credential attack attempts.
                        </p>

                        <div className="space-y-3">
                            {safeEmails.length === 0 ? (
                                <p className="py-4 text-center text-xs text-muted-foreground">
                                    No repeated account attacks.
                                </p>
                            ) : (
                                safeEmails.map((e, i) => (
                                    <div
                                        key={i}
                                        className="flex items-center justify-between border-b border-border/30 pb-2 text-xs"
                                    >
                                        <span className="max-w-[180px] truncate font-medium text-foreground">
                                            {e.email}
                                        </span>
                                        <div className="flex items-center gap-2">
                                            <span className="text-muted-foreground">
                                                {e.attempts} tries
                                            </span>
                                            <Badge
                                                variant="destructive"
                                                className="bg-rose-600 font-mono text-[10px]"
                                            >
                                                {e.failed_count} fail
                                            </Badge>
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Top Attacking IPs */}
                <Card className="border border-border/60 shadow-sm">
                    <CardContent className="p-5">
                        <h3 className="mb-1 flex items-center gap-2 text-sm font-semibold">
                            <Globe className="h-4 w-4 text-purple-500" /> High
                            Activity IPs
                        </h3>
                        <p className="mb-4 text-xs text-muted-foreground">
                            Highest volume remote network origins.
                        </p>

                        <div className="space-y-3">
                            {safeIps.length === 0 ? (
                                <p className="py-4 text-center text-xs text-muted-foreground">
                                    No remote IPs recorded.
                                </p>
                            ) : (
                                safeIps.map((ip, i) => (
                                    <div
                                        key={i}
                                        className="flex items-center justify-between border-b border-border/30 pb-2 text-xs"
                                    >
                                        <div>
                                            <span className="font-mono font-medium text-foreground">
                                                {ip.ip_address}
                                            </span>
                                            <span className="ml-1.5 text-[10px] text-muted-foreground">
                                                ({ip.country})
                                            </span>
                                        </div>
                                        <Badge
                                            variant="outline"
                                            className="font-semibold"
                                        >
                                            {ip.count} reqs
                                        </Badge>
                                    </div>
                                ))
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Daily Breakdown Table */}
            <Card className="border border-border/60 shadow-sm">
                <CardContent className="p-6">
                    <h3 className="mb-1 flex items-center gap-2 text-base font-semibold">
                        <TrendingUp className="h-4 w-4 text-emerald-600" />{' '}
                        Daily Historical Breakdown
                    </h3>
                    <p className="mb-4 text-xs text-muted-foreground">
                        Daily aggregate timeline of authentication verdicts and
                        risk telemetry.
                    </p>

                    <div className="overflow-x-auto">
                        <table className="w-full text-xs">
                            <thead>
                                <tr className="border-b border-border/60 text-left text-muted-foreground">
                                    <th className="pb-2.5 font-medium">Date</th>
                                    <th className="pb-2.5 text-right font-medium">
                                        Total Attempts
                                    </th>
                                    <th className="pb-2.5 text-right font-medium">
                                        Successful
                                    </th>
                                    <th className="pb-2.5 text-right font-medium">
                                        Failed
                                    </th>
                                    <th className="pb-2.5 text-right font-medium">
                                        Suspicious
                                    </th>
                                    <th className="pb-2.5 text-right font-medium">
                                        Security Incidents
                                    </th>
                                    <th className="pb-2.5 text-right font-medium">
                                        Avg Risk Score
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border/30">
                                {safeBreakdown.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={7}
                                            className="py-8 text-center text-muted-foreground"
                                        >
                                            No daily history data found.
                                        </td>
                                    </tr>
                                ) : (
                                    safeBreakdown.map((row, idx) => (
                                        <tr
                                            key={idx}
                                            className="transition hover:bg-muted/30"
                                        >
                                            <td className="py-3 font-semibold text-foreground">
                                                {row.date}
                                            </td>
                                            <td className="py-3 text-right font-medium">
                                                {row.total_attempts}
                                            </td>
                                            <td className="py-3 text-right font-semibold text-emerald-600 dark:text-emerald-400">
                                                {row.successful}
                                            </td>
                                            <td className="py-3 text-right font-semibold text-rose-600 dark:text-rose-400">
                                                {row.failed}
                                            </td>
                                            <td className="py-3 text-right font-semibold text-amber-600 dark:text-amber-400">
                                                {row.suspicious}
                                            </td>
                                            <td className="py-3 text-right font-bold text-red-600">
                                                {row.incidents}
                                            </td>
                                            <td className="py-3 text-right font-mono">
                                                {row.avg_risk_score} / 100
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
    );
}
