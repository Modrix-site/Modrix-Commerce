import { Head, Link, router } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    BarChart3,
    Calendar,
    Download,
    FileSpreadsheet,
    FileText,
    Globe,
    Layers,
    Shield,
    ShieldAlert,
    ShieldCheck,
    TrendingUp,
} from 'lucide-react';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type DailyRecord = {
    date: string;
    total_requests: number;
    human_requests: number;
    bot_requests: number;
    blocked: number;
    challenged: number;
    scraping: number;
    credential_stuffing: number;
    checkout_abuse: number;
    avg_score: number;
};

type Props = {
    stats: {
        total_requests: number;
        human_requests: number;
        suspected_bots: number;
        blocked_requests: number;
        challenged_requests: number;
        threat_rate: number;
        active_blocked_ips: number;
    };
    dailySummary: DailyRecord[];
    threatDistribution: Record<string, number>;
    topAttackingIps: Array<{
        ip_address: string;
        count: number;
        bot_type: string;
    }>;
    selectedDays: number;
};

export default function BotReports({
    stats,
    dailySummary = [],
    threatDistribution = {},
    topAttackingIps = [],
    selectedDays = 14,
}: Props) {
    const safeSummary = Array.isArray(dailySummary) ? dailySummary : [];
    const safeStats = stats || {
        total_requests: 0,
        human_requests: 0,
        suspected_bots: 0,
        blocked_requests: 0,
        challenged_requests: 0,
        threat_rate: 0,
        active_blocked_ips: 0,
    };

    const handleDaysChange = (days: number) => {
        router.get(
            `/admin/m/modrixcommerce/bot-protection/reports?days=${days}`,
        );
    };

    return (
        <AdminPage>
            <Head title="Bot Protection - Reports & Analytics" />

            <PageHeader
                title="Bot Reports & Analytics"
                description="Comprehensive historical threat trends, scraping velocity analysis, and data export."
                actions={
                    <div className="flex items-center gap-2">
                        <a
                            href={`/admin/m/modrixcommerce/bot-protection/reports/export/csv?days=${selectedDays}`}
                            download
                        >
                            <Button
                                variant="outline"
                                size="sm"
                                className="gap-2 shadow-sm"
                            >
                                <Download className="h-4 w-4" /> Export CSV
                            </Button>
                        </a>
                        <a
                            href={`/admin/m/modrixcommerce/bot-protection/reports/export/excel?days=${selectedDays}`}
                            download
                        >
                            <Button
                                variant="outline"
                                size="sm"
                                className="gap-2 shadow-sm"
                            >
                                <FileSpreadsheet className="h-4 w-4" /> Export
                                Excel
                            </Button>
                        </a>
                    </div>
                }
            />

            {/* Date Range Selector */}
            <Card className="mb-6 border border-border/60 shadow-sm">
                <CardContent className="flex flex-wrap items-center justify-between gap-4 p-4">
                    <div className="flex items-center gap-2">
                        <Calendar className="h-4 w-4 text-muted-foreground" />
                        <span className="text-xs font-semibold text-foreground">
                            Report Timeline:
                        </span>
                        {[7, 14, 30].map((d) => (
                            <Button
                                key={d}
                                size="sm"
                                variant={
                                    selectedDays === d ? 'default' : 'outline'
                                }
                                className="h-7 px-3 text-xs"
                                onClick={() => handleDaysChange(d)}
                            >
                                Last {d} Days
                            </Button>
                        ))}
                    </div>
                    <span className="text-xs text-muted-foreground">
                        Auditing past {selectedDays} days of store traffic
                    </span>
                </CardContent>
            </Card>

            {/* KPI Cards */}
            <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Card className="border border-border/60 shadow-sm">
                    <CardContent className="p-5">
                        <span className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                            Total Scanned Traffic
                        </span>
                        <div className="mt-2 text-3xl font-bold text-foreground">
                            {safeStats.total_requests.toLocaleString()}
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Evaluated across all endpoints
                        </p>
                    </CardContent>
                </Card>

                <Card className="border border-border/60 shadow-sm">
                    <CardContent className="p-5">
                        <span className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                            Attacks Neutralized
                        </span>
                        <div className="mt-2 text-3xl font-bold text-rose-600 dark:text-rose-400">
                            {safeStats.blocked_requests.toLocaleString()}
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Blocked with HTTP 403 response
                        </p>
                    </CardContent>
                </Card>

                <Card className="border border-border/60 shadow-sm">
                    <CardContent className="p-5">
                        <span className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                            Captchas Triggered
                        </span>
                        <div className="mt-2 text-3xl font-bold text-purple-600 dark:text-purple-400">
                            {safeStats.challenged_requests.toLocaleString()}
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Interactive human challenges
                        </p>
                    </CardContent>
                </Card>

                <Card className="border border-border/60 shadow-sm">
                    <CardContent className="p-5">
                        <span className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                            Overall Threat Rate
                        </span>
                        <div className="mt-2 text-3xl font-bold text-amber-600 dark:text-amber-400">
                            {safeStats.threat_rate}%
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Percentage of automated traffic
                        </p>
                    </CardContent>
                </Card>
            </div>

            {/* Daily Breakdown Table */}
            <Card className="border border-border/60 shadow-sm">
                <CardContent className="p-6">
                    <h3 className="mb-1 flex items-center gap-2 text-base font-semibold">
                        <FileText className="h-4 w-4 text-primary" />
                        Daily Traffic & Attack Audit Breakdown
                    </h3>
                    <p className="mb-4 text-xs text-muted-foreground">
                        Historical timeline of traffic composition, blocked
                        incursions, and attack vectors.
                    </p>

                    <div className="overflow-x-auto">
                        <table className="w-full text-xs">
                            <thead>
                                <tr className="border-b border-border/60 text-left text-muted-foreground">
                                    <th className="pb-3 font-medium">Date</th>
                                    <th className="pb-3 text-right font-medium">
                                        Total Requests
                                    </th>
                                    <th className="pb-3 text-right font-medium">
                                        Human
                                    </th>
                                    <th className="pb-3 text-right font-medium">
                                        Bots
                                    </th>
                                    <th className="pb-3 text-right font-medium">
                                        Blocked
                                    </th>
                                    <th className="pb-3 text-right font-medium">
                                        Challenged
                                    </th>
                                    <th className="pb-3 text-right font-medium">
                                        Scraping
                                    </th>
                                    <th className="pb-3 text-right font-medium">
                                        Credential Stuffing
                                    </th>
                                    <th className="pb-3 text-right font-medium">
                                        Checkout Abuse
                                    </th>
                                    <th className="pb-3 text-right font-medium">
                                        Avg Score
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border/30">
                                {safeSummary.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={10}
                                            className="py-10 text-center text-muted-foreground"
                                        >
                                            No report records found for this
                                            period.
                                        </td>
                                    </tr>
                                ) : (
                                    safeSummary.map((day, i) => (
                                        <tr
                                            key={i}
                                            className="transition hover:bg-muted/30"
                                        >
                                            <td className="py-3 font-medium whitespace-nowrap text-foreground">
                                                {day.date}
                                            </td>
                                            <td className="py-3 text-right font-medium">
                                                {day.total_requests.toLocaleString()}
                                            </td>
                                            <td className="py-3 text-right font-medium text-emerald-600 dark:text-emerald-400">
                                                {day.human_requests.toLocaleString()}
                                            </td>
                                            <td className="py-3 text-right font-medium text-amber-600 dark:text-amber-400">
                                                {day.bot_requests.toLocaleString()}
                                            </td>
                                            <td className="py-3 text-right font-bold text-rose-600 dark:text-rose-400">
                                                {day.blocked.toLocaleString()}
                                            </td>
                                            <td className="py-3 text-right font-medium text-purple-600 dark:text-purple-400">
                                                {day.challenged.toLocaleString()}
                                            </td>
                                            <td className="py-3 text-right font-mono text-muted-foreground">
                                                {day.scraping}
                                            </td>
                                            <td className="py-3 text-right font-mono text-muted-foreground">
                                                {day.credential_stuffing}
                                            </td>
                                            <td className="py-3 text-right font-mono text-muted-foreground">
                                                {day.checkout_abuse}
                                            </td>
                                            <td className="py-3 text-right">
                                                <Badge
                                                    variant="outline"
                                                    className={
                                                        day.avg_score >= 50
                                                            ? 'border-rose-500/30 text-rose-600'
                                                            : ''
                                                    }
                                                >
                                                    {day.avg_score}
                                                </Badge>
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
