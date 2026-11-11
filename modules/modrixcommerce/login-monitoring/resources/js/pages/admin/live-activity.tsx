import { Head, router } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    Ban,
    CheckCircle2,
    Clock,
    Filter,
    Globe,
    Info,
    Laptop,
    RefreshCw,
    Search,
    ShieldAlert,
    Smartphone,
    User,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type LoginItem = {
    id: number;
    uuid: string;
    email: string;
    user_name: string | null;
    ip_address: string;
    status: string;
    failure_reason: string | null;
    risk_score: number;
    threat_level: string;
    device_type: string;
    browser: string;
    operating_system: string;
    user_agent: string;
    country_code: string | null;
    city: string | null;
    is_suspicious: boolean;
    suspicious_reasons: Array<{ indicator: string; points: number }>;
    created_at: string;
    timestamp: string;
};

type PaginatedEvents = {
    data: LoginItem[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Props = {
    events: PaginatedEvents;
    filters: {
        search: string;
        status: string;
        threat_level: string;
    };
};

export default function LiveActivity({ events, filters }: Props) {
    const [search, setSearch] = useState(filters?.search || '');
    const [status, setStatus] = useState(filters?.status || '');
    const [threatLevel, setThreatLevel] = useState(filters?.threat_level || '');
    const [selectedEvent, setSelectedEvent] = useState<LoginItem | null>(null);

    const safeEvents = Array.isArray(events?.data) ? events.data : [];

    const handleFilter = () => {
        router.get(
            '/admin/m/modrixcommerce/login-monitoring/activity',
            { search, status, threat_level: threatLevel },
            { preserveState: true, replace: true },
        );
    };

    const handleReset = () => {
        setSearch('');
        setStatus('');
        setThreatLevel('');
        router.get('/admin/m/modrixcommerce/login-monitoring/activity');
    };

    const handleBlockIp = (ip: string) => {
        if (confirm(`Are you sure you want to block IP ${ip}?`)) {
            router.post(
                '/admin/m/modrixcommerce/login-monitoring/activity/block-ip',
                {
                    ip_address: ip,
                    reason: 'Manually blocked from Live Activity stream',
                },
            );
        }
    };

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

    return (
        <AdminPage>
            <Head title="Live Login Activity - Login Monitoring" />

            <PageHeader
                title="Live Login Activity"
                description="Real-time log of every authentication attempt across web and mobile surfaces."
                actions={
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => router.reload()}
                        className="gap-2"
                    >
                        <RefreshCw className="h-4 w-4" /> Refresh Stream
                    </Button>
                }
            />

            {/* Filter Toolbar */}
            <Card className="mb-6 border border-border/60 shadow-sm">
                <CardContent className="p-4">
                    <div className="flex flex-col items-center justify-between gap-3 md:flex-row">
                        <div className="flex w-full flex-1 items-center gap-2 md:w-auto">
                            <div className="relative max-w-sm flex-1">
                                <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    placeholder="Filter by email, IP, city..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    onKeyDown={(e) =>
                                        e.key === 'Enter' && handleFilter()
                                    }
                                    className="h-9 pl-9 text-xs"
                                />
                            </div>

                            <select
                                value={status}
                                onChange={(e) => setStatus(e.target.value)}
                                className="h-9 rounded-md border border-input bg-background px-3 text-xs"
                            >
                                <option value="">All Statuses</option>
                                <option value="success">Successful</option>
                                <option value="failed">Failed</option>
                            </select>

                            <select
                                value={threatLevel}
                                onChange={(e) => setThreatLevel(e.target.value)}
                                className="h-9 rounded-md border border-input bg-background px-3 text-xs"
                            >
                                <option value="">All Threat Levels</option>
                                <option value="low">Low Risk</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>

                        <div className="flex w-full items-center justify-end gap-2 md:w-auto">
                            <Button
                                size="sm"
                                onClick={handleFilter}
                                className="gap-1.5 bg-emerald-600 text-xs text-white hover:bg-emerald-700"
                            >
                                <Filter className="h-3.5 w-3.5" /> Filter
                            </Button>
                            {(search || status || threatLevel) && (
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    onClick={handleReset}
                                    className="text-xs"
                                >
                                    Reset
                                </Button>
                            )}
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Events Table */}
            <Card className="border border-border/60 shadow-sm">
                <CardContent className="p-0">
                    <div className="overflow-x-auto">
                        <table className="w-full text-xs">
                            <thead>
                                <tr className="border-b border-border/60 bg-muted/40 text-left text-muted-foreground">
                                    <th className="px-4 py-3 font-medium">
                                        Status
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Account / User
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        IP Address
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Location
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Device & Browser
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Risk Score
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Time
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border/30">
                                {safeEvents.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={8}
                                            className="py-12 text-center text-muted-foreground"
                                        >
                                            No login events matching the
                                            selected criteria.
                                        </td>
                                    </tr>
                                ) : (
                                    safeEvents.map((event) => (
                                        <tr
                                            key={event.id}
                                            className="transition hover:bg-muted/30"
                                        >
                                            <td className="px-4 py-3.5">
                                                {event.status === 'success' ? (
                                                    <Badge className="border-emerald-500/20 bg-emerald-500/15 font-medium text-emerald-600 dark:text-emerald-400">
                                                        Success
                                                    </Badge>
                                                ) : (
                                                    <Badge className="border-rose-500/20 bg-rose-500/15 font-medium text-rose-600 dark:text-rose-400">
                                                        Failed
                                                    </Badge>
                                                )}
                                                {event.is_suspicious && (
                                                    <span className="ml-1 inline-flex items-center text-[10px] font-bold text-amber-600">
                                                        &bull; Suspicious
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <div className="font-semibold text-foreground">
                                                    {event.email}
                                                </div>
                                                {event.user_name && (
                                                    <div className="text-[11px] text-muted-foreground">
                                                        {event.user_name}
                                                    </div>
                                                )}
                                                {event.failure_reason && (
                                                    <div className="text-[10px] font-medium text-rose-500">
                                                        {event.failure_reason}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-4 py-3.5 font-mono font-medium text-muted-foreground">
                                                {event.ip_address}
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <div className="flex items-center gap-1 text-foreground">
                                                    <Globe className="h-3 w-3 text-muted-foreground" />
                                                    <span>
                                                        {event.city
                                                            ? `${event.city}, `
                                                            : ''}
                                                        {event.country_code ||
                                                            'Unknown'}
                                                    </span>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <div className="font-medium text-foreground">
                                                    {event.browser}
                                                </div>
                                                <div className="text-[10px] text-muted-foreground">
                                                    {event.operating_system}{' '}
                                                    &bull; {event.device_type}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <div className="flex items-center gap-1.5">
                                                    <span className="font-bold">
                                                        {event.risk_score}/100
                                                    </span>
                                                    {threatBadge(
                                                        event.threat_level,
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3.5 whitespace-nowrap text-muted-foreground">
                                                <div>{event.created_at}</div>
                                                <div className="text-[10px] opacity-75">
                                                    {event.timestamp}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3.5 text-right">
                                                <div className="flex items-center justify-end gap-1.5">
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() =>
                                                            setSelectedEvent(
                                                                event,
                                                            )
                                                        }
                                                        className="h-7 px-2 text-xs"
                                                    >
                                                        Details
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() =>
                                                            handleBlockIp(
                                                                event.ip_address,
                                                            )
                                                        }
                                                        className="h-7 px-2 text-xs text-rose-600 hover:bg-rose-500/10 hover:text-rose-700"
                                                    >
                                                        <Ban className="mr-1 h-3 w-3" />{' '}
                                                        Block
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
                    {events && events.last_page > 1 && (
                        <div className="flex items-center justify-between border-t border-border/50 p-4 text-xs">
                            <span className="text-muted-foreground">
                                Page {events.current_page} of {events.last_page}{' '}
                                ({events.total} total events)
                            </span>
                            <div className="flex items-center gap-2">
                                {events.prev_page_url && (
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            router.get(events.prev_page_url!)
                                        }
                                        className="h-7 text-xs"
                                    >
                                        Previous
                                    </Button>
                                )}
                                {events.next_page_url && (
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            router.get(events.next_page_url!)
                                        }
                                        className="h-7 text-xs"
                                    >
                                        Next
                                    </Button>
                                )}
                            </div>
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Event Detail Modal */}
            {selectedEvent && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
                    <div className="relative w-full max-w-lg rounded-xl border border-border bg-card p-6 shadow-2xl">
                        <div className="mb-4 flex items-center justify-between border-b border-border/60 pb-3">
                            <div>
                                <h3 className="flex items-center gap-2 text-base font-semibold">
                                    <ShieldAlert className="h-4 w-4 text-emerald-600" />
                                    Login Event Details
                                </h3>
                                <p className="mt-0.5 font-mono text-xs text-muted-foreground">
                                    {selectedEvent.uuid}
                                </p>
                            </div>
                            <Button
                                size="sm"
                                variant="ghost"
                                onClick={() => setSelectedEvent(null)}
                            >
                                Close
                            </Button>
                        </div>

                        <div className="space-y-3 text-xs">
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <span className="text-muted-foreground">
                                        Target Email:
                                    </span>
                                    <p className="font-semibold text-foreground">
                                        {selectedEvent.email}
                                    </p>
                                </div>
                                <div>
                                    <span className="text-muted-foreground">
                                        Result:
                                    </span>
                                    <p className="font-semibold">
                                        {selectedEvent.status === 'success'
                                            ? 'Successful Login'
                                            : 'Failed Login'}
                                    </p>
                                </div>
                                <div>
                                    <span className="text-muted-foreground">
                                        IP Address:
                                    </span>
                                    <p className="font-mono font-medium">
                                        {selectedEvent.ip_address}
                                    </p>
                                </div>
                                <div>
                                    <span className="text-muted-foreground">
                                        Location:
                                    </span>
                                    <p>
                                        {selectedEvent.city
                                            ? `${selectedEvent.city}, `
                                            : ''}
                                        {selectedEvent.country_code ||
                                            'Unknown'}
                                    </p>
                                </div>
                                <div>
                                    <span className="text-muted-foreground">
                                        Device & OS:
                                    </span>
                                    <p>
                                        {selectedEvent.device_type} &bull;{' '}
                                        {selectedEvent.operating_system}
                                    </p>
                                </div>
                                <div>
                                    <span className="text-muted-foreground">
                                        Browser:
                                    </span>
                                    <p>{selectedEvent.browser}</p>
                                </div>
                            </div>

                            <div className="border-t border-border/40 pt-3">
                                <span className="text-muted-foreground">
                                    User Agent:
                                </span>
                                <p className="mt-1 rounded bg-muted p-2 font-mono text-[11px] break-all">
                                    {selectedEvent.user_agent ||
                                        'Unknown User-Agent'}
                                </p>
                            </div>

                            {selectedEvent.suspicious_reasons &&
                                selectedEvent.suspicious_reasons.length > 0 && (
                                    <div className="border-t border-border/40 pt-3">
                                        <span className="font-semibold text-rose-600 dark:text-rose-400">
                                            Risk Factors & Anomalies Detected:
                                        </span>
                                        <ul className="mt-2 space-y-1.5">
                                            {selectedEvent.suspicious_reasons.map(
                                                (r, i) => (
                                                    <li
                                                        key={i}
                                                        className="flex items-center justify-between rounded bg-rose-500/10 p-2 text-rose-900 dark:text-rose-200"
                                                    >
                                                        <span>
                                                            {r.indicator}
                                                        </span>
                                                        <span className="font-mono font-bold">
                                                            +{r.points} pts
                                                        </span>
                                                    </li>
                                                ),
                                            )}
                                        </ul>
                                    </div>
                                )}
                        </div>

                        <div className="mt-6 flex items-center justify-end gap-2 border-t border-border/60 pt-4">
                            <Button
                                size="sm"
                                variant="destructive"
                                onClick={() => {
                                    handleBlockIp(selectedEvent.ip_address);
                                    setSelectedEvent(null);
                                }}
                            >
                                Block IP Address
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </AdminPage>
    );
}
