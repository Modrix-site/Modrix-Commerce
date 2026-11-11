import { Head, router } from '@inertiajs/react';
import { Globe, History, Mail, Plus, Server, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { EmptyState } from '@/components/admin/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';

type BlacklistedIp = {
    id: number;
    ip_address: string;
    reason: string | null;
    is_active: boolean;
    is_expired: boolean;
    created_by: string | null;
    expires_at: string | null;
    created_at: string;
    delete_url: string;
};

type BlacklistedEmail = {
    id: number;
    domain: string;
    reason: string | null;
    is_active: boolean;
    created_by: string | null;
    created_at: string;
    delete_url: string;
};

type CountryRisk = {
    id: number;
    country_code: string;
    country_name: string;
    risk_level: string;
    score_modifier: number;
    delete_url: string;
};

type AuditLog = {
    id: number;
    user_name: string;
    action: string;
    entity_type: string;
    entity_id: number;
    metadata: any;
    ip_address: string | null;
    created_at: string;
};

type Props = {
    blacklistedIps: BlacklistedIp[];
    blacklistedEmails: BlacklistedEmail[];
    countryRisks: CountryRisk[];
    auditLogs: AuditLog[];
    addIpUrl: string;
    addEmailUrl: string;
    addCountryUrl: string;
};

export default function FraudSettings({
    blacklistedIps,
    blacklistedEmails,
    countryRisks,
    auditLogs,
    addIpUrl,
    addEmailUrl,
    addCountryUrl,
}: Props) {
    const [activeTab, setActiveTab] = useState<
        'ips' | 'emails' | 'countries' | 'audit'
    >('ips');
    const [submitting, setSubmitting] = useState(false);

    // Modals
    const [ipModal, setIpModal] = useState(false);
    const [ipForm, setIpForm] = useState({
        ip_address: '',
        reason: '',
        expires_at: '',
    });

    const [emailModal, setEmailModal] = useState(false);
    const [emailForm, setEmailForm] = useState({ domain: '', reason: '' });

    const [countryModal, setCountryModal] = useState(false);
    const [countryForm, setCountryForm] = useState({
        country_code: '',
        country_name: '',
        risk_level: 'high',
        score_modifier: 35,
    });

    const handleAddIp = (e: React.FormEvent) => {
        e.preventDefault();
        setSubmitting(true);
        router.post(addIpUrl, ipForm, {
            onSuccess: () => {
                setIpModal(false);
                setIpForm({ ip_address: '', reason: '', expires_at: '' });
            },
            onFinish: () => setSubmitting(false),
        });
    };

    const handleAddEmail = (e: React.FormEvent) => {
        e.preventDefault();
        setSubmitting(true);
        router.post(addEmailUrl, emailForm, {
            onSuccess: () => {
                setEmailModal(false);
                setEmailForm({ domain: '', reason: '' });
            },
            onFinish: () => setSubmitting(false),
        });
    };

    const handleAddCountry = (e: React.FormEvent) => {
        e.preventDefault();
        setSubmitting(true);
        router.post(addCountryUrl, countryForm, {
            onSuccess: () => {
                setCountryModal(false);
                setCountryForm({
                    country_code: '',
                    country_name: '',
                    risk_level: 'high',
                    score_modifier: 35,
                });
            },
            onFinish: () => setSubmitting(false),
        });
    };

    const handleDelete = (deleteUrl: string) => {
        if (!confirm('Are you sure you want to remove this entry?')) return;
        router.delete(deleteUrl);
    };

    return (
        <>
            <Head title="Fraud Detection — Configuration & Blacklists" />
            <AdminPage>
                <PageHeader
                    title="Fraud Settings & Blacklists"
                    description="Configure IP address blacklists, disposable email filters, high-risk country scoring, and review internal audit logs."
                />

                {/* Tab Navigation */}
                <div className="mb-6 flex gap-2 border-b border-border">
                    <button
                        onClick={() => setActiveTab('ips')}
                        className={`flex items-center gap-2 border-b-2 px-3 pb-3 text-sm font-medium transition-all ${
                            activeTab === 'ips'
                                ? 'border-primary font-semibold text-foreground'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        <Server className="size-4" /> Blacklisted IPs (
                        {blacklistedIps.length})
                    </button>
                    <button
                        onClick={() => setActiveTab('emails')}
                        className={`flex items-center gap-2 border-b-2 px-3 pb-3 text-sm font-medium transition-all ${
                            activeTab === 'emails'
                                ? 'border-primary font-semibold text-foreground'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        <Mail className="size-4" /> Email Domains (
                        {blacklistedEmails.length})
                    </button>
                    <button
                        onClick={() => setActiveTab('countries')}
                        className={`flex items-center gap-2 border-b-2 px-3 pb-3 text-sm font-medium transition-all ${
                            activeTab === 'countries'
                                ? 'border-primary font-semibold text-foreground'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        <Globe className="size-4" /> Country Risk Tiers (
                        {countryRisks.length})
                    </button>
                    <button
                        onClick={() => setActiveTab('audit')}
                        className={`flex items-center gap-2 border-b-2 px-3 pb-3 text-sm font-medium transition-all ${
                            activeTab === 'audit'
                                ? 'border-primary font-semibold text-foreground'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        <History className="size-4" /> Security Audit Trail (
                        {auditLogs.length})
                    </button>
                </div>

                {/* Tab 1: IP Blacklist */}
                {activeTab === 'ips' && (
                    <Card className="shadow-sm">
                        <CardHeader className="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle className="text-base font-semibold">
                                    IP Address Blacklist
                                </CardTitle>
                                <CardDescription>
                                    Transactions originating from these IP
                                    addresses receive an immediate high-risk
                                    penalty.
                                </CardDescription>
                            </div>
                            <Button onClick={() => setIpModal(true)}>
                                <Plus className="mr-1.5 size-4" /> Add
                                Blacklisted IP
                            </Button>
                        </CardHeader>
                        <CardContent className="p-0">
                            {blacklistedIps.length === 0 ? (
                                <div className="p-8">
                                    <EmptyState
                                        title="No blacklisted IPs"
                                        description="You have not added any prohibited IP addresses yet."
                                        action={
                                            <Button
                                                onClick={() => setIpModal(true)}
                                            >
                                                <Plus className="mr-1.5 size-4" />{' '}
                                                Add IP Address
                                            </Button>
                                        }
                                    />
                                </div>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-xs">
                                        <thead className="border-b bg-muted/30 font-semibold tracking-wider text-muted-foreground uppercase">
                                            <tr>
                                                <th className="p-3.5 pl-6">
                                                    IP Address
                                                </th>
                                                <th className="p-3.5">
                                                    Reason
                                                </th>
                                                <th className="p-3.5">
                                                    Added By
                                                </th>
                                                <th className="p-3.5">
                                                    Expires
                                                </th>
                                                <th className="p-3.5">
                                                    Status
                                                </th>
                                                <th className="p-3.5 pr-6 text-right">
                                                    Action
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {blacklistedIps.map((ip) => (
                                                <tr
                                                    key={ip.id}
                                                    className="transition-colors hover:bg-muted/10"
                                                >
                                                    <td className="p-3.5 pl-6 font-mono font-semibold text-foreground">
                                                        {ip.ip_address}
                                                    </td>
                                                    <td className="p-3.5 text-muted-foreground">
                                                        {ip.reason || '—'}
                                                    </td>
                                                    <td className="p-3.5">
                                                        {ip.created_by ||
                                                            'Admin'}
                                                    </td>
                                                    <td className="p-3.5 text-muted-foreground">
                                                        {ip.expires_at ||
                                                            'Never'}
                                                    </td>
                                                    <td className="p-3.5">
                                                        <Badge
                                                            className={
                                                                ip.is_active &&
                                                                !ip.is_expired
                                                                    ? 'border-red-200 bg-red-500/15 text-red-600'
                                                                    : 'bg-muted text-muted-foreground'
                                                            }
                                                        >
                                                            {ip.is_expired
                                                                ? 'Expired'
                                                                : ip.is_active
                                                                  ? 'Blocked'
                                                                  : 'Inactive'}
                                                        </Badge>
                                                    </td>
                                                    <td className="p-3.5 pr-6 text-right">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            className="text-red-600 hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-950/20"
                                                            onClick={() =>
                                                                handleDelete(
                                                                    ip.delete_url,
                                                                )
                                                            }
                                                        >
                                                            <Trash2 className="size-3.5" />
                                                        </Button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Tab 2: Email Domains */}
                {activeTab === 'emails' && (
                    <Card className="shadow-sm">
                        <CardHeader className="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle className="text-base font-semibold">
                                    Disposable & Blocked Email Domains
                                </CardTitle>
                                <CardDescription>
                                    Any checkout using emails ending in these
                                    domains will trigger immediate disposable
                                    email warnings.
                                </CardDescription>
                            </div>
                            <Button onClick={() => setEmailModal(true)}>
                                <Plus className="mr-1.5 size-4" /> Add Email
                                Domain
                            </Button>
                        </CardHeader>
                        <CardContent className="p-0">
                            {blacklistedEmails.length === 0 ? (
                                <div className="p-8">
                                    <EmptyState
                                        title="No blacklisted email domains"
                                        description="You have not added any blocked email domains yet."
                                        action={
                                            <Button
                                                onClick={() =>
                                                    setEmailModal(true)
                                                }
                                            >
                                                <Plus className="mr-1.5 size-4" />{' '}
                                                Add Email Domain
                                            </Button>
                                        }
                                    />
                                </div>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-xs">
                                        <thead className="border-b bg-muted/30 font-semibold tracking-wider text-muted-foreground uppercase">
                                            <tr>
                                                <th className="p-3.5 pl-6">
                                                    Domain
                                                </th>
                                                <th className="p-3.5">
                                                    Reason
                                                </th>
                                                <th className="p-3.5">
                                                    Added By
                                                </th>
                                                <th className="p-3.5">
                                                    Added Date
                                                </th>
                                                <th className="p-3.5 pr-6 text-right">
                                                    Action
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {blacklistedEmails.map((e) => (
                                                <tr
                                                    key={e.id}
                                                    className="transition-colors hover:bg-muted/10"
                                                >
                                                    <td className="p-3.5 pl-6 font-mono font-semibold text-foreground">
                                                        @{e.domain}
                                                    </td>
                                                    <td className="p-3.5 text-muted-foreground">
                                                        {e.reason ||
                                                            'Disposable email service'}
                                                    </td>
                                                    <td className="p-3.5">
                                                        {e.created_by ||
                                                            'Admin'}
                                                    </td>
                                                    <td className="p-3.5 text-muted-foreground">
                                                        {e.created_at}
                                                    </td>
                                                    <td className="p-3.5 pr-6 text-right">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            className="text-red-600 hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-950/20"
                                                            onClick={() =>
                                                                handleDelete(
                                                                    e.delete_url,
                                                                )
                                                            }
                                                        >
                                                            <Trash2 className="size-3.5" />
                                                        </Button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Tab 3: Country Risk */}
                {activeTab === 'countries' && (
                    <Card className="shadow-sm">
                        <CardHeader className="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle className="text-base font-semibold">
                                    High-Risk Country Scoring
                                </CardTitle>
                                <CardDescription>
                                    Adjust risk weight modifiers for
                                    transactions originating from specific
                                    jurisdictions.
                                </CardDescription>
                            </div>
                            <Button onClick={() => setCountryModal(true)}>
                                <Plus className="mr-1.5 size-4" /> Add Country
                                Rule
                            </Button>
                        </CardHeader>
                        <CardContent className="p-0">
                            {countryRisks.length === 0 ? (
                                <div className="p-8">
                                    <EmptyState
                                        title="No custom country risk tiers"
                                        description="Add countries with specific risk score modifiers."
                                        action={
                                            <Button
                                                onClick={() =>
                                                    setCountryModal(true)
                                                }
                                            >
                                                <Plus className="mr-1.5 size-4" />{' '}
                                                Add Country
                                            </Button>
                                        }
                                    />
                                </div>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-xs">
                                        <thead className="border-b bg-muted/30 font-semibold tracking-wider text-muted-foreground uppercase">
                                            <tr>
                                                <th className="p-3.5 pl-6">
                                                    Country Code
                                                </th>
                                                <th className="p-3.5">
                                                    Country Name
                                                </th>
                                                <th className="p-3.5">
                                                    Risk Tier
                                                </th>
                                                <th className="p-3.5">
                                                    Score Modifier
                                                </th>
                                                <th className="p-3.5 pr-6 text-right">
                                                    Action
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {countryRisks.map((c) => (
                                                <tr
                                                    key={c.id}
                                                    className="transition-colors hover:bg-muted/10"
                                                >
                                                    <td className="p-3.5 pl-6 font-mono font-bold text-foreground">
                                                        {c.country_code}
                                                    </td>
                                                    <td className="p-3.5 font-medium">
                                                        {c.country_name}
                                                    </td>
                                                    <td className="p-3.5">
                                                        <Badge
                                                            className={
                                                                c.risk_level ===
                                                                'high'
                                                                    ? 'border-red-200 bg-red-500/15 text-red-600'
                                                                    : 'border-amber-200 bg-amber-500/15 text-amber-600'
                                                            }
                                                        >
                                                            {c.risk_level.toUpperCase()}
                                                        </Badge>
                                                    </td>
                                                    <td className="p-3.5 font-mono font-bold text-red-600">
                                                        +{c.score_modifier} pts
                                                    </td>
                                                    <td className="p-3.5 pr-6 text-right">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            className="text-red-600 hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-950/20"
                                                            onClick={() =>
                                                                handleDelete(
                                                                    c.delete_url,
                                                                )
                                                            }
                                                        >
                                                            <Trash2 className="size-3.5" />
                                                        </Button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Tab 4: Security Audit Log */}
                {activeTab === 'audit' && (
                    <Card className="shadow-sm">
                        <CardHeader>
                            <CardTitle className="text-base font-semibold">
                                Security Audit Log
                            </CardTitle>
                            <CardDescription>
                                Immutable audit trail of merchant decisions,
                                blacklist updates, and fraud score
                                interventions.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="p-0">
                            {auditLogs.length === 0 ? (
                                <div className="p-8 text-center text-xs text-muted-foreground">
                                    No audit entries recorded yet.
                                </div>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-xs">
                                        <thead className="border-b bg-muted/30 font-semibold tracking-wider text-muted-foreground uppercase">
                                            <tr>
                                                <th className="p-3.5 pl-6">
                                                    Timestamp
                                                </th>
                                                <th className="p-3.5">User</th>
                                                <th className="p-3.5">
                                                    Action
                                                </th>
                                                <th className="p-3.5">
                                                    Entity
                                                </th>
                                                <th className="p-3.5">IP</th>
                                                <th className="p-3.5 pr-6">
                                                    Details
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y font-mono">
                                            {auditLogs.map((log) => (
                                                <tr
                                                    key={log.id}
                                                    className="transition-colors hover:bg-muted/10"
                                                >
                                                    <td className="p-3.5 pl-6 font-sans whitespace-nowrap text-muted-foreground">
                                                        {log.created_at}
                                                    </td>
                                                    <td className="p-3.5 font-sans font-semibold text-foreground">
                                                        {log.user_name}
                                                    </td>
                                                    <td className="p-3.5 font-sans">
                                                        <Badge variant="outline">
                                                            {log.action}
                                                        </Badge>
                                                    </td>
                                                    <td className="p-3.5 text-muted-foreground">
                                                        {log.entity_type} #
                                                        {log.entity_id}
                                                    </td>
                                                    <td className="p-3.5 text-muted-foreground">
                                                        {log.ip_address || '—'}
                                                    </td>
                                                    <td className="max-w-xs truncate p-3.5 pr-6 font-sans text-[11px] text-muted-foreground">
                                                        {log.metadata
                                                            ? JSON.stringify(
                                                                  log.metadata,
                                                              )
                                                            : '—'}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Add IP Modal */}
                <Dialog open={ipModal} onOpenChange={setIpModal}>
                    <DialogContent className="sm:max-w-md">
                        <form onSubmit={handleAddIp}>
                            <DialogHeader>
                                <DialogTitle>
                                    Add Blacklisted IP Address
                                </DialogTitle>
                                <DialogDescription>
                                    Future transactions from this IPv4/IPv6
                                    address will receive a maximum fraud score
                                    penalty.
                                </DialogDescription>
                            </DialogHeader>
                            <div className="space-y-3 py-4 text-sm">
                                <div>
                                    <label className="mb-1 block text-xs font-semibold text-muted-foreground">
                                        IP Address
                                    </label>
                                    <Input
                                        placeholder="e.g. 198.51.100.42"
                                        value={ipForm.ip_address}
                                        onChange={(e) =>
                                            setIpForm({
                                                ...ipForm,
                                                ip_address: e.target.value,
                                            })
                                        }
                                        required
                                    />
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs font-semibold text-muted-foreground">
                                        Reason
                                    </label>
                                    <Input
                                        placeholder="e.g. Repeated card testing attack"
                                        value={ipForm.reason}
                                        onChange={(e) =>
                                            setIpForm({
                                                ...ipForm,
                                                reason: e.target.value,
                                            })
                                        }
                                    />
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs font-semibold text-muted-foreground">
                                        Expiry Date (Optional)
                                    </label>
                                    <Input
                                        type="date"
                                        value={ipForm.expires_at}
                                        onChange={(e) =>
                                            setIpForm({
                                                ...ipForm,
                                                expires_at: e.target.value,
                                            })
                                        }
                                    />
                                </div>
                            </div>
                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setIpModal(false)}
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={submitting}>
                                    {submitting
                                        ? 'Saving...'
                                        : 'Add to Blacklist'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>

                {/* Add Email Domain Modal */}
                <Dialog open={emailModal} onOpenChange={setEmailModal}>
                    <DialogContent className="sm:max-w-md">
                        <form onSubmit={handleAddEmail}>
                            <DialogHeader>
                                <DialogTitle>
                                    Add Blocked Email Domain
                                </DialogTitle>
                                <DialogDescription>
                                    Block disposable email providers or spam
                                    domains. Enter domain name without @.
                                </DialogDescription>
                            </DialogHeader>
                            <div className="space-y-3 py-4 text-sm">
                                <div>
                                    <label className="mb-1 block text-xs font-semibold text-muted-foreground">
                                        Domain Name
                                    </label>
                                    <Input
                                        placeholder="e.g. tempmail.ninja"
                                        value={emailForm.domain}
                                        onChange={(e) =>
                                            setEmailForm({
                                                ...emailForm,
                                                domain: e.target.value,
                                            })
                                        }
                                        required
                                    />
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs font-semibold text-muted-foreground">
                                        Reason (Optional)
                                    </label>
                                    <Input
                                        placeholder="e.g. Known disposable mailbox generator"
                                        value={emailForm.reason}
                                        onChange={(e) =>
                                            setEmailForm({
                                                ...emailForm,
                                                reason: e.target.value,
                                            })
                                        }
                                    />
                                </div>
                            </div>
                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setEmailModal(false)}
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={submitting}>
                                    {submitting ? 'Saving...' : 'Add Domain'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>

                {/* Add Country Modal */}
                <Dialog open={countryModal} onOpenChange={setCountryModal}>
                    <DialogContent className="sm:max-w-md">
                        <form onSubmit={handleAddCountry}>
                            <DialogHeader>
                                <DialogTitle>
                                    Add High-Risk Country Rule
                                </DialogTitle>
                                <DialogDescription>
                                    Assign risk penalties for orders placed or
                                    shipped to specific countries.
                                </DialogDescription>
                            </DialogHeader>
                            <div className="space-y-3 py-4 text-sm">
                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label className="mb-1 block text-xs font-semibold text-muted-foreground">
                                            2-Letter Code
                                        </label>
                                        <Input
                                            placeholder="e.g. NG, RU, VN"
                                            maxLength={2}
                                            value={countryForm.country_code}
                                            onChange={(e) =>
                                                setCountryForm({
                                                    ...countryForm,
                                                    country_code:
                                                        e.target.value.toUpperCase(),
                                                })
                                            }
                                            required
                                        />
                                    </div>
                                    <div>
                                        <label className="mb-1 block text-xs font-semibold text-muted-foreground">
                                            Country Name
                                        </label>
                                        <Input
                                            placeholder="e.g. Nigeria"
                                            value={countryForm.country_name}
                                            onChange={(e) =>
                                                setCountryForm({
                                                    ...countryForm,
                                                    country_name:
                                                        e.target.value,
                                                })
                                            }
                                            required
                                        />
                                    </div>
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label className="mb-1 block text-xs font-semibold text-muted-foreground">
                                            Risk Tier
                                        </label>
                                        <select
                                            className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm"
                                            value={countryForm.risk_level}
                                            onChange={(e) =>
                                                setCountryForm({
                                                    ...countryForm,
                                                    risk_level: e.target.value,
                                                })
                                            }
                                        >
                                            <option value="high">
                                                High Risk
                                            </option>
                                            <option value="medium">
                                                Medium Risk
                                            </option>
                                        </select>
                                    </div>
                                    <div>
                                        <label className="mb-1 block text-xs font-semibold text-muted-foreground">
                                            Score Penalty (pts)
                                        </label>
                                        <Input
                                            type="number"
                                            min={1}
                                            max={100}
                                            value={countryForm.score_modifier}
                                            onChange={(e) =>
                                                setCountryForm({
                                                    ...countryForm,
                                                    score_modifier:
                                                        parseInt(
                                                            e.target.value,
                                                        ) || 1,
                                                })
                                            }
                                            required
                                        />
                                    </div>
                                </div>
                            </div>
                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setCountryModal(false)}
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={submitting}>
                                    {submitting
                                        ? 'Saving...'
                                        : 'Add Country Rule'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </AdminPage>
        </>
    );
}
