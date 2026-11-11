import { Head, router } from '@inertiajs/react';
import { FileText, RefreshCw, Search, User } from 'lucide-react';
import { useState } from 'react';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type AuditLogItem = {
    id: number;
    user_id: number | null;
    user_name: string;
    action: string;
    target_type: string | null;
    target_id: string | null;
    details: Record<string, any> | null;
    ip_address: string | null;
    created_at: string;
    timestamp: string;
};

type PaginatedAuditLogs = {
    data: AuditLogItem[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Props = {
    logs: PaginatedAuditLogs;
    filters: {
        search: string;
        action: string;
    };
};

export default function AuditLogs({ logs, filters }: Props) {
    const [search, setSearch] = useState(filters?.search || '');
    const [action, setAction] = useState(filters?.action || '');
    const [selectedLog, setSelectedLog] = useState<AuditLogItem | null>(null);

    const safeLogs = Array.isArray(logs?.data) ? logs.data : [];

    const handleFilter = () => {
        router.get(
            '/admin/m/modrixcommerce/login-monitoring/audit',
            { search, action },
            { preserveState: true, replace: true },
        );
    };

    const handleReset = () => {
        setSearch('');
        setAction('');
        router.get('/admin/m/modrixcommerce/login-monitoring/audit');
    };

    return (
        <AdminPage>
            <Head title="Login Audit Logs - Login Monitoring" />

            <PageHeader
                title="Security Audit Logs"
                description="Immutable chronological record of administrator interventions, session terminations, policy updates, and security overrides."
                actions={
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => router.reload()}
                        className="gap-2"
                    >
                        <RefreshCw className="h-4 w-4" /> Refresh Logs
                    </Button>
                }
            />

            {/* Filter Toolbar */}
            <Card className="mb-6 border border-border/60 shadow-sm">
                <CardContent className="p-4">
                    <div className="flex flex-col items-center justify-between gap-3 sm:flex-row">
                        <div className="flex w-full flex-1 items-center gap-2 sm:w-auto">
                            <div className="relative max-w-sm flex-1">
                                <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    placeholder="Search by action, administrator, IP..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    onKeyDown={(e) =>
                                        e.key === 'Enter' && handleFilter()
                                    }
                                    className="h-9 pl-9 text-xs"
                                />
                            </div>

                            <select
                                value={action}
                                onChange={(e) => setAction(e.target.value)}
                                className="h-9 rounded-md border border-input bg-background px-3 text-xs"
                            >
                                <option value="">All Actions</option>
                                <option value="session">Session Actions</option>
                                <option value="incident">
                                    Incident Actions
                                </option>
                                <option value="IP">IP Management</option>
                                <option value="policy">Policy Updates</option>
                                <option value="MFA">MFA Resets</option>
                            </select>
                        </div>

                        <div className="flex w-full items-center justify-end gap-2 sm:w-auto">
                            <Button
                                size="sm"
                                onClick={handleFilter}
                                className="bg-emerald-600 text-xs text-white hover:bg-emerald-700"
                            >
                                Filter
                            </Button>
                            {(search || action) && (
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

            {/* Audit Logs Table */}
            <Card className="border border-border/60 shadow-sm">
                <CardContent className="p-0">
                    <div className="overflow-x-auto">
                        <table className="w-full text-xs">
                            <thead>
                                <tr className="border-b border-border/60 bg-muted/40 text-left text-muted-foreground">
                                    <th className="px-4 py-3 font-medium">
                                        Administrator
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Action Performed
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Target Entity
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Origin IP
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Timestamp
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Details
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border/30">
                                {safeLogs.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="py-12 text-center text-muted-foreground"
                                        >
                                            No audit trail records found
                                            matching criteria.
                                        </td>
                                    </tr>
                                ) : (
                                    safeLogs.map((log) => (
                                        <tr
                                            key={log.id}
                                            className="transition hover:bg-muted/30"
                                        >
                                            <td className="px-4 py-3.5">
                                                <div className="flex items-center gap-1.5 font-semibold text-foreground">
                                                    <User className="h-3.5 w-3.5 text-muted-foreground" />
                                                    {log.user_name}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3.5 font-medium text-foreground">
                                                {log.action}
                                            </td>
                                            <td className="px-4 py-3.5">
                                                {log.target_type && (
                                                    <Badge
                                                        variant="outline"
                                                        className="font-mono text-[10px]"
                                                    >
                                                        {log.target_type}:
                                                        {log.target_id || ''}
                                                    </Badge>
                                                )}
                                            </td>
                                            <td className="px-4 py-3.5 font-mono text-muted-foreground">
                                                {log.ip_address || 'System'}
                                            </td>
                                            <td className="px-4 py-3.5 whitespace-nowrap text-muted-foreground">
                                                <div>{log.created_at}</div>
                                                <div className="text-[10px] opacity-75">
                                                    {log.timestamp}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3.5 text-right">
                                                {log.details ? (
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() =>
                                                            setSelectedLog(log)
                                                        }
                                                        className="h-7 px-2 text-xs"
                                                    >
                                                        View JSON
                                                    </Button>
                                                ) : (
                                                    <span className="text-[11px] text-muted-foreground">
                                                        -
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {logs && logs.last_page > 1 && (
                        <div className="flex items-center justify-between border-t border-border/50 p-4 text-xs">
                            <span className="text-muted-foreground">
                                Page {logs.current_page} of {logs.last_page} (
                                {logs.total} total logs)
                            </span>
                            <div className="flex items-center gap-2">
                                {logs.prev_page_url && (
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            router.get(logs.prev_page_url!)
                                        }
                                        className="h-7 text-xs"
                                    >
                                        Previous
                                    </Button>
                                )}
                                {logs.next_page_url && (
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            router.get(logs.next_page_url!)
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

            {/* Audit Details Modal */}
            {selectedLog && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
                    <div className="relative w-full max-w-lg rounded-xl border border-border bg-card p-6 shadow-2xl">
                        <div className="mb-4 flex items-center justify-between border-b border-border/60 pb-3">
                            <div>
                                <h3 className="flex items-center gap-2 text-base font-semibold">
                                    <FileText className="h-4 w-4 text-emerald-600" />
                                    Audit Entry #{selectedLog.id}
                                </h3>
                                <p className="mt-0.5 text-xs text-muted-foreground">
                                    {selectedLog.action}
                                </p>
                            </div>
                            <Button
                                size="sm"
                                variant="ghost"
                                onClick={() => setSelectedLog(null)}
                            >
                                Close
                            </Button>
                        </div>

                        <div className="space-y-3 text-xs">
                            <div className="flex justify-between text-muted-foreground">
                                <span>
                                    Actor:{' '}
                                    <strong className="text-foreground">
                                        {selectedLog.user_name}
                                    </strong>
                                </span>
                                <span>
                                    IP:{' '}
                                    <strong className="font-mono text-foreground">
                                        {selectedLog.ip_address}
                                    </strong>
                                </span>
                            </div>

                            <div className="border-t border-border/40 pt-3">
                                <span className="mb-1 block text-xs font-semibold text-foreground">
                                    Metadata / Payload:
                                </span>
                                <pre className="overflow-x-auto rounded-lg bg-muted p-3 font-mono text-[11px] leading-relaxed text-foreground">
                                    {JSON.stringify(
                                        selectedLog.details,
                                        null,
                                        2,
                                    )}
                                </pre>
                            </div>
                        </div>

                        <div className="mt-6 flex justify-end border-t border-border/60 pt-4">
                            <Button
                                size="sm"
                                onClick={() => setSelectedLog(null)}
                            >
                                Dismiss
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </AdminPage>
    );
}
