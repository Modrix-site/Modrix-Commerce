import { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import {
    Ban,
    Check,
    Globe,
    Plus,
    Search,
    ShieldCheck,
    Trash2,
    X,
} from 'lucide-react';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type Props = {
    tab: string;
    blockedIps: {
        data: Array<{
            id: number;
            ip_address: string;
            cidr_mask: number | null;
            reason: string;
            attack_type: string;
            threat_level: string;
            hit_count: number;
            expires_at: string | null;
            created_at: string;
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    allowedIps: {
        data: Array<{
            id: number;
            ip_address: string;
            label: string;
            description: string | null;
            is_verified_crawler: boolean;
            created_at: string;
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    blockedCountries: string[];
    countriesList: Array<{ code: string; name: string }>;
    blockCloudAsn: boolean;
};

export default function IpManagement({
    tab = 'blacklist',
    blockedIps,
    allowedIps,
    blockedCountries = [],
    countriesList = [],
    blockCloudAsn: _blockCloudAsn = false,
}: Props) {
    const [activeTab, setActiveTab] = useState(tab);
    const [showAddBlocked, setShowAddBlocked] = useState(false);
    const [showAddAllowed, setShowAddAllowed] = useState(false);
    const [selectedCountries, setSelectedCountries] =
        useState<string[]>(blockedCountries);
    const [countrySearch, setCountrySearch] = useState('');

    const blockedForm = useForm({
        ip_address: '',
        reason: '',
        attack_type: 'manual_block',
        expires_days: 30,
    });

    const allowedForm = useForm({
        ip_address: '',
        label: '',
        description: '',
        is_verified_crawler: false,
    });

    const handleAddBlocked = (e: React.FormEvent) => {
        e.preventDefault();
        blockedForm.post('/admin/m/modrixcommerce/bot-protection/ips/blocked', {
            onSuccess: () => {
                setShowAddBlocked(false);
                blockedForm.reset();
            },
        });
    };

    const handleAddAllowed = (e: React.FormEvent) => {
        e.preventDefault();
        allowedForm.post('/admin/m/modrixcommerce/bot-protection/ips/allowed', {
            onSuccess: () => {
                setShowAddAllowed(false);
                allowedForm.reset();
            },
        });
    };

    const toggleCountry = (code: string) => {
        const updated = selectedCountries.includes(code)
            ? selectedCountries.filter((c) => c !== code)
            : [...selectedCountries, code];
        setSelectedCountries(updated);
    };

    const saveCountries = () => {
        router.post('/admin/m/modrixcommerce/bot-protection/ips/countries', {
            countries: selectedCountries,
        });
    };

    const filteredCountries = countriesList.filter(
        (c) =>
            c.name.toLowerCase().includes(countrySearch.toLowerCase()) ||
            c.code.toLowerCase().includes(countrySearch.toLowerCase()),
    );

    return (
        <AdminPage>
            <Head title="Bot Protection - IP Management" />

            <PageHeader
                title="IP & Country Management"
                description="Manage global IP blacklists, trusted whitelist exceptions, and geographic access restrictions."
                actions={
                    <div className="flex items-center gap-2">
                        {activeTab === 'blacklist' && (
                            <Button
                                onClick={() => setShowAddBlocked(true)}
                                className="gap-2"
                            >
                                <Plus className="h-4 w-4" /> Add IP to Blacklist
                            </Button>
                        )}
                        {activeTab === 'whitelist' && (
                            <Button
                                onClick={() => setShowAddAllowed(true)}
                                className="gap-2"
                            >
                                <Plus className="h-4 w-4" /> Add IP to Whitelist
                            </Button>
                        )}
                        {activeTab === 'countries' && (
                            <Button onClick={saveCountries} className="gap-2">
                                <Check className="h-4 w-4" /> Save Country
                                Restrictions
                            </Button>
                        )}
                    </div>
                }
            />

            {/* Navigation Tabs */}
            <div className="mb-6 flex items-center gap-2 border-b border-border/60 pb-3">
                <Button
                    variant={activeTab === 'blacklist' ? 'default' : 'ghost'}
                    size="sm"
                    className="gap-2 text-xs"
                    onClick={() => setActiveTab('blacklist')}
                >
                    <Ban className="h-4 w-4" /> Blacklist (
                    {blockedIps?.data?.length || 0})
                </Button>
                <Button
                    variant={activeTab === 'whitelist' ? 'default' : 'ghost'}
                    size="sm"
                    className="gap-2 text-xs"
                    onClick={() => setActiveTab('whitelist')}
                >
                    <ShieldCheck className="h-4 w-4" /> Whitelist (
                    {allowedIps?.data?.length || 0})
                </Button>
                <Button
                    variant={activeTab === 'countries' ? 'default' : 'ghost'}
                    size="sm"
                    className="gap-2 text-xs"
                    onClick={() => setActiveTab('countries')}
                >
                    <Globe className="h-4 w-4" /> Country Geofencing (
                    {selectedCountries.length} Blocked)
                </Button>
            </div>

            {/* TAB 1: BLACKLIST */}
            {activeTab === 'blacklist' && (
                <Card className="border border-border/60 shadow-sm">
                    <CardContent className="p-6">
                        <div className="overflow-x-auto">
                            <table className="w-full text-xs">
                                <thead>
                                    <tr className="border-b border-border/60 text-left text-muted-foreground">
                                        <th className="pb-3 font-medium">
                                            IP / CIDR
                                        </th>
                                        <th className="pb-3 font-medium">
                                            Block Reason
                                        </th>
                                        <th className="pb-3 font-medium">
                                            Vector
                                        </th>
                                        <th className="pb-3 text-right font-medium">
                                            Blocked Hits
                                        </th>
                                        <th className="pb-3 font-medium">
                                            Expires
                                        </th>
                                        <th className="pb-3 text-right font-medium">
                                            Action
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border/30">
                                    {!blockedIps?.data ||
                                    blockedIps.data.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={6}
                                                className="py-10 text-center text-muted-foreground"
                                            >
                                                No IPs currently blacklisted.
                                            </td>
                                        </tr>
                                    ) : (
                                        blockedIps.data.map((item) => (
                                            <tr
                                                key={item.id}
                                                className="transition hover:bg-muted/30"
                                            >
                                                <td className="py-3 font-mono font-bold text-foreground">
                                                    {item.ip_address}
                                                </td>
                                                <td className="py-3 text-muted-foreground">
                                                    {item.reason}
                                                </td>
                                                <td className="py-3">
                                                    <Badge variant="outline">
                                                        {item.attack_type.replace(
                                                            /_/g,
                                                            ' ',
                                                        )}
                                                    </Badge>
                                                </td>
                                                <td className="py-3 text-right font-bold text-rose-600">
                                                    {item.hit_count}
                                                </td>
                                                <td className="py-3 text-muted-foreground">
                                                    {item.expires_at
                                                        ? new Date(
                                                              item.expires_at,
                                                          ).toLocaleDateString()
                                                        : 'Permanent'}
                                                </td>
                                                <td className="py-3 text-right">
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        className="h-7 px-2 text-rose-600 hover:bg-rose-500/10"
                                                        onClick={() =>
                                                            router.delete(
                                                                `/admin/m/modrixcommerce/bot-protection/ips/blocked/${item.id}`,
                                                            )
                                                        }
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
            )}

            {/* TAB 2: WHITELIST */}
            {activeTab === 'whitelist' && (
                <Card className="border border-border/60 shadow-sm">
                    <CardContent className="p-6">
                        <div className="overflow-x-auto">
                            <table className="w-full text-xs">
                                <thead>
                                    <tr className="border-b border-border/60 text-left text-muted-foreground">
                                        <th className="pb-3 font-medium">
                                            IP / CIDR
                                        </th>
                                        <th className="pb-3 font-medium">
                                            Label
                                        </th>
                                        <th className="pb-3 font-medium">
                                            Description
                                        </th>
                                        <th className="pb-3 font-medium">
                                            Verified Crawler
                                        </th>
                                        <th className="pb-3 text-right font-medium">
                                            Action
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border/30">
                                    {!allowedIps?.data ||
                                    allowedIps.data.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={5}
                                                className="py-10 text-center text-muted-foreground"
                                            >
                                                No IPs currently whitelisted.
                                            </td>
                                        </tr>
                                    ) : (
                                        allowedIps.data.map((item) => (
                                            <tr
                                                key={item.id}
                                                className="transition hover:bg-muted/30"
                                            >
                                                <td className="py-3 font-mono font-bold text-foreground">
                                                    {item.ip_address}
                                                </td>
                                                <td className="py-3 font-medium text-foreground">
                                                    {item.label}
                                                </td>
                                                <td className="py-3 text-muted-foreground">
                                                    {item.description || '—'}
                                                </td>
                                                <td className="py-3">
                                                    {item.is_verified_crawler ? (
                                                        <Badge className="bg-emerald-500/15 text-emerald-600">
                                                            Yes
                                                        </Badge>
                                                    ) : (
                                                        <Badge variant="outline">
                                                            No
                                                        </Badge>
                                                    )}
                                                </td>
                                                <td className="py-3 text-right">
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        className="h-7 px-2 text-rose-600 hover:bg-rose-500/10"
                                                        onClick={() =>
                                                            router.delete(
                                                                `/admin/m/modrixcommerce/bot-protection/ips/allowed/${item.id}`,
                                                            )
                                                        }
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
            )}

            {/* TAB 3: COUNTRY RESTRICTIONS */}
            {activeTab === 'countries' && (
                <Card className="border border-border/60 shadow-sm">
                    <CardContent className="p-6">
                        <div className="mb-4 flex items-center justify-between gap-4">
                            <div className="relative w-full max-w-sm">
                                <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    placeholder="Search countries..."
                                    className="h-9 pl-9 text-xs"
                                    value={countrySearch}
                                    onChange={(e) =>
                                        setCountrySearch(e.target.value)
                                    }
                                />
                            </div>
                            <Button
                                size="sm"
                                onClick={saveCountries}
                                className="gap-2"
                            >
                                <Check className="h-4 w-4" /> Save (
                                {selectedCountries.length} Blocked)
                            </Button>
                        </div>

                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            {filteredCountries.map((c) => {
                                const isBlocked = selectedCountries.includes(
                                    c.code,
                                );
                                return (
                                    <div
                                        key={c.code}
                                        onClick={() => toggleCountry(c.code)}
                                        className={`flex cursor-pointer items-center justify-between rounded-lg border p-3 transition select-none ${
                                            isBlocked
                                                ? 'border-rose-500/40 bg-rose-500/10 text-rose-900 dark:text-rose-200'
                                                : 'border-border/60 bg-card text-foreground hover:bg-muted/40'
                                        }`}
                                    >
                                        <div className="flex items-center gap-2 text-xs">
                                            <span className="rounded bg-muted px-1.5 py-0.5 font-mono text-[10px] font-bold">
                                                {c.code}
                                            </span>
                                            <span className="font-medium">
                                                {c.name}
                                            </span>
                                        </div>
                                        <Badge
                                            variant={
                                                isBlocked
                                                    ? 'destructive'
                                                    : 'outline'
                                            }
                                            className="text-[10px]"
                                        >
                                            {isBlocked ? 'Blocked' : 'Allowed'}
                                        </Badge>
                                    </div>
                                );
                            })}
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* MODAL: ADD BLACKLIST IP */}
            {showAddBlocked && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
                    <div className="relative w-full max-w-md rounded-xl border border-border bg-card p-6 shadow-2xl">
                        <button
                            onClick={() => setShowAddBlocked(false)}
                            className="absolute top-4 right-4 text-muted-foreground hover:text-foreground"
                        >
                            <X className="h-5 w-5" />
                        </button>

                        <h3 className="mb-4 flex items-center gap-2 text-base font-bold text-foreground">
                            <Ban className="h-5 w-5 text-rose-600" /> Add IP to
                            Blacklist
                        </h3>

                        <form
                            onSubmit={handleAddBlocked}
                            className="space-y-4 text-xs"
                        >
                            <div>
                                <label className="mb-1 block font-semibold">
                                    IP Address or CIDR Range
                                </label>
                                <Input
                                    placeholder="e.g. 192.168.1.100 or 10.0.0.0/24"
                                    value={blockedForm.data.ip_address}
                                    onChange={(e) =>
                                        blockedForm.setData(
                                            'ip_address',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                            </div>

                            <div>
                                <label className="mb-1 block font-semibold">
                                    Block Reason
                                </label>
                                <Input
                                    placeholder="e.g. Malicious scraper or credential attack"
                                    value={blockedForm.data.reason}
                                    onChange={(e) =>
                                        blockedForm.setData(
                                            'reason',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                            </div>

                            <div>
                                <label className="mb-1 block font-semibold">
                                    Duration (Days, empty for Permanent)
                                </label>
                                <Input
                                    type="number"
                                    placeholder="30"
                                    value={blockedForm.data.expires_days}
                                    onChange={(e) =>
                                        blockedForm.setData(
                                            'expires_days',
                                            parseInt(e.target.value) || 0,
                                        )
                                    }
                                />
                            </div>

                            <div className="flex justify-end gap-2 border-t border-border pt-3">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setShowAddBlocked(false)}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={blockedForm.processing}
                                >
                                    Blacklist IP
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* MODAL: ADD WHITELIST IP */}
            {showAddAllowed && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
                    <div className="relative w-full max-w-md rounded-xl border border-border bg-card p-6 shadow-2xl">
                        <button
                            onClick={() => setShowAddAllowed(false)}
                            className="absolute top-4 right-4 text-muted-foreground hover:text-foreground"
                        >
                            <X className="h-5 w-5" />
                        </button>

                        <h3 className="mb-4 flex items-center gap-2 text-base font-bold text-foreground">
                            <ShieldCheck className="h-5 w-5 text-emerald-600" />{' '}
                            Add IP to Whitelist
                        </h3>

                        <form
                            onSubmit={handleAddAllowed}
                            className="space-y-4 text-xs"
                        >
                            <div>
                                <label className="mb-1 block font-semibold">
                                    IP Address or CIDR Range
                                </label>
                                <Input
                                    placeholder="e.g. 192.168.1.50"
                                    value={allowedForm.data.ip_address}
                                    onChange={(e) =>
                                        allowedForm.setData(
                                            'ip_address',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                            </div>

                            <div>
                                <label className="mb-1 block font-semibold">
                                    Label / Name
                                </label>
                                <Input
                                    placeholder="e.g. HQ Office, Payment Gateway Webhook, Googlebot"
                                    value={allowedForm.data.label}
                                    onChange={(e) =>
                                        allowedForm.setData(
                                            'label',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                            </div>

                            <div>
                                <label className="mb-1 block font-semibold">
                                    Description (Optional)
                                </label>
                                <Input
                                    placeholder="Additional context or tickets"
                                    value={allowedForm.data.description}
                                    onChange={(e) =>
                                        allowedForm.setData(
                                            'description',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>

                            <div className="flex justify-end gap-2 border-t border-border pt-3">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setShowAddAllowed(false)}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={allowedForm.processing}
                                >
                                    Whitelist IP
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AdminPage>
    );
}
