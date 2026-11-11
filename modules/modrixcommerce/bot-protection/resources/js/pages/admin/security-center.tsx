import { Head, Link, router } from '@inertiajs/react';
import {
    AlertOctagon,
    AlertTriangle,
    Ban,
    Check,
    CheckCircle2,
    Eye,
    Filter,
    Flame,
    Lock,
    RefreshCw,
    Shield,
    ShieldAlert,
    ShieldCheck,
    Trash2,
    Zap,
} from 'lucide-react';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type Props = {
    stats: {
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
    events: {
        data: Array<{
            id: number;
            event_uuid: string;
            ip_address: string;
            user_agent: string | null;
            request_path: string;
            request_method: string;
            bot_type: string;
            risk_score: number;
            threat_level: string;
            action_taken: string;
            reasons: Array<{ indicator: string; points: number }>;
            is_resolved: boolean;
            created_at: string;
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
        current_page: number;
        total: number;
    };
    filters: {
        status: string;
        threat: string;
    };
    isLockdown: boolean;
};

export default function SecurityCenter({
    stats,
    events,
    filters,
    isLockdown,
}: Props) {
    const eventList = events?.data || [];

    const handleFilterChange = (key: string, value: string) => {
        const params = new URLSearchParams(window.location.search);
        if (value === 'all') {
            params.delete(key);
        } else {
            params.set(key, value);
        }
        router.get(
            `/admin/m/modrixcommerce/bot-protection/security?${params.toString()}`,
        );
    };

    return (
        <AdminPage>
            <Head title="Bot Protection - Security Center" />

            <PageHeader
                title="Security Center"
                description="Real-time perimeter defense controls, threat triage, and automated incident mitigations."
                actions={
                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.post(
                                    '/admin/m/modrixcommerce/bot-protection/security/flush-cache',
                                )
                            }
                            className="gap-2 shadow-sm"
                        >
                            <RefreshCw className="h-4 w-4" />
                            Flush Threat Cache
                        </Button>
                        <Button
                            variant={isLockdown ? 'destructive' : 'default'}
                            onClick={() =>
                                router.post(
                                    '/admin/m/modrixcommerce/bot-protection/security/lockdown',
                                )
                            }
                            className="gap-2 shadow-sm"
                        >
                            <AlertOctagon className="h-4 w-4" />
                            {isLockdown
                                ? 'Disable Emergency Lockdown'
                                : 'Activate Lockdown Mode'}
                        </Button>
                    </div>
                }
            />

            {/* Defense Posture & Mitigations Grid */}
            <div className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-4">
                <Card className="border border-border/60 shadow-sm">
                    <CardContent className="flex items-center gap-4 p-5">
                        <div
                            className={`rounded-xl p-3 ${isLockdown ? 'bg-red-500/15 text-red-600' : 'bg-emerald-500/15 text-emerald-600'}`}
                        >
                            {isLockdown ? (
                                <Flame className="h-6 w-6 animate-bounce" />
                            ) : (
                                <ShieldCheck className="h-6 w-6" />
                            )}
                        </div>
                        <div>
                            <span className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Perimeter Status
                            </span>
                            <h4 className="text-lg font-bold text-foreground">
                                {isLockdown
                                    ? 'Lockdown (High Alert)'
                                    : 'Active & Guarded'}
                            </h4>
                        </div>
                    </CardContent>
                </Card>

                <Card className="border border-border/60 shadow-sm">
                    <CardContent className="flex items-center gap-4 p-5">
                        <div className="rounded-xl bg-blue-500/15 p-3 text-blue-600">
                            <Zap className="h-6 w-6" />
                        </div>
                        <div>
                            <span className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Mitigations Active
                            </span>
                            <h4 className="text-lg font-bold text-foreground">
                                Rate Limit & Traps
                            </h4>
                        </div>
                    </CardContent>
                </Card>

                <Card className="border border-border/60 shadow-sm">
                    <CardContent className="flex items-center gap-4 p-5">
                        <div className="rounded-xl bg-purple-500/15 p-3 text-purple-600">
                            <Lock className="h-6 w-6" />
                        </div>
                        <div>
                            <span className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Honeypot Shield
                            </span>
                            <h4 className="text-lg font-bold text-foreground">
                                Armed & Hidden
                            </h4>
                        </div>
                    </CardContent>
                </Card>

                <Card className="border border-border/60 shadow-sm">
                    <CardContent className="flex items-center gap-4 p-5">
                        <div className="rounded-xl bg-amber-500/15 p-3 text-amber-600">
                            <AlertTriangle className="h-6 w-6" />
                        </div>
                        <div>
                            <span className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Unresolved Threats
                            </span>
                            <h4 className="text-lg font-bold text-foreground">
                                {stats.unresolved_incidents} Incidents
                            </h4>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Filter Bar */}
            <Card className="mb-6 border border-border/60 shadow-sm">
                <CardContent className="flex flex-wrap items-center justify-between gap-4 p-4">
                    <div className="flex flex-wrap items-center gap-3">
                        <span className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                            <Filter className="h-3.5 w-3.5" /> Filters:
                        </span>
                        <div className="flex items-center gap-1">
                            {['all', 'unresolved', 'resolved'].map((s) => (
                                <Button
                                    key={s}
                                    variant={
                                        filters.status === s
                                            ? 'default'
                                            : 'outline'
                                    }
                                    size="sm"
                                    className="h-8 text-xs capitalize"
                                    onClick={() =>
                                        handleFilterChange('status', s)
                                    }
                                >
                                    {s}
                                </Button>
                            ))}
                        </div>
                        <div className="mx-1 h-4 w-px bg-border/60"></div>
                        <div className="flex items-center gap-1">
                            {['all', 'critical', 'high', 'medium', 'low'].map(
                                (t) => (
                                    <Button
                                        key={t}
                                        variant={
                                            filters.threat === t
                                                ? 'secondary'
                                                : 'ghost'
                                        }
                                        size="sm"
                                        className="h-8 text-xs capitalize"
                                        onClick={() =>
                                            handleFilterChange('threat', t)
                                        }
                                    >
                                        {t} Threat
                                    </Button>
                                ),
                            )}
                        </div>
                    </div>

                    <span className="text-xs text-muted-foreground">
                        Showing <strong>{eventList.length}</strong> of{' '}
                        <strong>{events?.total || 0}</strong> events
                    </span>
                </CardContent>
            </Card>

            {/* Live Incidents Feed Table */}
            <Card className="border border-border/60 shadow-sm">
                <CardContent className="p-6">
                    <div className="overflow-x-auto">
                        <table className="w-full text-xs">
                            <thead>
                                <tr className="border-b border-border/60 text-left text-muted-foreground">
                                    <th className="pb-3 font-medium">
                                        Timestamp
                                    </th>
                                    <th className="pb-3 font-medium">
                                        Origin IP
                                    </th>
                                    <th className="pb-3 font-medium">
                                        Attack Vector
                                    </th>
                                    <th className="pb-3 font-medium">
                                        Risk Score
                                    </th>
                                    <th className="pb-3 font-medium">
                                        Mitigation
                                    </th>
                                    <th className="pb-3 font-medium">
                                        Targeted Path
                                    </th>
                                    <th className="pb-3 text-right font-medium">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border/30">
                                {eventList.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={7}
                                            className="py-12 text-center text-muted-foreground"
                                        >
                                            No threat events matching current
                                            filters.
                                        </td>
                                    </tr>
                                ) : (
                                    eventList.map((evt) => (
                                        <tr
                                            key={evt.id}
                                            className="transition hover:bg-muted/30"
                                        >
                                            <td className="py-3 whitespace-nowrap text-muted-foreground">
                                                {new Date(
                                                    evt.created_at,
                                                ).toLocaleString()}
                                            </td>
                                            <td className="py-3 font-mono font-medium text-foreground">
                                                {evt.ip_address}
                                            </td>
                                            <td className="py-3">
                                                <Badge
                                                    variant="outline"
                                                    className="text-[11px] font-normal"
                                                >
                                                    {evt.bot_type.replace(
                                                        /_/g,
                                                        ' ',
                                                    )}
                                                </Badge>
                                            </td>
                                            <td className="py-3 font-bold">
                                                <span
                                                    className={
                                                        evt.risk_score >= 80
                                                            ? 'text-rose-600'
                                                            : evt.risk_score >=
                                                                50
                                                              ? 'text-amber-600'
                                                              : 'text-foreground'
                                                    }
                                                >
                                                    {evt.risk_score}
                                                </span>{' '}
                                                / 100
                                            </td>
                                            <td className="py-3">
                                                <Badge
                                                    className={
                                                        evt.action_taken ===
                                                        'block'
                                                            ? 'bg-rose-500/15 text-rose-600'
                                                            : evt.action_taken ===
                                                                'challenge'
                                                              ? 'bg-amber-500/15 text-amber-600'
                                                              : 'bg-muted text-muted-foreground'
                                                    }
                                                >
                                                    {evt.action_taken}
                                                </Badge>
                                            </td>
                                            <td className="max-w-xs truncate py-3 font-mono text-muted-foreground">
                                                {evt.request_path}
                                            </td>
                                            <td className="py-3 text-right">
                                                <div className="flex items-center justify-end gap-1.5">
                                                    {!evt.is_resolved && (
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            className="h-7 px-2 text-[11px] text-emerald-600 hover:bg-emerald-500/10"
                                                            onClick={() =>
                                                                router.post(
                                                                    `/admin/m/modrixcommerce/bot-protection/queue/${evt.id}/resolve`,
                                                                )
                                                            }
                                                        >
                                                            <Check className="mr-1 h-3 w-3" />{' '}
                                                            Resolve
                                                        </Button>
                                                    )}
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        className="h-7 px-2 text-[11px] text-rose-600 hover:bg-rose-500/10"
                                                        onClick={() =>
                                                            router.post(
                                                                `/admin/m/modrixcommerce/bot-protection/queue/${evt.id}/block-ip`,
                                                            )
                                                        }
                                                    >
                                                        <Ban className="mr-1 h-3 w-3" />{' '}
                                                        Blacklist IP
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {events?.links && events.links.length > 3 && (
                        <div className="mt-6 flex justify-center gap-1">
                            {events.links.map((link, i) => (
                                <Link
                                    key={i}
                                    href={link.url || '#'}
                                    className={`rounded border px-3 py-1.5 text-xs transition ${
                                        link.active
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'border-border/60 text-muted-foreground hover:bg-muted'
                                    } ${!link.url ? 'pointer-events-none opacity-40' : ''}`}
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>
        </AdminPage>
    );
}
