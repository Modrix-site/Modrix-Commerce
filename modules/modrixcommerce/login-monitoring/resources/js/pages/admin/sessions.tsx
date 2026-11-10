import { Head, router } from '@inertiajs/react';
import {
    Activity,
    AlertOctagon,
    Clock,
    Globe,
    Laptop,
    LogOut,
    RefreshCw,
    Search,
    Shield,
    Smartphone,
    UserX,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type SessionItem = {
    id: number;
    session_id: string;
    user_id: number;
    user_name: string;
    user_email: string;
    ip_address: string;
    device_type: string;
    browser: string;
    operating_system: string;
    country_code: string | null;
    city: string | null;
    is_current: boolean;
    is_revoked: boolean;
    last_activity: string;
    expires_at: string;
};

type PaginatedSessions = {
    data: SessionItem[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Props = {
    sessions: PaginatedSessions;
    activeCount: number;
    filters: {
        search: string;
        status: string;
    };
};

export default function UserSessions({ sessions, activeCount, filters }: Props) {
    const [search, setSearch] = useState(filters?.search || '');
    const [status, setStatus] = useState(filters?.status || 'active');

    const safeSessions = Array.isArray(sessions?.data) ? sessions.data : [];

    const handleFilter = () => {
        router.get(
            '/admin/m/modrixcommerce/login-monitoring/sessions',
            { search, status },
            { preserveState: true, replace: true }
        );
    };

    const handleRevoke = (sessionId: string) => {
        if (confirm('Terminate this user session immediately? The user will be forced to log in again.')) {
            router.post(`/admin/m/modrixcommerce/login-monitoring/sessions/${sessionId}/revoke`);
        }
    };

    const handleRevokeAll = (userId: number, email: string) => {
        if (confirm(`Terminate ALL active sessions for ${email}?`)) {
            router.post(`/admin/m/modrixcommerce/login-monitoring/sessions/revoke-all/${userId}`);
        }
    };

    return (
        <AdminPage>
            <Head title="Active User Sessions - Login Monitoring" />

            <PageHeader
                title="Active User Sessions"
                description="Live session registry across all authenticated administrator and customer accounts."
                actions={
                    <div className="flex items-center gap-2">
                        <Badge variant="outline" className="text-xs px-2.5 py-1 bg-emerald-500/10 text-emerald-600 border-emerald-500/30">
                            {activeCount} Active Sessions
                        </Badge>
                        <Button variant="outline" size="sm" onClick={() => router.reload()} className="gap-2">
                            <RefreshCw className="h-4 w-4" /> Refresh
                        </Button>
                    </div>
                }
            />

            {/* Filter Toolbar */}
            <Card className="mb-6 border border-border/60 shadow-sm">
                <CardContent className="p-4">
                    <div className="flex flex-col sm:flex-row gap-3 items-center justify-between">
                        <div className="flex flex-1 items-center gap-2 w-full sm:w-auto">
                            <div className="relative flex-1 max-w-sm">
                                <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    placeholder="Search by user, email, IP, browser..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    onKeyDown={(e) => e.key === 'Enter' && handleFilter()}
                                    className="pl-9 h-9 text-xs"
                                />
                            </div>

                            <select
                                value={status}
                                onChange={(e) => setStatus(e.target.value)}
                                className="h-9 rounded-md border border-input bg-background px-3 text-xs"
                            >
                                <option value="active">Active Only</option>
                                <option value="revoked">Revoked / Expired</option>
                                <option value="all">All Sessions</option>
                            </select>
                        </div>

                        <div className="flex items-center gap-2 w-full sm:w-auto justify-end">
                            <Button size="sm" onClick={handleFilter} className="text-xs bg-emerald-600 hover:bg-emerald-700 text-white">
                                Filter
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Sessions Table */}
            <Card className="border border-border/60 shadow-sm">
                <CardContent className="p-0">
                    <div className="overflow-x-auto">
                        <table className="w-full text-xs">
                            <thead>
                                <tr className="border-b border-border/60 bg-muted/40 text-muted-foreground text-left">
                                    <th className="py-3 px-4 font-medium">User Account</th>
                                    <th className="py-3 px-4 font-medium">IP Address</th>
                                    <th className="py-3 px-4 font-medium">Device & Client</th>
                                    <th className="py-3 px-4 font-medium">Location</th>
                                    <th className="py-3 px-4 font-medium">Last Active</th>
                                    <th className="py-3 px-4 font-medium">Status</th>
                                    <th className="py-3 px-4 font-medium text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border/30">
                                {safeSessions.length === 0 ? (
                                    <tr>
                                        <td colSpan={7} className="py-12 text-center text-muted-foreground">
                                            No sessions matching the selected filter.
                                        </td>
                                    </tr>
                                ) : (
                                    safeSessions.map((session) => (
                                        <tr key={session.id} className="hover:bg-muted/30 transition">
                                            <td className="py-3.5 px-4">
                                                <div className="font-semibold text-foreground">{session.user_name}</div>
                                                <div className="text-[11px] text-muted-foreground">{session.user_email}</div>
                                            </td>
                                            <td className="py-3.5 px-4 font-mono font-medium text-muted-foreground">
                                                {session.ip_address}
                                            </td>
                                            <td className="py-3.5 px-4">
                                                <div className="font-medium text-foreground">{session.browser}</div>
                                                <div className="text-[10px] text-muted-foreground">
                                                    {session.operating_system} &bull; {session.device_type}
                                                </div>
                                            </td>
                                            <td className="py-3.5 px-4">
                                                <span>{session.city ? `${session.city}, ` : ''}{session.country_code || 'Unknown'}</span>
                                            </td>
                                            <td className="py-3.5 px-4 text-muted-foreground whitespace-nowrap">
                                                {session.last_activity}
                                            </td>
                                            <td className="py-3.5 px-4">
                                                {session.is_revoked ? (
                                                    <Badge variant="outline" className="text-muted-foreground border-border">
                                                        Revoked
                                                    </Badge>
                                                ) : (
                                                    <Badge className="bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border-emerald-500/20 font-medium">
                                                        Active
                                                    </Badge>
                                                )}
                                            </td>
                                            <td className="py-3.5 px-4 text-right">
                                                {!session.is_revoked ? (
                                                    <div className="flex items-center justify-end gap-1.5">
                                                        <Button
                                                            size="sm"
                                                            variant="destructive"
                                                            onClick={() => handleRevoke(session.session_id)}
                                                            className="h-7 px-2 text-xs"
                                                        >
                                                            <LogOut className="h-3 w-3 mr-1" /> Terminate
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() => handleRevokeAll(session.user_id, session.user_email)}
                                                            className="h-7 px-2 text-xs text-rose-600 hover:text-rose-700 hover:bg-rose-500/10 border-rose-200 dark:border-rose-900"
                                                        >
                                                            <UserX className="h-3 w-3 mr-1" /> Terminate All
                                                        </Button>
                                                    </div>
                                                ) : (
                                                    <span className="text-xs text-muted-foreground">Terminated</span>
                                                )}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {sessions && sessions.last_page > 1 && (
                        <div className="flex items-center justify-between p-4 border-t border-border/50 text-xs">
                            <span className="text-muted-foreground">
                                Page {sessions.current_page} of {sessions.last_page} ({sessions.total} total sessions)
                            </span>
                            <div className="flex items-center gap-2">
                                {sessions.prev_page_url && (
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => router.get(sessions.prev_page_url!)}
                                        className="h-7 text-xs"
                                    >
                                        Previous
                                    </Button>
                                )}
                                {sessions.next_page_url && (
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => router.get(sessions.next_page_url!)}
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
        </AdminPage>
    );
}
