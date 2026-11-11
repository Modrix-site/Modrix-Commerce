import { Head, Link } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    ArrowUpRight,
    CheckCircle2,
    Clock,
    Fingerprint,
    Globe,
    Layers,
    Lock,
    ShieldAlert,
    Users,
    XCircle,
} from 'lucide-react';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type Stats = {
    total_attempts_24h: number;
    successful_logins_24h: number;
    failed_logins_24h: number;
    suspicious_logins_24h: number;
    active_sessions: number;
    open_incidents: number;
    currently_locked_accounts: number;
    success_rate: number;
};

type DailyTrend = {
    date: string;
    success: number;
    failed: number;
    suspicious: number;
};

type GeoLogin = {
    country: string;
    count: number;
};

type RecentEvent = {
    id: number;
    uuid: string;
    email: string;
    user_name: string | null;
    ip_address: string;
    status: string;
    failure_reason: string | null;
    risk_score: number;
    threat_level: string;
    browser: string;
    operating_system: string;
    device_type: string;
    country_code: string | null;
    city: string | null;
    is_suspicious: boolean;
    created_at: string;
    timestamp: string;
};

type Props = {
    stats: Stats;
    dailyTrends: DailyTrend[];
    geographicLogins: GeoLogin[];
    threatLevels: Record<string, number>;
    recentEvents: RecentEvent[];
};

export default function LoginDashboard({
    stats,
    dailyTrends = [],
    geographicLogins = [],
    threatLevels = {},
    recentEvents = [],
}: Props) {
    const safeStats = stats || {
        total_attempts_24h: 0,
        successful_logins_24h: 0,
        failed_logins_24h: 0,
        suspicious_logins_24h: 0,
        active_sessions: 0,
        open_incidents: 0,
        currently_locked_accounts: 0,
        success_rate: 100,
    };

    const safeTrends = Array.isArray(dailyTrends) ? dailyTrends : [];
    const safeGeo = Array.isArray(geographicLogins) ? geographicLogins : [];
    const safeEvents = Array.isArray(recentEvents) ? recentEvents : [];

    const maxTrendTotal = Math.max(
        1,
        ...safeTrends.map((t) => (t.success || 0) + (t.failed || 0)),
    );

    const threatBadge = (level: string) => {
        switch (level) {
            case 'critical':
                return (
                    <Badge
                        variant="destructive"
                        className="bg-red-600 font-semibold text-white"
                    >
                        Critical
                    </Badge>
                );
            case 'high':
                return (
                    <Badge
                        variant="destructive"
                        className="bg-amber-600 font-semibold text-white"
                    >
                        High
                    </Badge>
                );
            case 'medium':
                return (
                    <Badge
                        variant="secondary"
                        className="border-yellow-500/30 bg-yellow-500/20 text-yellow-600 dark:text-yellow-400"
                    >
                        Elevated
                    </Badge>
                );
            default:
                return (
                    <Badge
                        variant="outline"
                        className="border-emerald-500/30 text-emerald-600 dark:text-emerald-400"
                    >
                        Low Risk
                    </Badge>
                );
        }
    };

    const statusBadge = (status: string) => {
        if (status === 'success') {
            return (
                <span className="inline-flex items-center gap-1 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                    <CheckCircle2 className="h-3.5 w-3.5" /> Successful
                </span>
            );
        }
        return (
            <span className="inline-flex items-center gap-1 text-xs font-medium text-rose-600 dark:text-rose-400">
                <XCircle className="h-3.5 w-3.5" /> Failed
            </span>
        );
    };

    return (
        <AdminPage>
            <Head title="Login Monitoring Dashboard" />

            <PageHeader
                title="Login Monitoring"
                description="Live authentication intelligence, brute-force defense, session security, and credential audit."
                actions={
                    <div className="flex items-center gap-2">
                        <Link href="/admin/m/modrixcommerce/login-monitoring/activity">
                            <Button variant="outline" className="gap-2">
                                <Activity className="h-4 w-4 text-emerald-600" />
                                Live Stream
                            </Button>
                        </Link>
                        <Link href="/admin/m/modrixcommerce/login-monitoring/queue">
                            <Button className="gap-2 bg-emerald-600 text-white hover:bg-emerald-700">
                                <ShieldAlert className="h-4 w-4" />
                                Suspicious Queue
                                {safeStats.suspicious_logins_24h > 0 && (
                                    <span className="ml-1 rounded-full bg-white/20 px-1.5 py-0.5 text-[10px] font-bold">
                                        {safeStats.suspicious_logins_24h}
                                    </span>
                                )}
                            </Button>
                        </Link>
                    </div>
                }
            />

            {/* Lockout Notification Alert Banner */}
            {safeStats.currently_locked_accounts > 0 && (
                <div className="mb-6 flex items-center justify-between rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-amber-900 shadow-sm dark:text-amber-200">
                    <div className="flex items-center gap-3">
                        <Lock className="h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" />
                        <div>
                            <h4 className="text-sm font-semibold">
                                {safeStats.currently_locked_accounts} Account(s)
                                Currently Locked Out
                            </h4>
                            <p className="text-xs text-amber-700 dark:text-amber-300">
                                Temporarily blocked due to repeated failed login
                                attempts violating brute-force thresholds.
                            </p>
                        </div>
                    </div>
                    <Link href="/admin/m/modrixcommerce/login-monitoring/policies">
                        <Button
                            size="sm"
                            variant="outline"
                            className="border-amber-500/40 text-xs"
                        >
                            Review Lockout Policies
                        </Button>
                    </Link>
                </div>
            )}

            {/* Top Metric Cards */}
            <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Card className="border border-border/60 bg-gradient-to-br from-card to-card/60 shadow-sm transition hover:shadow">
                    <CardContent className="p-5">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Total Logins (24h)
                            </span>
                            <div className="rounded-lg bg-emerald-500/10 p-2 text-emerald-600 dark:text-emerald-400">
                                <Fingerprint className="h-4 w-4" />
                            </div>
                        </div>
                        <div className="mt-3 text-3xl font-bold tracking-tight text-foreground">
                            {safeStats.total_attempts_24h.toLocaleString()}
                        </div>
                        <div className="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                            <span className="font-medium text-emerald-600 dark:text-emerald-400">
                                {safeStats.success_rate}%
                            </span>{' '}
                            success rate across all attempts
                        </div>
                    </CardContent>
                </Card>

                <Card className="border border-border/60 bg-gradient-to-br from-card to-card/60 shadow-sm transition hover:shadow">
                    <CardContent className="p-5">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Failed Logins
                            </span>
                            <div className="rounded-lg bg-rose-500/10 p-2 text-rose-600 dark:text-rose-400">
                                <XCircle className="h-4 w-4" />
                            </div>
                        </div>
                        <div className="mt-3 text-3xl font-bold tracking-tight text-rose-600 dark:text-rose-400">
                            {safeStats.failed_logins_24h.toLocaleString()}
                        </div>
                        <div className="mt-1 flex items-center gap-1 text-xs text-muted-foreground">
                            <span className="font-medium text-amber-600 dark:text-amber-400">
                                {safeStats.currently_locked_accounts}
                            </span>{' '}
                            currently locked by brute force
                        </div>
                    </CardContent>
                </Card>

                <Card className="border border-border/60 bg-gradient-to-br from-card to-card/60 shadow-sm transition hover:shadow">
                    <CardContent className="p-5">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Suspicious Logins
                            </span>
                            <div className="rounded-lg bg-amber-500/10 p-2 text-amber-600 dark:text-amber-400">
                                <AlertTriangle className="h-4 w-4" />
                            </div>
                        </div>
                        <div className="mt-3 text-3xl font-bold tracking-tight text-amber-600 dark:text-amber-400">
                            {safeStats.suspicious_logins_24h.toLocaleString()}
                        </div>
                        <div className="mt-1 flex items-center gap-1 text-xs text-muted-foreground">
                            <span className="font-medium text-rose-600 dark:text-rose-400">
                                {safeStats.open_incidents}
                            </span>{' '}
                            security incidents flagged
                        </div>
                    </CardContent>
                </Card>

                <Card className="border border-border/60 bg-gradient-to-br from-card to-card/60 shadow-sm transition hover:shadow">
                    <CardContent className="p-5">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Active User Sessions
                            </span>
                            <div className="rounded-lg bg-blue-500/10 p-2 text-blue-600 dark:text-blue-400">
                                <Users className="h-4 w-4" />
                            </div>
                        </div>
                        <div className="mt-3 text-3xl font-bold tracking-tight text-foreground">
                            {safeStats.active_sessions.toLocaleString()}
                        </div>
                        <div className="mt-1 flex items-center gap-1 text-xs text-muted-foreground">
                            <Link
                                href="/admin/m/modrixcommerce/login-monitoring/sessions"
                                className="text-blue-600 hover:underline"
                            >
                                Inspect active sessions &rarr;
                            </Link>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* 7-Day Trend Chart & Threat Level Distribution */}
            <div className="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
                <Card className="border border-border/60 shadow-sm lg:col-span-2">
                    <CardContent className="p-6">
                        <div className="mb-4 flex items-center justify-between">
                            <div>
                                <h3 className="flex items-center gap-2 text-base font-semibold">
                                    <Activity className="h-4 w-4 text-emerald-600" />
                                    7-Day Login Activity Trends
                                </h3>
                                <p className="text-xs text-muted-foreground">
                                    Comparison of successful logins,
                                    authentication failures, and suspicious
                                    alerts.
                                </p>
                            </div>
                            <div className="flex items-center gap-3 text-xs">
                                <span className="flex items-center gap-1.5">
                                    <span className="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>{' '}
                                    Successful
                                </span>
                                <span className="flex items-center gap-1.5">
                                    <span className="h-2.5 w-2.5 rounded-full bg-rose-500"></span>{' '}
                                    Failed
                                </span>
                                <span className="flex items-center gap-1.5">
                                    <span className="h-2.5 w-2.5 rounded-full bg-amber-500"></span>{' '}
                                    Suspicious
                                </span>
                            </div>
                        </div>

                        {/* Interactive Bar Visualization */}
                        <div className="flex h-48 items-end gap-3 border-b border-border/40 pt-6 pb-2">
                            {safeTrends.map((t, idx) => {
                                const total =
                                    (t.success || 0) + (t.failed || 0);
                                const successH = Math.round(
                                    ((t.success || 0) / maxTrendTotal) * 100,
                                );
                                const failedH = Math.round(
                                    ((t.failed || 0) / maxTrendTotal) * 100,
                                );
                                const _suspH = Math.round(
                                    ((t.suspicious || 0) / maxTrendTotal) * 100,
                                );

                                return (
                                    <div
                                        key={idx}
                                        className="group relative flex h-full flex-1 flex-col items-center justify-end"
                                    >
                                        <div className="flex w-full flex-col justify-end gap-0.5 overflow-hidden rounded-t">
                                            {failedH > 0 && (
                                                <div
                                                    style={{
                                                        height: `${Math.max(4, failedH)}%`,
                                                    }}
                                                    className="w-full rounded-t bg-rose-500/80 transition-all group-hover:bg-rose-500"
                                                />
                                            )}
                                            <div
                                                style={{
                                                    height: `${Math.max(successH > 0 ? 4 : 0, successH)}%`,
                                                }}
                                                className="w-full bg-emerald-500/80 transition-all group-hover:bg-emerald-500"
                                            />
                                        </div>
                                        <span className="mt-2 text-[11px] font-medium text-muted-foreground">
                                            {t.date}
                                        </span>
                                        {/* Tooltip */}
                                        <div className="pointer-events-none absolute bottom-full z-20 mb-2 hidden flex-col rounded-lg border border-border bg-popover p-2.5 text-[11px] whitespace-nowrap text-popover-foreground shadow-xl group-hover:flex">
                                            <span className="mb-1 border-b border-border/50 pb-1 font-bold">
                                                {t.date}
                                            </span>
                                            <span className="text-emerald-500">
                                                Successful: {t.success}
                                            </span>
                                            <span className="text-rose-500">
                                                Failed: {t.failed}
                                            </span>
                                            <span className="text-amber-500">
                                                Suspicious: {t.suspicious}
                                            </span>
                                            <span className="border-t border-border/40 pt-1 font-medium text-muted-foreground">
                                                Total: {total}
                                            </span>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </CardContent>
                </Card>

                {/* Threat Level Distribution & Geographies */}
                <Card className="border border-border/60 shadow-sm">
                    <CardContent className="p-6">
                        <h3 className="mb-1 flex items-center gap-2 text-base font-semibold">
                            <Layers className="h-4 w-4 text-emerald-600" />
                            Risk Level Breakdown (24h)
                        </h3>
                        <p className="mb-4 text-xs text-muted-foreground">
                            Calculated heuristics across all login attempts.
                        </p>

                        <div className="mb-6 space-y-3.5">
                            {[
                                {
                                    key: 'low',
                                    label: 'Low Risk (Score 0-24)',
                                    color: 'bg-emerald-500',
                                    count: threatLevels['low'] || 0,
                                },
                                {
                                    key: 'medium',
                                    label: 'Medium Risk (Score 25-49)',
                                    color: 'bg-yellow-500',
                                    count: threatLevels['medium'] || 0,
                                },
                                {
                                    key: 'high',
                                    label: 'High Risk (Score 50-79)',
                                    color: 'bg-amber-500',
                                    count: threatLevels['high'] || 0,
                                },
                                {
                                    key: 'critical',
                                    label: 'Critical Risk (Score 80-100)',
                                    color: 'bg-rose-600',
                                    count: threatLevels['critical'] || 0,
                                },
                            ].map((item) => {
                                const total = Math.max(
                                    1,
                                    Object.values(threatLevels).reduce(
                                        (a, b) => a + b,
                                        0,
                                    ),
                                );
                                const pct = Math.round(
                                    (item.count / total) * 100,
                                );
                                return (
                                    <div key={item.key} className="space-y-1">
                                        <div className="flex justify-between text-xs">
                                            <span className="font-medium text-foreground">
                                                {item.label}
                                            </span>
                                            <span className="font-semibold text-muted-foreground">
                                                {item.count} ({pct}%)
                                            </span>
                                        </div>
                                        <div className="h-2 w-full overflow-hidden rounded-full bg-muted">
                                            <div
                                                className={`h-full ${item.color} rounded-full`}
                                                style={{
                                                    width: `${Math.min(100, Math.max(item.count > 0 ? 5 : 0, pct))}%`,
                                                }}
                                            />
                                        </div>
                                    </div>
                                );
                            })}
                        </div>

                        {/* Top Geographic Logins */}
                        <div className="border-t border-border/40 pt-4">
                            <h4 className="mb-2 flex items-center gap-1.5 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                <Globe className="h-3.5 w-3.5 text-blue-500" />{' '}
                                Top Login Origins
                            </h4>
                            <div className="flex flex-wrap gap-2">
                                {safeGeo.length === 0 ? (
                                    <span className="text-xs text-muted-foreground">
                                        No geographic data logged.
                                    </span>
                                ) : (
                                    safeGeo.map((g, i) => (
                                        <span
                                            key={i}
                                            className="inline-flex items-center gap-1 rounded-md bg-muted px-2 py-1 text-xs font-medium"
                                        >
                                            <span className="font-bold">
                                                {g.country}
                                            </span>
                                            <span className="text-muted-foreground">
                                                ({g.count})
                                            </span>
                                        </span>
                                    ))
                                )}
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Recent Login Events Feed */}
            <Card className="border border-border/60 shadow-sm">
                <CardContent className="p-6">
                    <div className="mb-4 flex items-center justify-between">
                        <div>
                            <h3 className="flex items-center gap-2 text-base font-semibold">
                                <Clock className="h-4 w-4 text-emerald-600" />
                                Real-Time Login Stream
                            </h3>
                            <p className="text-xs text-muted-foreground">
                                Recent authentication requests and access
                                verdicts.
                            </p>
                        </div>
                        <Link href="/admin/m/modrixcommerce/login-monitoring/activity">
                            <Button
                                variant="outline"
                                size="sm"
                                className="gap-1 text-xs"
                            >
                                Full Live Log{' '}
                                <ArrowUpRight className="h-3 w-3" />
                            </Button>
                        </Link>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-xs">
                            <thead>
                                <tr className="border-b border-border/60 text-left text-muted-foreground">
                                    <th className="pb-2.5 font-medium">
                                        Status
                                    </th>
                                    <th className="pb-2.5 font-medium">
                                        Account
                                    </th>
                                    <th className="pb-2.5 font-medium">
                                        IP Address
                                    </th>
                                    <th className="pb-2.5 font-medium">
                                        Device & Browser
                                    </th>
                                    <th className="pb-2.5 font-medium">
                                        Location
                                    </th>
                                    <th className="pb-2.5 font-medium">
                                        Risk Score
                                    </th>
                                    <th className="pb-2.5 text-right font-medium">
                                        Time
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border/30">
                                {safeEvents.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={7}
                                            className="py-8 text-center text-muted-foreground"
                                        >
                                            No login events tracked yet. Logins
                                            will appear here in real time.
                                        </td>
                                    </tr>
                                ) : (
                                    safeEvents.map((event) => (
                                        <tr
                                            key={event.id}
                                            className="transition hover:bg-muted/30"
                                        >
                                            <td className="py-3">
                                                {statusBadge(event.status)}
                                            </td>
                                            <td className="py-3">
                                                <div className="font-medium text-foreground">
                                                    {event.email}
                                                </div>
                                                {event.user_name && (
                                                    <div className="text-[11px] text-muted-foreground">
                                                        {event.user_name}
                                                    </div>
                                                )}
                                                {event.failure_reason && (
                                                    <div className="max-w-xs truncate text-[10px] font-medium text-rose-500">
                                                        {event.failure_reason}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="py-3 font-mono text-muted-foreground">
                                                {event.ip_address}
                                            </td>
                                            <td className="py-3">
                                                <div className="font-medium text-foreground">
                                                    {event.browser}
                                                </div>
                                                <div className="text-[11px] text-muted-foreground">
                                                    {event.operating_system}{' '}
                                                    &bull; {event.device_type}
                                                </div>
                                            </td>
                                            <td className="py-3">
                                                <span>
                                                    {event.city
                                                        ? `${event.city}, `
                                                        : ''}
                                                    {event.country_code ||
                                                        'Unknown'}
                                                </span>
                                            </td>
                                            <td className="py-3">
                                                <div className="flex items-center gap-2">
                                                    <span className="font-bold">
                                                        {event.risk_score}/100
                                                    </span>
                                                    {threatBadge(
                                                        event.threat_level,
                                                    )}
                                                </div>
                                            </td>
                                            <td className="py-3 text-right whitespace-nowrap text-muted-foreground">
                                                {event.created_at}
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
