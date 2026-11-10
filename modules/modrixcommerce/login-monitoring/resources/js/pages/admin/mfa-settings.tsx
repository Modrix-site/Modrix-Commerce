import { Head, router } from '@inertiajs/react';
import {
    CheckCircle2,
    Key,
    Lock,
    Phone,
    RefreshCw,
    RotateCcw,
    Shield,
    ShieldAlert,
    ShieldCheck,
    Smartphone,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type MfaConfigItem = {
    id: number;
    user_id: number;
    user_name: string;
    user_email: string;
    mfa_enabled: boolean;
    mfa_type: string;
    phone_number: string | null;
    last_used_at: string;
};

type PaginatedMfaConfigs = {
    data: MfaConfigItem[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Props = {
    currentPolicy: string;
    stats: {
        total_users: number;
        mfa_enabled_users: number;
        adoption_rate: number;
    };
    mfaConfigs: PaginatedMfaConfigs;
};

export default function MfaSettings({ currentPolicy, stats, mfaConfigs }: Props) {
    const [policy, setPolicy] = useState(currentPolicy || 'risk_based');
    const safeConfigs = Array.isArray(mfaConfigs?.data) ? mfaConfigs.data : [];

    const handleSavePolicy = () => {
        router.post('/admin/m/modrixcommerce/login-monitoring/mfa/policy', {
            mfa_enforcement: policy,
        });
    };

    const handleResetMfa = (userId: number, email: string) => {
        if (confirm(`Reset MFA credentials for ${email}? The user will be required to set up MFA anew upon next login.`)) {
            router.post(`/admin/m/modrixcommerce/login-monitoring/mfa/reset/${userId}`);
        }
    };

    return (
        <AdminPage>
            <Head title="MFA Settings - Login Monitoring" />

            <PageHeader
                title="Multi-Factor Authentication (MFA)"
                description="Enforce two-factor verification, manage authentication channels, and administer user MFA states."
            />

            {/* Adoption Stats */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3 mb-6">
                <Card className="border border-border/60 shadow-sm">
                    <CardContent className="p-4 flex items-center justify-between">
                        <div>
                            <span className="text-xs font-semibold text-muted-foreground uppercase">Total Registered Accounts</span>
                            <div className="mt-1 text-2xl font-bold text-foreground">{stats?.total_users || 0}</div>
                        </div>
                        <div className="rounded-lg bg-blue-500/10 p-2 text-blue-600"><Users className="h-5 w-5" /></div>
                    </CardContent>
                </Card>

                <Card className="border border-border/60 shadow-sm">
                    <CardContent className="p-4 flex items-center justify-between">
                        <div>
                            <span className="text-xs font-semibold text-muted-foreground uppercase">MFA Protected Users</span>
                            <div className="mt-1 text-2xl font-bold text-emerald-600 dark:text-emerald-400">{stats?.mfa_enabled_users || 0}</div>
                        </div>
                        <div className="rounded-lg bg-emerald-500/10 p-2 text-emerald-600"><ShieldCheck className="h-5 w-5" /></div>
                    </CardContent>
                </Card>

                <Card className="border border-border/60 shadow-sm">
                    <CardContent className="p-4 flex items-center justify-between">
                        <div>
                            <span className="text-xs font-semibold text-muted-foreground uppercase">Store Adoption Rate</span>
                            <div className="mt-1 text-2xl font-bold text-purple-600 dark:text-purple-400">{stats?.adoption_rate || 0}%</div>
                        </div>
                        <div className="rounded-lg bg-purple-500/10 p-2 text-purple-600"><Key className="h-5 w-5" /></div>
                    </CardContent>
                </Card>
            </div>

            {/* Global MFA Policy Card */}
            <Card className="mb-6 border border-border/60 shadow-sm">
                <CardContent className="p-6">
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-border/40 mb-6">
                        <div>
                            <h3 className="font-semibold text-base flex items-center gap-2">
                                <Shield className="h-4 w-4 text-emerald-600" />
                                MFA Enforcement Policy
                            </h3>
                            <p className="text-xs text-muted-foreground">
                                Define under what conditions users and staff must complete multi-factor verification.
                            </p>
                        </div>
                        <Button onClick={handleSavePolicy} size="sm" className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs">
                            Save Policy Changes
                        </Button>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        {[
                            {
                                id: 'risk_based',
                                title: 'Risk-Based (Recommended)',
                                desc: 'Enforced only when a login is flagged as high-risk, from an unfamiliar device, or impossible travel.',
                                icon: ShieldAlert,
                            },
                            {
                                id: 'staff_only',
                                title: 'Staff & Admin Only',
                                desc: 'Mandatory for all store administrators and staff members. Optional for customers.',
                                icon: Lock,
                            },
                            {
                                id: 'all_users',
                                title: 'Strict (All Users)',
                                desc: 'Every user and administrator must verify via MFA on every new session.',
                                icon: Key,
                            },
                            {
                                id: 'optional',
                                title: 'Optional',
                                desc: 'Users can voluntarily choose to enable MFA in their profile security settings.',
                                icon: ShieldCheck,
                            },
                        ].map((item) => {
                            const Icon = item.icon;
                            const isSelected = policy === item.id;
                            return (
                                <div
                                    key={item.id}
                                    onClick={() => setPolicy(item.id)}
                                    className={`cursor-pointer rounded-xl border p-4 transition ${
                                        isSelected
                                            ? 'border-emerald-600 bg-emerald-500/10 shadow-sm ring-1 ring-emerald-500'
                                            : 'border-border/60 bg-card hover:bg-muted/40'
                                    }`}
                                >
                                    <div className="flex items-center justify-between mb-2">
                                        <Icon className={`h-5 w-5 ${isSelected ? 'text-emerald-600' : 'text-muted-foreground'}`} />
                                        <input
                                            type="radio"
                                            name="mfa_policy"
                                            checked={isSelected}
                                            onChange={() => setPolicy(item.id)}
                                            className="h-4 w-4 text-emerald-600"
                                        />
                                    </div>
                                    <h4 className="font-semibold text-xs text-foreground mb-1">{item.title}</h4>
                                    <p className="text-[11px] text-muted-foreground leading-relaxed">{item.desc}</p>
                                </div>
                            );
                        })}
                    </div>
                </CardContent>
            </Card>

            {/* Active User MFA Configurations */}
            <Card className="border border-border/60 shadow-sm">
                <CardContent className="p-6">
                    <div className="flex items-center justify-between mb-4">
                        <div>
                            <h3 className="font-semibold text-base flex items-center gap-2">
                                <Smartphone className="h-4 w-4 text-emerald-600" />
                                Configured User MFA Registry
                            </h3>
                            <p className="text-xs text-muted-foreground">List of users with enabled or registered authentication factors.</p>
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-xs">
                            <thead>
                                <tr className="border-b border-border/60 text-muted-foreground text-left">
                                    <th className="pb-2.5 font-medium">Account / User</th>
                                    <th className="pb-2.5 font-medium">MFA Status</th>
                                    <th className="pb-2.5 font-medium">Primary Method</th>
                                    <th className="pb-2.5 font-medium">Backup Phone</th>
                                    <th className="pb-2.5 font-medium">Last Authenticated</th>
                                    <th className="pb-2.5 font-medium text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border/30">
                                {safeConfigs.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="py-8 text-center text-muted-foreground">
                                            No user MFA configurations registered yet.
                                        </td>
                                    </tr>
                                ) : (
                                    safeConfigs.map((cfg) => (
                                        <tr key={cfg.id} className="hover:bg-muted/30 transition">
                                            <td className="py-3">
                                                <div className="font-semibold text-foreground">{cfg.user_name}</div>
                                                <div className="text-[11px] text-muted-foreground">{cfg.user_email}</div>
                                            </td>
                                            <td className="py-3">
                                                {cfg.mfa_enabled ? (
                                                    <Badge className="bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border-emerald-500/20 font-medium">
                                                        Enabled
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="outline" className="text-muted-foreground">
                                                        Disabled
                                                    </Badge>
                                                )}
                                            </td>
                                            <td className="py-3 capitalize font-medium text-foreground">
                                                {cfg.mfa_type || 'Authenticator App'}
                                            </td>
                                            <td className="py-3 font-mono text-muted-foreground">
                                                {cfg.phone_number || 'None'}
                                            </td>
                                            <td className="py-3 text-muted-foreground whitespace-nowrap">
                                                {cfg.last_used_at}
                                            </td>
                                            <td className="py-3 text-right">
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() => handleResetMfa(cfg.user_id, cfg.user_email)}
                                                    className="h-7 px-2 text-xs text-rose-600 hover:text-rose-700 hover:bg-rose-500/10"
                                                >
                                                    <RotateCcw className="h-3 w-3 mr-1" /> Reset MFA
                                                </Button>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {mfaConfigs && mfaConfigs.last_page > 1 && (
                        <div className="flex items-center justify-between p-4 border-t border-border/50 text-xs mt-4">
                            <span className="text-muted-foreground">
                                Page {mfaConfigs.current_page} of {mfaConfigs.last_page} ({mfaConfigs.total} total)
                            </span>
                            <div className="flex items-center gap-2">
                                {mfaConfigs.prev_page_url && (
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => router.get(mfaConfigs.prev_page_url!)}
                                        className="h-7 text-xs"
                                    >
                                        Previous
                                    </Button>
                                )}
                                {mfaConfigs.next_page_url && (
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => router.get(mfaConfigs.next_page_url!)}
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
