import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
    AlertOctagon,
    AlertTriangle,
    Ban,
    Check,
    CheckCircle2,
    Eye,
    Filter,
    Info,
    Shield,
    ShieldAlert,
    ShieldCheck,
    X,
} from 'lucide-react';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type EventItem = {
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
    request_headers: Record<string, any> | null;
    is_resolved: boolean;
    created_at: string;
};

type Props = {
    events: {
        data: EventItem[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        current_page: number;
        total: number;
    };
    filters: {
        type: string;
        min_score: number;
    };
    pendingCount: number;
};

export default function DetectionQueue({ events, filters, pendingCount = 0 }: Props) {
    const [selectedEvent, setSelectedEvent] = useState<EventItem | null>(null);
    const eventList = events?.data || [];

    const handleFilterChange = (key: string, value: any) => {
        const params = new URLSearchParams(window.location.search);
        if (value === 'all' || value === 0) {
            params.delete(key);
        } else {
            params.set(key, value);
        }
        router.get(`/admin/m/modrixcommerce/bot-protection/queue?${params.toString()}`);
    };

    return (
        <AdminPage>
            <Head title="Bot Protection - Detection Queue" />

            <PageHeader
                title="Detection Queue"
                description="Review flagged visitor sessions, inspect heuristic triggers, and triage threats."
                actions={
                    <Badge variant="outline" className="px-3 py-1.5 text-xs font-semibold bg-amber-500/10 text-amber-600 border-amber-500/30">
                        {pendingCount} Pending Triage
                    </Badge>
                }
            />

            {/* Filter Bar */}
            <Card className="border border-border/60 shadow-sm mb-6">
                <CardContent className="p-4 flex flex-wrap items-center justify-between gap-4">
                    <div className="flex flex-wrap items-center gap-3">
                        <span className="text-xs font-medium text-muted-foreground flex items-center gap-1.5">
                            <Filter className="h-3.5 w-3.5" /> Threat Type:
                        </span>
                        <div className="flex flex-wrap items-center gap-1">
                            {['all', 'scraper', 'credential_stuffing', 'checkout_bot', 'bad_user_agent', 'rate_limit_exceeded'].map((t) => (
                                <Button
                                    key={t}
                                    variant={filters.type === t ? 'default' : 'outline'}
                                    size="sm"
                                    className="h-8 text-xs capitalize"
                                    onClick={() => handleFilterChange('type', t)}
                                >
                                    {t.replace(/_/g, ' ')}
                                </Button>
                            ))}
                        </div>
                    </div>

                    <span className="text-xs text-muted-foreground">
                        Showing <strong>{eventList.length}</strong> of <strong>{events?.total || 0}</strong> flagged events
                    </span>
                </CardContent>
            </Card>

            {/* Detection Queue Table */}
            <Card className="border border-border/60 shadow-sm">
                <CardContent className="p-6">
                    <div className="overflow-x-auto">
                        <table className="w-full text-xs">
                            <thead>
                                <tr className="border-b border-border/60 text-muted-foreground text-left">
                                    <th className="pb-3 font-medium">Logged</th>
                                    <th className="pb-3 font-medium">Origin IP</th>
                                    <th className="pb-3 font-medium">Threat Vector</th>
                                    <th className="pb-3 font-medium">Risk Score</th>
                                    <th className="pb-3 font-medium">Mitigation</th>
                                    <th className="pb-3 font-medium">Triggered Rule</th>
                                    <th className="pb-3 font-medium text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border/30">
                                {eventList.length === 0 ? (
                                    <tr>
                                        <td colSpan={7} className="py-12 text-center text-muted-foreground">
                                            No events in queue! All traffic is clean or resolved.
                                        </td>
                                    </tr>
                                ) : (
                                    eventList.map((evt) => (
                                        <tr key={evt.id} className="hover:bg-muted/30 transition">
                                            <td className="py-3 text-muted-foreground whitespace-nowrap">
                                                {new Date(evt.created_at).toLocaleString()}
                                            </td>
                                            <td className="py-3 font-mono font-medium text-foreground">
                                                {evt.ip_address}
                                            </td>
                                            <td className="py-3">
                                                <Badge variant="outline" className="text-[11px] font-normal">
                                                    {evt.bot_type.replace(/_/g, ' ')}
                                                </Badge>
                                            </td>
                                            <td className="py-3">
                                                <span className={`font-bold mr-1.5 ${evt.risk_score >= 80 ? 'text-rose-600' : evt.risk_score >= 50 ? 'text-amber-600' : 'text-foreground'}`}>
                                                    {evt.risk_score}
                                                </span>
                                                / 100
                                            </td>
                                            <td className="py-3">
                                                <Badge
                                                    className={
                                                        evt.action_taken === 'block'
                                                            ? 'bg-rose-500/15 text-rose-600'
                                                            : evt.action_taken === 'challenge'
                                                            ? 'bg-amber-500/15 text-amber-600'
                                                            : 'bg-muted text-muted-foreground'
                                                    }
                                                >
                                                    {evt.action_taken}
                                                </Badge>
                                            </td>
                                            <td className="py-3 text-muted-foreground max-w-xs truncate">
                                                {evt.reasons?.[0]?.indicator || 'Behavioral anomaly'}
                                            </td>
                                            <td className="py-3 text-right">
                                                <div className="flex items-center justify-end gap-1.5">
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        className="h-7 px-2 text-[11px]"
                                                        onClick={() => setSelectedEvent(evt)}
                                                    >
                                                        <Eye className="h-3 w-3 mr-1" /> Inspect
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        className="h-7 px-2 text-[11px] text-emerald-600 hover:bg-emerald-500/10"
                                                        onClick={() => router.post(`/admin/m/modrixcommerce/bot-protection/queue/${evt.id}/resolve`)}
                                                    >
                                                        <Check className="h-3 w-3 mr-1" /> Dismiss
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        className="h-7 px-2 text-[11px] text-rose-600 hover:bg-rose-500/10"
                                                        onClick={() => router.post(`/admin/m/modrixcommerce/bot-protection/queue/${evt.id}/block-ip`)}
                                                    >
                                                        <Ban className="h-3 w-3 mr-1" /> Blacklist
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
                                    className={`px-3 py-1.5 text-xs rounded border transition ${
                                        link.active
                                            ? 'bg-primary text-primary-foreground border-primary'
                                            : 'border-border/60 hover:bg-muted text-muted-foreground'
                                    } ${!link.url ? 'opacity-40 pointer-events-none' : ''}`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Heuristics Inspection Modal */}
            {selectedEvent && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
                    <div className="bg-card border border-border rounded-xl max-w-2xl w-full p-6 shadow-2xl relative max-h-[90vh] overflow-y-auto">
                        <button
                            onClick={() => setSelectedEvent(null)}
                            className="absolute top-4 right-4 text-muted-foreground hover:text-foreground"
                        >
                            <X className="h-5 w-5" />
                        </button>

                        <div className="flex items-center gap-3 mb-4">
                            <div className="p-2.5 rounded-lg bg-rose-500/15 text-rose-600">
                                <ShieldAlert className="h-5 w-5" />
                            </div>
                            <div>
                                <h3 className="text-base font-bold text-foreground">Threat Heuristics Inspection</h3>
                                <p className="text-xs text-muted-foreground font-mono">Event UUID: {selectedEvent.event_uuid}</p>
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-3 text-xs mb-4 p-3 bg-muted/40 rounded-lg">
                            <div>
                                <span className="text-muted-foreground">Origin IP:</span>{' '}
                                <strong className="font-mono text-foreground">{selectedEvent.ip_address}</strong>
                            </div>
                            <div>
                                <span className="text-muted-foreground">Risk Score:</span>{' '}
                                <strong className="text-rose-600 font-bold">{selectedEvent.risk_score} / 100</strong>
                            </div>
                            <div>
                                <span className="text-muted-foreground">Request Method:</span>{' '}
                                <strong>{selectedEvent.request_method}</strong>
                            </div>
                            <div>
                                <span className="text-muted-foreground">Mitigation:</span>{' '}
                                <strong className="capitalize">{selectedEvent.action_taken}</strong>
                            </div>
                            <div className="col-span-2">
                                <span className="text-muted-foreground">Target URL:</span>{' '}
                                <span className="font-mono text-foreground break-all">{selectedEvent.request_path}</span>
                            </div>
                            <div className="col-span-2">
                                <span className="text-muted-foreground">User Agent:</span>{' '}
                                <span className="font-mono text-foreground break-all">{selectedEvent.user_agent || 'None provided'}</span>
                            </div>
                        </div>

                        <div className="mb-4">
                            <h4 className="text-xs font-bold uppercase tracking-wider text-muted-foreground mb-2">
                                Heuristic Indicators Triggered
                            </h4>
                            <div className="space-y-2">
                                {(selectedEvent.reasons || []).map((r, i) => (
                                    <div key={i} className="flex items-center justify-between p-2 rounded bg-muted/30 border border-border/40 text-xs">
                                        <span className="font-medium text-foreground">{r.indicator}</span>
                                        <Badge variant="destructive" className="bg-rose-500/15 text-rose-600 border-rose-500/20 font-bold">
                                            +{r.points} pts
                                        </Badge>
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div className="flex items-center justify-end gap-2 pt-4 border-t border-border">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                    router.post(`/admin/m/modrixcommerce/bot-protection/queue/${selectedEvent.id}/whitelist-ip`);
                                    setSelectedEvent(null);
                                }}
                            >
                                <ShieldCheck className="h-4 w-4 mr-1 text-emerald-600" /> Whitelist IP
                            </Button>
                            <Button
                                variant="destructive"
                                size="sm"
                                onClick={() => {
                                    router.post(`/admin/m/modrixcommerce/bot-protection/queue/${selectedEvent.id}/block-ip`);
                                    setSelectedEvent(null);
                                }}
                            >
                                <Ban className="h-4 w-4 mr-1" /> Blacklist IP
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </AdminPage>
    );
}
