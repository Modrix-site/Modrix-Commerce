import { Head, router } from '@inertiajs/react';
import {
    AlertOctagon,
    Ban,
    Bell,
    Check,
    Clock,
    Globe,
    Lock,
    Plus,
    Save,
    Shield,
    Trash2,
    UserCheck,
} from 'lucide-react';
import { useState } from 'react';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type PolicySettings = {
    enabled: boolean;
    max_failed_attempts: number;
    lockout_duration_minutes: number;
    session_lifetime_hours: number;
    detect_impossible_travel: boolean;
    impossible_travel_speed_kmh: number;
    alert_on_new_device: boolean;
    alert_on_suspicious_login: boolean;
    email_alerts_enabled: boolean;
    alert_emails: string;
    slack_webhook_url: string;
};

type AllowedIp = {
    id: number;
    ip_address: string;
    label: string | null;
    description: string | null;
    created_at: string;
};

type BlockedIp = {
    id: number;
    ip_address: string;
    reason: string | null;
    attack_type: string | null;
    expires_at: string | null;
    created_at: string;
};

type Props = {
    settings: PolicySettings;
    allowedIps: AllowedIp[];
    blockedIps: BlockedIp[];
};

export default function AccessPolicies({ settings, allowedIps = [], blockedIps = [] }: Props) {
    const [form, setForm] = useState<PolicySettings>({
        enabled: settings?.enabled ?? true,
        max_failed_attempts: settings?.max_failed_attempts ?? 5,
        lockout_duration_minutes: settings?.lockout_duration_minutes ?? 30,
        session_lifetime_hours: settings?.session_lifetime_hours ?? 24,
        detect_impossible_travel: settings?.detect_impossible_travel ?? true,
        impossible_travel_speed_kmh: settings?.impossible_travel_speed_kmh ?? 800,
        alert_on_new_device: settings?.alert_on_new_device ?? true,
        alert_on_suspicious_login: settings?.alert_on_suspicious_login ?? true,
        email_alerts_enabled: settings?.email_alerts_enabled ?? true,
        alert_emails: settings?.alert_emails ?? '',
        slack_webhook_url: settings?.slack_webhook_url ?? '',
    });

    // IP form states
    const [newAllowedIp, setNewAllowedIp] = useState('');
    const [newAllowedLabel, setNewAllowedLabel] = useState('');

    const [newBlockedIp, setNewBlockedIp] = useState('');
    const [newBlockedReason, setNewBlockedReason] = useState('');
    const [newBlockedHours, setNewBlockedHours] = useState('24');

    const handleSaveSettings = (e: React.FormEvent) => {
        e.preventDefault();
        router.post('/admin/m/modrixcommerce/login-monitoring/policies/settings', form as any);
    };

    const handleAddAllowedIp = (e: React.FormEvent) => {
        e.preventDefault();
        if (!newAllowedIp) return;
        router.post('/admin/m/modrixcommerce/login-monitoring/policies/allowed-ips', {
            ip_address: newAllowedIp,
            label: newAllowedLabel,
        }, {
            onSuccess: () => {
                setNewAllowedIp('');
                setNewAllowedLabel('');
            },
        });
    };

    const handleDeleteAllowedIp = (id: number) => {
        if (confirm('Remove this IP from the trusted allowlist?')) {
            router.delete(`/admin/m/modrixcommerce/login-monitoring/policies/allowed-ips/${id}`);
        }
    };

    const handleAddBlockedIp = (e: React.FormEvent) => {
        e.preventDefault();
        if (!newBlockedIp) return;
        router.post('/admin/m/modrixcommerce/login-monitoring/policies/blocked-ips', {
            ip_address: newBlockedIp,
            reason: newBlockedReason,
            duration_hours: parseInt(newBlockedHours, 10) || null,
        }, {
            onSuccess: () => {
                setNewBlockedIp('');
                setNewBlockedReason('');
            },
        });
    };

    const handleDeleteBlockedIp = (id: number) => {
        if (confirm('Unblock this IP address?')) {
            router.delete(`/admin/m/modrixcommerce/login-monitoring/policies/blocked-ips/${id}`);
        }
    };

    return (
        <AdminPage>
            <Head title="Access & Security Policies - Login Monitoring" />

            <PageHeader
                title="Access Policies & Defense Rules"
                description="Configure account lockout thresholds, impossible travel parameters, IP access lists, and incident alert hooks."
            />

            {/* Threshold & Behavior Policies Form */}
            <form onSubmit={handleSaveSettings} className="mb-8">
                <Card className="border border-border/60 shadow-sm mb-6">
                    <CardContent className="p-6">
                        <div className="flex items-center justify-between border-b border-border/40 pb-4 mb-6">
                            <div>
                                <h3 className="font-semibold text-base flex items-center gap-2">
                                    <Lock className="h-4 w-4 text-emerald-600" />
                                    Brute-Force & Lockout Thresholds
                                </h3>
                                <p className="text-xs text-muted-foreground">
                                    Defend store credentials against distributed password guessing and credential stuffing.
                                </p>
                            </div>
                            <Button type="submit" size="sm" className="bg-emerald-600 hover:bg-emerald-700 text-white gap-1.5 text-xs">
                                <Save className="h-3.5 w-3.5" /> Save Policies
                            </Button>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div className="space-y-1.5">
                                <label className="text-xs font-semibold text-foreground">Max Failed Attempts Before Lockout</label>
                                <Input
                                    type="number"
                                    min={2}
                                    max={20}
                                    value={form.max_failed_attempts}
                                    onChange={(e) => setForm({ ...form, max_failed_attempts: parseInt(e.target.value, 10) || 5 })}
                                    className="h-9 text-xs"
                                />
                                <p className="text-[11px] text-muted-foreground">Threshold of consecutive login failures.</p>
                            </div>

                            <div className="space-y-1.5">
                                <label className="text-xs font-semibold text-foreground">Lockout Duration (Minutes)</label>
                                <Input
                                    type="number"
                                    min={5}
                                    max={1440}
                                    value={form.lockout_duration_minutes}
                                    onChange={(e) => setForm({ ...form, lockout_duration_minutes: parseInt(e.target.value, 10) || 30 })}
                                    className="h-9 text-xs"
                                />
                                <p className="text-[11px] text-muted-foreground">How long the account or IP remains locked.</p>
                            </div>

                            <div className="space-y-1.5">
                                <label className="text-xs font-semibold text-foreground">Max Session Lifetime (Hours)</label>
                                <Input
                                    type="number"
                                    min={1}
                                    max={168}
                                    value={form.session_lifetime_hours}
                                    onChange={(e) => setForm({ ...form, session_lifetime_hours: parseInt(e.target.value, 10) || 24 })}
                                    className="h-9 text-xs"
                                />
                                <p className="text-[11px] text-muted-foreground">Inactivity duration before requiring re-authentication.</p>
                            </div>
                        </div>

                        {/* Impossible Travel & Anomaly Rules */}
                        <div className="border-t border-border/40 pt-6 mt-6">
                            <h4 className="font-semibold text-sm mb-4 flex items-center gap-2">
                                <Globe className="h-4 w-4 text-blue-500" /> Anomaly & Behavioral Detection
                            </h4>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div className="flex items-start gap-3 bg-muted/30 p-4 rounded-xl border border-border/40">
                                    <input
                                        type="checkbox"
                                        id="detect_travel"
                                        checked={form.detect_impossible_travel}
                                        onChange={(e) => setForm({ ...form, detect_impossible_travel: e.target.checked })}
                                        className="mt-1 h-4 w-4 text-emerald-600 rounded"
                                    />
                                    <div>
                                        <label htmlFor="detect_travel" className="font-semibold text-xs text-foreground cursor-pointer">
                                            Detect Impossible Travel Scenarios
                                        </label>
                                        <p className="text-[11px] text-muted-foreground mt-0.5">
                                            Flags consecutive logins that cannot physically be reached within the elapsed time between locations.
                                        </p>
                                    </div>
                                </div>

                                <div className="space-y-1.5">
                                    <label className="text-xs font-semibold text-foreground">Max Travel Speed Threshold (km/h)</label>
                                    <Input
                                        type="number"
                                        min={300}
                                        max={1500}
                                        value={form.impossible_travel_speed_kmh}
                                        onChange={(e) => setForm({ ...form, impossible_travel_speed_kmh: parseInt(e.target.value, 10) || 800 })}
                                        className="h-9 text-xs"
                                    />
                                    <p className="text-[11px] text-muted-foreground">Logins exceeding commercial airline speed are flagged.</p>
                                </div>
                            </div>
                        </div>

                        {/* Alert Notifications */}
                        <div className="border-t border-border/40 pt-6 mt-6">
                            <h4 className="font-semibold text-sm mb-4 flex items-center gap-2">
                                <Bell className="h-4 w-4 text-amber-500" /> Notifications & Webhooks
                            </h4>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div className="space-y-1.5">
                                    <label className="text-xs font-semibold text-foreground">Alert Email Recipients (comma-separated)</label>
                                    <Input
                                        type="text"
                                        placeholder="security@yourstore.com, admin@yourstore.com"
                                        value={form.alert_emails}
                                        onChange={(e) => setForm({ ...form, alert_emails: e.target.value })}
                                        className="h-9 text-xs"
                                    />
                                    <p className="text-[11px] text-muted-foreground">Receives instant alerts on brute force or credential stuffing.</p>
                                </div>

                                <div className="space-y-1.5">
                                    <label className="text-xs font-semibold text-foreground">Slack Incoming Webhook URL</label>
                                    <Input
                                        type="text"
                                        placeholder="https://hooks.slack.com/services/..."
                                        value={form.slack_webhook_url}
                                        onChange={(e) => setForm({ ...form, slack_webhook_url: e.target.value })}
                                        className="h-9 text-xs font-mono"
                                    />
                                    <p className="text-[11px] text-muted-foreground">Post real-time incident warnings into your security channel.</p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </form>

            {/* IP Allowlist & Blocklist Grids */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* Allowed IPs Card */}
                <Card className="border border-border/60 shadow-sm">
                    <CardContent className="p-6">
                        <div className="flex items-center justify-between mb-4">
                            <div>
                                <h3 className="font-semibold text-base flex items-center gap-2">
                                    <UserCheck className="h-4 w-4 text-emerald-600" />
                                    Trusted IP Allowlist
                                </h3>
                                <p className="text-xs text-muted-foreground">IP addresses exempt from brute force lockout rules.</p>
                            </div>
                        </div>

                        <form onSubmit={handleAddAllowedIp} className="flex gap-2 mb-4">
                            <Input
                                placeholder="IP address (e.g. 192.168.1.1)"
                                value={newAllowedIp}
                                onChange={(e) => setNewAllowedIp(e.target.value)}
                                className="h-8 text-xs font-mono"
                            />
                            <Input
                                placeholder="Label (e.g. Office HQ)"
                                value={newAllowedLabel}
                                onChange={(e) => setNewAllowedLabel(e.target.value)}
                                className="h-8 text-xs max-w-[150px]"
                            />
                            <Button type="submit" size="sm" className="h-8 text-xs bg-emerald-600 hover:bg-emerald-700 text-white shrink-0">
                                <Plus className="h-3.5 w-3.5 mr-1" /> Add
                            </Button>
                        </form>

                        <div className="overflow-x-auto">
                            <table className="w-full text-xs">
                                <thead>
                                    <tr className="border-b border-border/60 text-muted-foreground text-left">
                                        <th className="pb-2 font-medium">IP Address</th>
                                        <th className="pb-2 font-medium">Label</th>
                                        <th className="pb-2 font-medium text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border/30">
                                    {allowedIps.length === 0 ? (
                                        <tr>
                                            <td colSpan={3} className="py-6 text-center text-muted-foreground">
                                                No IPs currently whitelisted.
                                            </td>
                                        </tr>
                                    ) : (
                                        allowedIps.map((ip) => (
                                            <tr key={ip.id} className="hover:bg-muted/30 transition">
                                                <td className="py-2.5 font-mono font-medium text-foreground">{ip.ip_address}</td>
                                                <td className="py-2.5 text-muted-foreground">{ip.label || 'Trusted'}</td>
                                                <td className="py-2.5 text-right">
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() => handleDeleteAllowedIp(ip.id)}
                                                        className="h-6 w-6 p-0 text-rose-600 hover:bg-rose-500/10"
                                                    >
                                                        <Trash2 className="h-3.5 w-3.5" />
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

                {/* Blocked IPs Card */}
                <Card className="border border-border/60 shadow-sm">
                    <CardContent className="p-6">
                        <div className="flex items-center justify-between mb-4">
                            <div>
                                <h3 className="font-semibold text-base flex items-center gap-2">
                                    <Ban className="h-4 w-4 text-rose-500" />
                                    Blocked IP Addresses
                                </h3>
                                <p className="text-xs text-muted-foreground">Deny authentication attempts from blacklisted IPs.</p>
                            </div>
                        </div>

                        <form onSubmit={handleAddBlockedIp} className="flex gap-2 mb-4">
                            <Input
                                placeholder="IP address (e.g. 45.33.32.156)"
                                value={newBlockedIp}
                                onChange={(e) => setNewBlockedIp(e.target.value)}
                                className="h-8 text-xs font-mono"
                            />
                            <Input
                                placeholder="Reason (e.g. Brute force)"
                                value={newBlockedReason}
                                onChange={(e) => setNewBlockedReason(e.target.value)}
                                className="h-8 text-xs max-w-[150px]"
                            />
                            <Button type="submit" size="sm" variant="destructive" className="h-8 text-xs shrink-0">
                                <Plus className="h-3.5 w-3.5 mr-1" /> Block
                            </Button>
                        </form>

                        <div className="overflow-x-auto">
                            <table className="w-full text-xs">
                                <thead>
                                    <tr className="border-b border-border/60 text-muted-foreground text-left">
                                        <th className="pb-2 font-medium">IP Address</th>
                                        <th className="pb-2 font-medium">Reason</th>
                                        <th className="pb-2 font-medium text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border/30">
                                    {blockedIps.length === 0 ? (
                                        <tr>
                                            <td colSpan={3} className="py-6 text-center text-muted-foreground">
                                                No IPs currently blocked.
                                            </td>
                                        </tr>
                                    ) : (
                                        blockedIps.map((ip) => (
                                            <tr key={ip.id} className="hover:bg-muted/30 transition">
                                                <td className="py-2.5 font-mono font-medium text-foreground">{ip.ip_address}</td>
                                                <td className="py-2.5 text-muted-foreground truncate max-w-[150px]">{ip.reason || 'Malicious'}</td>
                                                <td className="py-2.5 text-right">
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() => handleDeleteBlockedIp(ip.id)}
                                                        className="h-6 w-6 p-0 text-emerald-600 hover:bg-emerald-500/10"
                                                    >
                                                        <Trash2 className="h-3.5 w-3.5" />
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
            </div>
        </AdminPage>
    );
}
