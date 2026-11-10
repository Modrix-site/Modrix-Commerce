import { Head, Link, router } from '@inertiajs/react';
import {
    Activity,
    AlertOctagon,
    AlertTriangle,
    ArrowUpRight,
    Ban,
    Bot,
    CheckCircle2,
    Clock,
    Globe,
    Layers,
    Lock,
    RefreshCw,
    Shield,
    ShieldAlert,
    ShieldCheck,
    TrendingUp,
    Zap,
} from 'lucide-react';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type Stats = {
    total_requests: number;
    human_requests: number;
    suspected_bots: number;
    blocked_requests: number;
    challenged_requests: number;
    threat_rate: number;
    active_blocked_ips: number;
    unresolved_incidents: number;
    is_lockdown_active: boolean;
};

type HourlyTrend = {
    hour: string;
    total: number;
    blocked: number;
    challenged: number;
    human: number;
};

type AttackingIp = {
    ip_address: string;
    country_code: string | null;
    bot_type: string;
    count: number;
    max_score: number;
};

type TargetedPage = {
    request_uri: string;
    total_hits: number;
    bot_hits: number;
};

type Incident = {
    id: number;
    event_uuid: string;
    ip_address: string;
    bot_type: string;
    risk_score: number;
    threat_level: string;
    action_taken: string;
    reasons: Array<{ indicator: string; points: number }>;
    country_code: string | null;
    created_at: string;
};

type Props = {
    stats: Stats;
    hourlyTrends: HourlyTrend[];
    topAttackingIps: AttackingIp[];
    topTargetedPages: TargetedPage[];
    threatDistribution: Record<string, number>;
    recentIncidents: Incident[];
};

export default function BotDashboard({
    stats,
    hourlyTrends = [],
    topAttackingIps = [],
    topTargetedPages = [],
    threatDistribution = {},
    recentIncidents = [],
}: Props) {
    const safeStats = stats || {
        total_requests: 0,
        human_requests: 0,
        suspected_bots: 0,
        blocked_requests: 0,
        challenged_requests: 0,
        threat_rate: 0,
        active_blocked_ips: 0,
        unresolved_incidents: 0,
        is_lockdown_active: false,
    };

    const safeTrends = Array.isArray(hourlyTrends) ? hourlyTrends : [];
    const safeAttackingIps = Array.isArray(topAttackingIps) ? topAttackingIps : [];
    const safeTargetedPages = Array.isArray(topTargetedPages) ? topTargetedPages : [];
    const safeIncidents = Array.isArray(recentIncidents) ? recentIncidents : [];

    const maxTrendTotal = Math.max(1, ...safeTrends.map((t) => t.total || 0));

    const threatBadge = (level: string) => {
        switch (level) {
            case 'critical':
                return <Badge variant="destructive" className="bg-red-600 text-white font-semibold">Critical</Badge>;
            case 'high':
                return <Badge variant="destructive" className="bg-amber-600 text-white font-semibold">High</Badge>;
            case 'medium':
                return <Badge variant="secondary" className="bg-yellow-500/20 text-yellow-600 dark:text-yellow-400 border-yellow-500/30">Suspicious</Badge>;
            default:
                return <Badge variant="outline" className="text-emerald-600 dark:text-emerald-400 border-emerald-500/30">Low Risk</Badge>;
        }
    };

    const actionBadge = (action: string) => {
        switch (action) {
            case 'block':
                return <Badge className="bg-rose-500/15 text-rose-600 dark:text-rose-400 border-rose-500/20">Blocked</Badge>;
            case 'challenge':
                return <Badge className="bg-amber-500/15 text-amber-600 dark:text-amber-400 border-amber-500/20">Challenged</Badge>;
            case 'throttle':
                return <Badge className="bg-purple-500/15 text-purple-600 dark:text-purple-400 border-purple-500/20">Throttled</Badge>;
            default:
                return <Badge variant="outline">Monitored</Badge>;
        }
    };

    const formatBotType = (type: string) => {
        return type.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
    };

    return (
        <AdminPage>
            <Head title="Bot Protection Dashboard" />

            <PageHeader
                title="Bot Protection"
                description="Real-time traffic mitigation, automated scraper defense, and checkout security engine."
                actions={
                    <div className="flex items-center gap-2">
                        <Button
                            variant={safeStats.is_lockdown_active ? 'destructive' : 'outline'}
                            onClick={() => router.post('/admin/m/modrixcommerce/bot-protection/security/lockdown')}
                            className="gap-2 shadow-sm"
                        >
                            <AlertOctagon className="h-4 w-4" />
                            {safeStats.is_lockdown_active ? 'Lockdown Active (Click to Disable)' : 'Panic Mode / Lockdown'}
                        </Button>
                        <Link href="/admin/m/modrixcommerce/bot-protection/security">
                            <Button className="gap-2">
                                <Shield className="h-4 w-4" />
                                Security Center
                            </Button>
                        </Link>
                    </div>
                }
            />

            {/* Emergency Panic Banner if active */}
            {safeStats.is_lockdown_active && (
                <div className="mb-6 rounded-xl border border-red-500/50 bg-red-500/10 p-4 text-red-900 dark:text-red-200 flex items-center justify-between shadow-sm animate-pulse">
                    <div className="flex items-center gap-3">
                        <AlertOctagon className="h-6 w-6 text-red-600 dark:text-red-400 shrink-0" />
                        <div>
                            <h4 className="font-semibold text-sm">Emergency Lockdown Mode is ACTIVE</h4>
                            <p className="text-xs text-red-700 dark:text-red-300">
                                All untrusted traffic is being actively challenged with interactive security verification.
                            </p>
                        </div>
                    </div>
                    <Button
                        size="sm"
                        variant="destructive"
                        onClick={() => router.post('/admin/m/modrixcommerce/bot-protection/security/lockdown')}
                    >
                        Deactivate Lockdown
                    </Button>
                </div>
            )}

            {/* KPI Metric Cards */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
                <Card className="border border-border/60 bg-gradient-to-br from-card to-card/60 shadow-sm hover:shadow transition">
                    <CardContent className="p-5">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Total Visitors
                            </span>
                            <div className="rounded-lg bg-blue-500/10 p-2 text-blue-600 dark:text-blue-400">
                                <Activity className="h-4 w-4" />
                            </div>
                        </div>
                        <div className="mt-3 text-3xl font-bold tracking-tight text-foreground">
                            {safeStats.total_requests.toLocaleString()}
                        </div>
                        <div className="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                            <span className="text-emerald-600 dark:text-emerald-400 font-medium">
                                {safeStats.human_requests.toLocaleString()}
                            </span>{' '}
                            human sessions today
                        </div>
                    </CardContent>
                </Card>

                <Card className="border border-border/60 bg-gradient-to-br from-card to-card/60 shadow-sm hover:shadow transition">
                    <CardContent className="p-5">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Suspected Bots
                            </span>
                            <div className="rounded-lg bg-amber-500/10 p-2 text-amber-600 dark:text-amber-400">
                                <Bot className="h-4 w-4" />
                            </div>
                        </div>
                        <div className="mt-3 text-3xl font-bold tracking-tight text-foreground">
                            {safeStats.suspected_bots.toLocaleString()}
                        </div>
                        <div className="mt-1 flex items-center gap-1 text-xs text-muted-foreground">
                            <span className="font-medium text-amber-600 dark:text-amber-400">
                                {safeStats.threat_rate}%
                            </span>{' '}
                            of total store traffic
                        </div>
                    </CardContent>
                </Card>

                <Card className="border border-border/60 bg-gradient-to-br from-card to-card/60 shadow-sm hover:shadow transition">
                    <CardContent className="p-5">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Blocked Threats
                            </span>
                            <div className="rounded-lg bg-rose-500/10 p-2 text-rose-600 dark:text-rose-400">
                                <Ban className="h-4 w-4" />
                            </div>
                        </div>
                        <div className="mt-3 text-3xl font-bold tracking-tight text-rose-600 dark:text-rose-400">
                            {safeStats.blocked_requests.toLocaleString()}
                        </div>
                        <div className="mt-1 flex items-center gap-1 text-xs text-muted-foreground">
                            <span className="font-medium text-purple-600 dark:text-purple-400">
                                {safeStats.challenged_requests.toLocaleString()}
                            </span>{' '}
                            captchas challenged
                        </div>
                    </CardContent>
                </Card>

                <Card className="border border-border/60 bg-gradient-to-br from-card to-card/60 shadow-sm hover:shadow transition">
                    <CardContent className="p-5">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Active Blacklist
                            </span>
                            <div className="rounded-lg bg-purple-500/10 p-2 text-purple-600 dark:text-purple-400">
                                <ShieldCheck className="h-4 w-4" />
                            </div>
                        </div>
                        <div className="mt-3 text-3xl font-bold tracking-tight text-foreground">
                            {safeStats.active_blocked_ips.toLocaleString()}
                        </div>
                        <div className="mt-1 flex items-center gap-1 text-xs text-muted-foreground">
                            <span className="font-medium text-blue-600 dark:text-blue-400">
                                {safeStats.unresolved_incidents}
                            </span>{' '}
                            incidents awaiting review
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Threat Trends Over 24 Hours & Vector Distribution */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                <Card className="lg:col-span-2 border border-border/60 shadow-sm">
                    <CardContent className="p-6">
                        <div className="flex items-center justify-between mb-4">
                            <div>
                                <h3 className="font-semibold text-base flex items-center gap-2">
                                    <TrendingUp className="h-4 w-4 text-primary" />
                                    24-Hour Threat & Traffic Timeline
                                </h3>
                                <p className="text-xs text-muted-foreground">
                                    Hourly distribution of human traffic, bot challenges, and blocked attacks.
                                </p>
                            </div>
                            <div className="flex items-center gap-3 text-xs">
                                <span className="flex items-center gap-1.5">
                                    <span className="h-2.5 w-2.5 rounded-full bg-blue-500"></span> Human
                                </span>
                                <span className="flex items-center gap-1.5">
                                    <span className="h-2.5 w-2.5 rounded-full bg-amber-500"></span> Challenged
                                </span>
                                <span className="flex items-center gap-1.5">
                                    <span className="h-2.5 w-2.5 rounded-full bg-rose-500"></span> Blocked
                                </span>
                            </div>
                        </div>

                        {/* Interactive SVG Bar Visualization */}
                        <div className="h-48 flex items-end gap-1.5 pt-6 border-b border-border/40 pb-2">
                            {safeTrends.map((t, idx) => {
                                const humanH = Math.round((t.human / maxTrendTotal) * 100);
                                const chalH = Math.round((t.challenged / maxTrendTotal) * 100);
                                const blockH = Math.round((t.blocked / maxTrendTotal) * 100);
                                return (
                                    <div
                                        key={idx}
                                        className="flex-1 flex flex-col justify-end items-center h-full group relative"
                                    >
                                        <div className="w-full flex flex-col justify-end rounded-t overflow-hidden">
                                            {blockH > 0 && (
                                                <div
                                                    style={{ height: `${Math.max(4, blockH)}%` }}
                                                    className="w-full bg-rose-500/80 transition-all group-hover:bg-rose-500"
                                                />
                                            )}
                                            {chalH > 0 && (
                                                <div
                                                    style={{ height: `${Math.max(4, chalH)}%` }}
                                                    className="w-full bg-amber-500/80 transition-all group-hover:bg-amber-500"
                                                />
                                            )}
                                            <div
                                                style={{ height: `${Math.max(humanH > 0 ? 4 : 0, humanH)}%` }}
                                                className="w-full bg-blue-500/70 transition-all group-hover:bg-blue-500"
                                            />
                                        </div>
                                        <span className="text-[10px] text-muted-foreground mt-1 opacity-70">
                                            {idx % 4 === 0 ? t.hour : ''}
                                        </span>
                                        {/* Hover Tooltip */}
                                        <div className="absolute bottom-full mb-2 hidden group-hover:flex flex-col bg-popover text-popover-foreground text-[11px] p-2 rounded shadow-lg border border-border whitespace-nowrap z-20 pointer-events-none">
                                            <span className="font-bold">{t.hour}</span>
                                            <span>Total: {t.total}</span>
                                            <span className="text-emerald-500">Human: {t.human}</span>
                                            <span className="text-amber-500">Challenged: {t.challenged}</span>
                                            <span className="text-rose-500">Blocked: {t.blocked}</span>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </CardContent>
                </Card>

                {/* Threat Vector Distribution */}
                <Card className="border border-border/60 shadow-sm">
                    <CardContent className="p-6">
                        <h3 className="font-semibold text-base mb-1 flex items-center gap-2">
                            <Layers className="h-4 w-4 text-primary" />
                            Attack Vectors (7 Days)
                        </h3>
                        <p className="text-xs text-muted-foreground mb-4">
                            Categorized heuristic bot infractions.
                        </p>

                        <div className="space-y-3">
                            {Object.entries(threatDistribution).length === 0 ? (
                                <p className="text-xs text-muted-foreground py-6 text-center">No attack vectors recorded yet.</p>
                            ) : (
                                Object.entries(threatDistribution).map(([type, count]) => (
                                    <div key={type} className="space-y-1">
                                        <div className="flex justify-between text-xs">
                                            <span className="font-medium text-foreground">{formatBotType(type)}</span>
                                            <span className="text-muted-foreground font-semibold">{count}</span>
                                        </div>
                                        <div className="h-2 w-full rounded-full bg-muted overflow-hidden">
                                            <div
                                                className="h-full bg-blue-600 rounded-full"
                                                style={{ width: `${Math.min(100, Math.max(8, (count / Math.max(1, ...Object.values(threatDistribution))) * 100))}%` }}
                                            />
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Top Attacking IPs & Top Targeted Endpoints */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <Card className="border border-border/60 shadow-sm">
                    <CardContent className="p-6">
                        <div className="flex items-center justify-between mb-4">
                            <div>
                                <h3 className="font-semibold text-base flex items-center gap-2">
                                    <Globe className="h-4 w-4 text-rose-500" />
                                    Top Threat Origin IPs
                                </h3>
                                <p className="text-xs text-muted-foreground">Highest frequency offending IP addresses.</p>
                            </div>
                            <Link href="/admin/m/modrixcommerce/bot-protection/ips">
                                <Button variant="ghost" size="sm" className="gap-1 text-xs">
                                    Manage IPs <ArrowUpRight className="h-3 w-3" />
                                </Button>
                            </Link>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full text-xs">
                                <thead>
                                    <tr className="border-b border-border/60 text-muted-foreground text-left">
                                        <th className="pb-2 font-medium">IP Address</th>
                                        <th className="pb-2 font-medium">Country</th>
                                        <th className="pb-2 font-medium">Threat Vector</th>
                                        <th className="pb-2 font-medium text-right">Attacks</th>
                                        <th className="pb-2 font-medium text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border/30">
                                    {safeAttackingIps.length === 0 ? (
                                        <tr>
                                            <td colSpan={5} className="py-6 text-center text-muted-foreground">
                                                No attack IPs recorded.
                                            </td>
                                        </tr>
                                    ) : (
                                        safeAttackingIps.map((row, i) => (
                                            <tr key={i} className="hover:bg-muted/30 transition">
                                                <td className="py-2.5 font-mono font-medium text-foreground">{row.ip_address}</td>
                                                <td className="py-2.5">{row.country_code || 'Global'}</td>
                                                <td className="py-2.5">
                                                    <span className="rounded bg-muted px-1.5 py-0.5 text-[10px]">
                                                        {formatBotType(row.bot_type)}
                                                    </span>
                                                </td>
                                                <td className="py-2.5 text-right font-bold text-rose-600 dark:text-rose-400">
                                                    {row.count}
                                                </td>
                                                <td className="py-2.5 text-right">
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        className="h-6 px-2 text-[11px] text-rose-600 hover:text-rose-700 hover:bg-rose-500/10"
                                                        onClick={() => router.post('/admin/m/modrixcommerce/bot-protection/ips/blocked', {
                                                            ip_address: row.ip_address,
                                                            reason: `High frequency attacks (${row.count} hits)`,
                                                            attack_type: row.bot_type,
                                                        })}
                                                    >
                                                        Block
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                {/* Top Targeted Pages */}
                <Card className="border border-border/60 shadow-sm">
                    <CardContent className="p-6">
                        <div className="flex items-center justify-between mb-4">
                            <div>
                                <h3 className="font-semibold text-base flex items-center gap-2">
                                    <Zap className="h-4 w-4 text-amber-500" />
                                    Top Targeted Endpoints
                                </h3>
                                <p className="text-xs text-muted-foreground">Store routes encountering the most automated traffic.</p>
                            </div>
                            <Link href="/admin/m/modrixcommerce/bot-protection/traffic">
                                <Button variant="ghost" size="sm" className="gap-1 text-xs">
                                    Analytics <ArrowUpRight className="h-3 w-3" />
                                </Button>
                            </Link>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full text-xs">
                                <thead>
                                    <tr className="border-b border-border/60 text-muted-foreground text-left">
                                        <th className="pb-2 font-medium">Route Path</th>
                                        <th className="pb-2 font-medium text-right">Total Hits</th>
                                        <th className="pb-2 font-medium text-right">Bot Hits</th>
                                        <th className="pb-2 font-medium text-right">Bot Ratio</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border/30">
                                    {safeTargetedPages.length === 0 ? (
                                        <tr>
                                            <td colSpan={4} className="py-6 text-center text-muted-foreground">
                                                No endpoint traffic logged yet.
                                            </td>
                                        </tr>
                                    ) : (
                                        safeTargetedPages.map((page, i) => {
                                            const ratio = page.total_hits > 0 ? Math.round((page.bot_hits / page.total_hits) * 100) : 0;
                                            return (
                                                <tr key={i} className="hover:bg-muted/30 transition">
                                                    <td className="py-2.5 font-mono text-foreground font-medium truncate max-w-[200px]">
                                                        {page.request_uri || '/'}
                                                    </td>
                                                    <td className="py-2.5 text-right font-medium">{page.total_hits}</td>
                                                    <td className="py-2.5 text-right text-amber-600 dark:text-amber-400 font-semibold">
                                                        {page.bot_hits}
                                                    </td>
                                                    <td className="py-2.5 text-right">
                                                        <Badge variant="outline" className={ratio > 50 ? 'border-rose-500/30 text-rose-600' : ''}>
                                                            {ratio}%
                                                        </Badge>
                                                    </td>
                                                </tr>
                                            );
                                        })
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Recent Security Incidents */}
            <Card className="border border-border/60 shadow-sm">
                <CardContent className="p-6">
                    <div className="flex items-center justify-between mb-4">
                        <div>
                            <h3 className="font-semibold text-base flex items-center gap-2">
                                <AlertTriangle className="h-4 w-4 text-rose-500" />
                                Recent Security Incidents
                            </h3>
                            <p className="text-xs text-muted-foreground">Live feed of challenged, blocked, and flagged requests.</p>
                        </div>
                        <Link href="/admin/m/modrixcommerce/bot-protection/queue">
                            <Button variant="outline" size="sm" className="gap-1 text-xs">
                                Open Detection Queue ({safeStats.unresolved_incidents})
                            </Button>
                        </Link>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-xs">
                            <thead>
                                <tr className="border-b border-border/60 text-muted-foreground text-left">
                                    <th className="pb-2 font-medium">Time</th>
                                    <th className="pb-2 font-medium">IP Address</th>
                                    <th className="pb-2 font-medium">Threat Vector</th>
                                    <th className="pb-2 font-medium">Risk Score</th>
                                    <th className="pb-2 font-medium">Mitigation</th>
                                    <th className="pb-2 font-medium">Primary Trigger</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border/30">
                                {safeIncidents.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="py-8 text-center text-muted-foreground">
                                            No active threat incidents detected. Your store is secure.
                                        </td>
                                    </tr>
                                ) : (
                                    safeIncidents.map((incident) => (
                                        <tr key={incident.id} className="hover:bg-muted/30 transition">
                                            <td className="py-3 text-muted-foreground whitespace-nowrap">
                                                {new Date(incident.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })}
                                            </td>
                                            <td className="py-3 font-mono font-medium text-foreground">
                                                {incident.ip_address}
                                            </td>
                                            <td className="py-3 font-medium">
                                                {formatBotType(incident.bot_type)}
                                            </td>
                                            <td className="py-3">
                                                <span className="font-bold mr-2">{incident.risk_score}/100</span>
                                                {threatBadge(incident.threat_level)}
                                            </td>
                                            <td className="py-3">
                                                {actionBadge(incident.action_taken)}
                                            </td>
                                            <td className="py-3 text-muted-foreground max-w-xs truncate">
                                                {incident.reasons?.[0]?.indicator || 'Automated behavioral anomaly'}
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
