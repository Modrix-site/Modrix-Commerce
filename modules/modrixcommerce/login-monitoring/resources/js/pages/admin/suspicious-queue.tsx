import { Head, router } from '@inertiajs/react';
import {
    AlertTriangle,
    Ban,
    Check,
    CheckCircle2,
    Globe,
    LogOut,
    RefreshCw,
    Search,
    ShieldAlert,
    ShieldCheck,
    UserCheck,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type QueueItem = {
    id: number;
    uuid: string;
    user_id: number | null;
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
    country_code: string | null;
    city: string | null;
    suspicious_reasons: Array<{ indicator: string; points: number }>;
    created_at: string;
    timestamp: string;
};

type PaginatedQueue = {
    data: QueueItem[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Props = {
    queue: PaginatedQueue;
    filters: {
        search: string;
    };
};

export default function SuspiciousQueue({ queue, filters }: Props) {
    const [search, setSearch] = useState(filters?.search || '');
    const safeQueue = Array.isArray(queue?.data) ? queue.data : [];

    const handleSearch = () => {
        router.get('/admin/m/modrixcommerce/login-monitoring/queue', { search }, { preserveState: true, replace: true });
    };

    const handleDismiss = (id: number) => {
        router.post(`/admin/m/modrixcommerce/login-monitoring/queue/${id}/dismiss`);
    };

    const handleForceLogout = (id: number, email: string) => {
        if (confirm(`Force-logout all active sessions for ${email}?`)) {
            router.post(`/admin/m/modrixcommerce/login-monitoring/queue/${id}/force-logout`);
        }
    };

    const handleWhitelistIp = (ip: string) => {
        if (confirm(`Whitelist IP ${ip} as trusted?`)) {
            router.post('/admin/m/modrixcommerce/login-monitoring/queue/whitelist-ip', {
                ip_address: ip,
                label: 'Trusted from Suspicious Queue',
            });
        }
    };

    const threatBadge = (level: string) => {
        switch (level) {
            case 'critical':
                return <Badge variant="destructive" className="bg-red-600 text-white font-semibold">Critical</Badge>;
            case 'high':
                return <Badge variant="destructive" className="bg-amber-600 text-white font-semibold">High Risk</Badge>;
            case 'medium':
                return <Badge variant="secondary" className="bg-yellow-500/20 text-yellow-600 dark:text-yellow-400 border-yellow-500/30">Elevated</Badge>;
            default:
                return <Badge variant="outline" className="text-emerald-600">Moderate</Badge>;
        }
    };

    return (
        <AdminPage>
            <Head title="Suspicious Login Queue - Login Monitoring" />

            <PageHeader
                title="Suspicious Login Queue"
                description="High-risk authentication events, impossible travel anomalies, and credential stuffing alerts requiring merchant review."
                actions={
                    <Button variant="outline" size="sm" onClick={() => router.reload()} className="gap-2">
                        <RefreshCw className="h-4 w-4" /> Refresh Queue
                    </Button>
                }
            />

            {/* Search Filter */}
            <Card className="mb-6 border border-border/60 shadow-sm">
                <CardContent className="p-4">
                    <div className="flex gap-2 max-w-md">
                        <div className="relative flex-1">
                            <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                            <Input
                                placeholder="Search by email or IP..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
                                className="pl-9 h-9 text-xs"
                            />
                        </div>
                        <Button size="sm" onClick={handleSearch} className="text-xs bg-emerald-600 hover:bg-emerald-700 text-white">
                            Search
                        </Button>
                    </div>
                </CardContent>
            </Card>

            {/* Queue Cards / List */}
            <div className="space-y-4">
                {safeQueue.length === 0 ? (
                    <Card className="border border-border/60 shadow-sm">
                        <CardContent className="p-12 text-center">
                            <ShieldCheck className="mx-auto h-12 w-12 text-emerald-500 opacity-80 mb-3" />
                            <h3 className="text-base font-semibold text-foreground">Suspicious Queue is Clean</h3>
                            <p className="text-xs text-muted-foreground mt-1 max-w-sm mx-auto">
                                No suspicious authentication attempts are currently flagged for review. All active accounts are operating within safe security parameters.
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    safeQueue.map((item) => (
                        <Card key={item.id} className="border border-border/60 shadow-sm hover:shadow transition">
                            <CardContent className="p-5">
                                <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                                    <div className="space-y-2">
                                        <div className="flex items-center gap-2">
                                            <span className="font-bold text-sm text-foreground">{item.email}</span>
                                            {item.user_name && (
                                                <span className="text-xs text-muted-foreground">({item.user_name})</span>
                                            )}
                                            {threatBadge(item.threat_level)}
                                            <span className="text-xs font-mono font-bold text-rose-600 dark:text-rose-400">
                                                Risk: {item.risk_score}/100
                                            </span>
                                        </div>

                                        <div className="flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                                            <span className="font-mono bg-muted px-1.5 py-0.5 rounded">{item.ip_address}</span>
                                            <span>&bull;</span>
                                            <span>{item.city ? `${item.city}, ` : ''}{item.country_code || 'Unknown Origin'}</span>
                                            <span>&bull;</span>
                                            <span>{item.browser} on {item.operating_system}</span>
                                            <span>&bull;</span>
                                            <span>{item.created_at}</span>
                                        </div>

                                        {/* Reasons */}
                                        {item.suspicious_reasons && item.suspicious_reasons.length > 0 && (
                                            <div className="mt-2 space-y-1">
                                                {item.suspicious_reasons.map((r, i) => (
                                                    <div key={i} className="flex items-center gap-2 text-xs text-amber-700 dark:text-amber-300">
                                                        <AlertTriangle className="h-3.5 w-3.5 shrink-0 text-amber-500" />
                                                        <span>{r.indicator}</span>
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                    </div>

                                    {/* Action Buttons */}
                                    <div className="flex flex-wrap items-center gap-2 shrink-0">
                                        {item.user_id && (
                                            <Button
                                                size="sm"
                                                variant="destructive"
                                                onClick={() => handleForceLogout(item.id, item.email)}
                                                className="h-8 gap-1 text-xs"
                                            >
                                                <LogOut className="h-3.5 w-3.5" /> Force Logout
                                            </Button>
                                        )}

                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => handleWhitelistIp(item.ip_address)}
                                            className="h-8 gap-1 text-xs"
                                        >
                                            <UserCheck className="h-3.5 w-3.5" /> Whitelist IP
                                        </Button>

                                        <Button
                                            size="sm"
                                            variant="secondary"
                                            onClick={() => handleDismiss(item.id)}
                                            className="h-8 gap-1 text-xs"
                                        >
                                            <Check className="h-3.5 w-3.5" /> Dismiss
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    ))
                )}
            </div>

            {/* Pagination */}
            {queue && queue.last_page > 1 && (
                <div className="flex items-center justify-between p-4 border-t border-border/50 text-xs mt-6">
                    <span className="text-muted-foreground">
                        Page {queue.current_page} of {queue.last_page} ({queue.total} total items)
                    </span>
                    <div className="flex items-center gap-2">
                        {queue.prev_page_url && (
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => router.get(queue.prev_page_url!)}
                                className="h-7 text-xs"
                            >
                                Previous
                            </Button>
                        )}
                        {queue.next_page_url && (
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => router.get(queue.next_page_url!)}
                                className="h-7 text-xs"
                            >
                                Next
                            </Button>
                        )}
                    </div>
                </div>
            )}
        </AdminPage>
    );
}
