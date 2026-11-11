import { Head, router } from '@inertiajs/react';
import {
    AlertOctagon,
    CheckCircle2,
    Clock,
    RefreshCw,
    Search,
    ShieldAlert,
} from 'lucide-react';
import { useState } from 'react';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type IncidentItem = {
    id: number;
    uuid: string;
    incident_type: string;
    user_id: number | null;
    user_name: string | null;
    email: string;
    ip_address: string;
    severity: string;
    status: string;
    description: string;
    metadata: Record<string, any>;
    resolved_by: string | null;
    resolved_at: string | null;
    resolution_notes: string | null;
    created_at: string;
    timestamp: string;
};

type PaginatedIncidents = {
    data: IncidentItem[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Props = {
    incidents: PaginatedIncidents;
    summary: {
        open: number;
        investigating: number;
        resolved: number;
        critical: number;
    };
    filters: {
        search: string;
        status: string;
        severity: string;
        type: string;
    };
};

export default function SecurityIncidents({
    incidents,
    summary,
    filters,
}: Props) {
    const [search, setSearch] = useState(filters?.search || '');
    const [status, setStatus] = useState(filters?.status || '');
    const [severity, setSeverity] = useState(filters?.severity || '');
    const [type, setType] = useState(filters?.type || '');

    const [selectedIncident, setSelectedIncident] =
        useState<IncidentItem | null>(null);
    const [newStatus, setNewStatus] = useState('resolved');
    const [resolutionNotes, setResolutionNotes] = useState('');

    const safeIncidents = Array.isArray(incidents?.data) ? incidents.data : [];

    const handleFilter = () => {
        router.get(
            '/admin/m/modrixcommerce/login-monitoring/incidents',
            { search, status, severity, type },
            { preserveState: true, replace: true },
        );
    };

    const handleUpdateStatus = () => {
        if (!selectedIncident) return;
        router.post(
            `/admin/m/modrixcommerce/login-monitoring/incidents/${selectedIncident.id}/status`,
            {
                status: newStatus,
                resolution_notes: resolutionNotes,
            },
            {
                onSuccess: () => {
                    setSelectedIncident(null);
                    setResolutionNotes('');
                },
            },
        );
    };

    const severityBadge = (sev: string) => {
        switch (sev) {
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
                        Medium
                    </Badge>
                );
            default:
                return (
                    <Badge
                        variant="outline"
                        className="border-blue-500/30 text-blue-600"
                    >
                        Low
                    </Badge>
                );
        }
    };

    const statusBadge = (st: string) => {
        switch (st) {
            case 'open':
                return (
                    <Badge className="border-rose-500/20 bg-rose-500/15 font-medium text-rose-600 dark:text-rose-400">
                        Open
                    </Badge>
                );
            case 'investigating':
                return (
                    <Badge className="border-amber-500/20 bg-amber-500/15 font-medium text-amber-600 dark:text-amber-400">
                        Investigating
                    </Badge>
                );
            case 'resolved':
                return (
                    <Badge className="border-emerald-500/20 bg-emerald-500/15 font-medium text-emerald-600 dark:text-emerald-400">
                        Resolved
                    </Badge>
                );
            case 'dismissed':
                return (
                    <Badge variant="outline" className="text-muted-foreground">
                        Dismissed
                    </Badge>
                );
            default:
                return <Badge variant="outline">{st}</Badge>;
        }
    };

    const formatType = (t: string) => {
        return t.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
    };

    return (
        <AdminPage>
            <Head title="Security Incidents - Login Monitoring" />

            <PageHeader
                title="Security Incidents"
                description="Investigate brute force, credential stuffing, and impossible travel authentication alerts."
                actions={
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => router.reload()}
                        className="gap-2"
                    >
                        <RefreshCw className="h-4 w-4" /> Refresh
                    </Button>
                }
            />

            {/* KPI Cards */}
            <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Card className="border border-border/60 shadow-sm">
                    <CardContent className="flex items-center justify-between p-4">
                        <div>
                            <span className="text-xs font-semibold text-muted-foreground uppercase">
                                Open Incidents
                            </span>
                            <div className="mt-1 text-2xl font-bold text-rose-600 dark:text-rose-400">
                                {summary?.open || 0}
                            </div>
                        </div>
                        <div className="rounded-lg bg-rose-500/10 p-2 text-rose-600">
                            <AlertOctagon className="h-5 w-5" />
                        </div>
                    </CardContent>
                </Card>

                <Card className="border border-border/60 shadow-sm">
                    <CardContent className="flex items-center justify-between p-4">
                        <div>
                            <span className="text-xs font-semibold text-muted-foreground uppercase">
                                Under Investigation
                            </span>
                            <div className="mt-1 text-2xl font-bold text-amber-600 dark:text-amber-400">
                                {summary?.investigating || 0}
                            </div>
                        </div>
                        <div className="rounded-lg bg-amber-500/10 p-2 text-amber-600">
                            <Clock className="h-5 w-5" />
                        </div>
                    </CardContent>
                </Card>

                <Card className="border border-border/60 shadow-sm">
                    <CardContent className="flex items-center justify-between p-4">
                        <div>
                            <span className="text-xs font-semibold text-muted-foreground uppercase">
                                Resolved Incidents
                            </span>
                            <div className="mt-1 text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                                {summary?.resolved || 0}
                            </div>
                        </div>
                        <div className="rounded-lg bg-emerald-500/10 p-2 text-emerald-600">
                            <CheckCircle2 className="h-5 w-5" />
                        </div>
                    </CardContent>
                </Card>

                <Card className="border border-border/60 shadow-sm">
                    <CardContent className="flex items-center justify-between p-4">
                        <div>
                            <span className="text-xs font-semibold text-muted-foreground uppercase">
                                Critical Severity
                            </span>
                            <div className="mt-1 text-2xl font-bold text-red-600">
                                {summary?.critical || 0}
                            </div>
                        </div>
                        <div className="rounded-lg bg-red-500/10 p-2 text-red-600">
                            <ShieldAlert className="h-5 w-5" />
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Filter Toolbar */}
            <Card className="mb-6 border border-border/60 shadow-sm">
                <CardContent className="p-4">
                    <div className="flex flex-col items-center justify-between gap-3 md:flex-row">
                        <div className="flex w-full flex-1 flex-wrap items-center gap-2 md:w-auto">
                            <div className="relative min-w-[200px] flex-1">
                                <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    placeholder="Search by email, IP, description..."
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
                                <option value="open">Open</option>
                                <option value="investigating">
                                    Investigating
                                </option>
                                <option value="resolved">Resolved</option>
                                <option value="dismissed">Dismissed</option>
                            </select>

                            <select
                                value={severity}
                                onChange={(e) => setSeverity(e.target.value)}
                                className="h-9 rounded-md border border-input bg-background px-3 text-xs"
                            >
                                <option value="">All Severities</option>
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>

                            <select
                                value={type}
                                onChange={(e) => setType(e.target.value)}
                                className="h-9 rounded-md border border-input bg-background px-3 text-xs"
                            >
                                <option value="">All Attack Types</option>
                                <option value="brute_force">Brute Force</option>
                                <option value="impossible_travel">
                                    Impossible Travel
                                </option>
                                <option value="credential_stuffing">
                                    Credential Stuffing
                                </option>
                            </select>
                        </div>

                        <div className="flex items-center gap-2">
                            <Button
                                size="sm"
                                onClick={handleFilter}
                                className="bg-emerald-600 text-xs text-white hover:bg-emerald-700"
                            >
                                Filter
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Incidents Table */}
            <Card className="border border-border/60 shadow-sm">
                <CardContent className="p-0">
                    <div className="overflow-x-auto">
                        <table className="w-full text-xs">
                            <thead>
                                <tr className="border-b border-border/60 bg-muted/40 text-left text-muted-foreground">
                                    <th className="px-4 py-3 font-medium">
                                        Type
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Severity
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Status
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Account / IP
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Description
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Logged
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border/30">
                                {safeIncidents.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={7}
                                            className="py-12 text-center text-muted-foreground"
                                        >
                                            No security incidents matching
                                            filter criteria.
                                        </td>
                                    </tr>
                                ) : (
                                    safeIncidents.map((incident) => (
                                        <tr
                                            key={incident.id}
                                            className="transition hover:bg-muted/30"
                                        >
                                            <td className="px-4 py-3.5 font-medium text-foreground">
                                                {formatType(
                                                    incident.incident_type,
                                                )}
                                            </td>
                                            <td className="px-4 py-3.5">
                                                {severityBadge(
                                                    incident.severity,
                                                )}
                                            </td>
                                            <td className="px-4 py-3.5">
                                                {statusBadge(incident.status)}
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <div className="font-semibold text-foreground">
                                                    {incident.email}
                                                </div>
                                                <div className="font-mono text-[11px] text-muted-foreground">
                                                    {incident.ip_address}
                                                </div>
                                            </td>
                                            <td className="max-w-sm px-4 py-3.5">
                                                <div className="line-clamp-2 font-medium text-foreground">
                                                    {incident.description}
                                                </div>
                                                {incident.resolved_by && (
                                                    <div className="mt-0.5 text-[10px] text-emerald-600">
                                                        Resolved by{' '}
                                                        {incident.resolved_by}{' '}
                                                        on{' '}
                                                        {incident.resolved_at}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-4 py-3.5 whitespace-nowrap text-muted-foreground">
                                                {incident.created_at}
                                            </td>
                                            <td className="px-4 py-3.5 text-right">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => {
                                                        setSelectedIncident(
                                                            incident,
                                                        );
                                                        setNewStatus(
                                                            incident.status ===
                                                                'open'
                                                                ? 'resolved'
                                                                : incident.status,
                                                        );
                                                        setResolutionNotes(
                                                            incident.resolution_notes ||
                                                                '',
                                                        );
                                                    }}
                                                    className="h-7 px-2.5 text-xs"
                                                >
                                                    Manage
                                                </Button>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {incidents && incidents.last_page > 1 && (
                        <div className="flex items-center justify-between border-t border-border/50 p-4 text-xs">
                            <span className="text-muted-foreground">
                                Page {incidents.current_page} of{' '}
                                {incidents.last_page} ({incidents.total} total
                                incidents)
                            </span>
                            <div className="flex items-center gap-2">
                                {incidents.prev_page_url && (
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            router.get(incidents.prev_page_url!)
                                        }
                                        className="h-7 text-xs"
                                    >
                                        Previous
                                    </Button>
                                )}
                                {incidents.next_page_url && (
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            router.get(incidents.next_page_url!)
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

            {/* Manage Incident Modal */}
            {selectedIncident && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
                    <div className="relative w-full max-w-lg rounded-xl border border-border bg-card p-6 shadow-2xl">
                        <div className="mb-4 flex items-center justify-between border-b border-border/60 pb-3">
                            <div>
                                <h3 className="flex items-center gap-2 text-base font-semibold">
                                    <ShieldAlert className="h-4 w-4 text-emerald-600" />
                                    Manage Incident #{selectedIncident.id}
                                </h3>
                                <p className="mt-0.5 font-mono text-xs text-muted-foreground">
                                    {selectedIncident.uuid}
                                </p>
                            </div>
                            <Button
                                size="sm"
                                variant="ghost"
                                onClick={() => setSelectedIncident(null)}
                            >
                                Close
                            </Button>
                        </div>

                        <div className="space-y-4 text-xs">
                            <div>
                                <span className="font-semibold text-muted-foreground">
                                    Alert Summary:
                                </span>
                                <p className="mt-1 text-sm font-medium text-foreground">
                                    {selectedIncident.description}
                                </p>
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <span className="text-muted-foreground">
                                        Attack Type:
                                    </span>
                                    <p className="font-medium text-foreground">
                                        {formatType(
                                            selectedIncident.incident_type,
                                        )}
                                    </p>
                                </div>
                                <div>
                                    <span className="text-muted-foreground">
                                        Severity:
                                    </span>
                                    <div>
                                        {severityBadge(
                                            selectedIncident.severity,
                                        )}
                                    </div>
                                </div>
                                <div>
                                    <span className="text-muted-foreground">
                                        Account:
                                    </span>
                                    <p className="font-medium text-foreground">
                                        {selectedIncident.email}
                                    </p>
                                </div>
                                <div>
                                    <span className="text-muted-foreground">
                                        Origin IP:
                                    </span>
                                    <p className="font-mono font-medium">
                                        {selectedIncident.ip_address}
                                    </p>
                                </div>
                            </div>

                            <div className="space-y-1.5 border-t border-border/40 pt-3">
                                <label className="font-semibold text-foreground">
                                    Update Incident Status:
                                </label>
                                <select
                                    value={newStatus}
                                    onChange={(e) =>
                                        setNewStatus(e.target.value)
                                    }
                                    className="h-9 w-full rounded-md border border-input bg-background px-3 text-xs"
                                >
                                    <option value="open">
                                        Open (Unresolved)
                                    </option>
                                    <option value="investigating">
                                        Investigating
                                    </option>
                                    <option value="resolved">Resolved</option>
                                    <option value="dismissed">
                                        Dismissed (False Positive)
                                    </option>
                                </select>
                            </div>

                            <div className="space-y-1.5">
                                <label className="font-semibold text-foreground">
                                    Resolution Notes & Findings:
                                </label>
                                <textarea
                                    rows={3}
                                    value={resolutionNotes}
                                    onChange={(e) =>
                                        setResolutionNotes(e.target.value)
                                    }
                                    placeholder="Document merchant findings, verification steps taken, or root cause..."
                                    className="w-full rounded-md border border-input bg-background p-2.5 text-xs"
                                />
                            </div>
                        </div>

                        <div className="mt-6 flex items-center justify-end gap-2 border-t border-border/60 pt-4">
                            <Button
                                size="sm"
                                variant="ghost"
                                onClick={() => setSelectedIncident(null)}
                            >
                                Cancel
                            </Button>
                            <Button
                                size="sm"
                                onClick={handleUpdateStatus}
                                className="bg-emerald-600 text-white hover:bg-emerald-700"
                            >
                                Save Status
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </AdminPage>
    );
}
