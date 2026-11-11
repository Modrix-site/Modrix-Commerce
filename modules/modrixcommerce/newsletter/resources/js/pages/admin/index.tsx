import { Form, Head, router, useForm } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowUpRight,
    CheckCircle2,
    Clock,
    Copy,
    Download,
    Eye,
    FolderPlus,
    Mail,
    Plus,
    RefreshCw,
    Search,
    Send,
    Settings,
    Tag,
    Trash2,
    Users,
    X,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { EmptyState } from '@/components/admin/empty-state';
import { FormField, NativeSelect } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Tabs } from '@/components/admin/tabs';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type SubscriberItem = {
    id: number;
    email: string;
    name: string | null;
    source: string;
    groups: string[];
    consented_at: string;
    unsubscribed_at: string | null;
    update_groups_url: string;
    delete_url: string;
};

type GroupItem = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    color: string;
    subscribers_count: number;
    delete_url: string;
};

type CampaignItem = {
    id: number;
    title: string;
    subject: string;
    preview_text: string | null;
    target_type: 'all' | 'active' | 'customers' | 'leads' | 'group';
    target_group: string | null;
    content_html: string;
    status: 'draft' | 'sending' | 'sent' | 'failed';
    recipients_count: number;
    sent_count: number;
    failed_count: number;
    sent_at: string | null;
    created_at: string;
    last_error: string | null;
    update_url: string;
    delete_url: string;
    send_url: string;
    send_test_url: string;
};

type SmtpConfig = {
    driver: 'smtp' | 'env';
    host: string;
    port: number;
    encryption: 'tls' | 'ssl' | 'none';
    username: string;
    has_password: boolean;
    from_address: string;
    from_name: string;
    reply_to: string;
    is_connected: boolean;
};

type Props = {
    counts: {
        total: number;
        active: number;
        unsubscribed: number;
        customers: number;
        campaigns: number;
        groups: number;
    };
    exportUrl: string;
    filters: {
        status: string;
        segment: string;
        search: string;
        tab: string;
    };
    subscribers: SubscriberItem[];
    groups: GroupItem[];
    campaigns: CampaignItem[];
    smtp: SmtpConfig;
    urls: {
        index: string;
        export: string;
        store_subscriber: string;
        bulk_group: string;
        store_group: string;
        store_campaign: string;
        save_smtp: string;
        test_smtp: string;
    };
};

export default function NewsletterAdmin({
    counts,
    exportUrl,
    filters,
    subscribers,
    groups,
    campaigns,
    smtp,
    urls,
}: Props) {
    // Selection state for bulk operations
    const [selectedIds, setSelectedIds] = useState<number[]>([]);

    // Modals state
    const [addSubscriberOpen, setAddSubscriberOpen] = useState(false);
    const [editGroupsSubscriber, setEditGroupsSubscriber] =
        useState<SubscriberItem | null>(null);
    const [bulkGroupOpen, setBulkGroupOpen] = useState(false);
    const [createGroupOpen, setCreateGroupOpen] = useState(false);
    const [campaignModalOpen, setCampaignModalOpen] = useState(false);
    const [editingCampaign, setEditingCampaign] = useState<CampaignItem | null>(
        null,
    );
    const [previewCampaign, setPreviewCampaign] = useState<CampaignItem | null>(
        null,
    );
    const [testCampaign, setTestCampaign] = useState<CampaignItem | null>(null);
    const [broadcastConfirmCampaign, setBroadcastConfirmCampaign] =
        useState<CampaignItem | null>(null);

    // Filter controls
    const [searchVal, setSearchVal] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || 'all');
    const [segmentFilter, setSegmentFilter] = useState(filters.segment || 'all');

    const handleFilterSubmit = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            urls.index,
            {
                search: searchVal,
                status: statusFilter,
                segment: segmentFilter,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const handleClearFilters = () => {
        setSearchVal('');
        setStatusFilter('all');
        setSegmentFilter('all');
        router.get(urls.index, {}, { preserveState: true, preserveScroll: true });
    };

    // Add subscriber form
    const addSubForm = useForm({
        email: '',
        name: '',
        groups: [] as string[],
    });

    // Edit single subscriber groups form
    const editSubGroupsForm = useForm<{ groups: string[] }>({
        groups: [],
    });

    // Bulk grouping form
    const bulkGroupForm = useForm({
        subscriber_ids: [] as number[],
        group_slug: groups[0]?.slug || '',
        action: 'add' as 'add' | 'remove',
    });

    // Create group form
    const createGroupForm = useForm({
        name: '',
        slug: '',
        description: '',
        color: '#0ea5e9',
    });

    // Campaign form
    const campaignForm = useForm({
        title: '',
        subject: '',
        preview_text: '',
        target_type: 'all' as 'all' | 'active' | 'customers' | 'leads' | 'group',
        target_group: '',
        content_html: '',
    });

    // Test campaign send form
    const testCampaignForm = useForm({
        test_email: smtp.from_address || '',
    });

    // SMTP form
    const smtpForm = useForm({
        driver: smtp.driver || 'smtp',
        host: smtp.host || '',
        port: smtp.port || 587,
        encryption: smtp.encryption || 'tls',
        username: smtp.username || '',
        password: '',
        from_address: smtp.from_address || '',
        from_name: smtp.from_name || '',
        reply_to: smtp.reply_to || '',
    });

    // SMTP test send form
    const smtpTestForm = useForm({
        to: smtp.from_address || '',
    });

    const toggleSelectAll = () => {
        if (selectedIds.length === subscribers.length) {
            setSelectedIds([]);
        } else {
            setSelectedIds(subscribers.map((s) => s.id));
        }
    };

    const toggleSelectOne = (id: number) => {
        setSelectedIds((prev) =>
            prev.includes(id) ? prev.filter((i) => i !== id) : [...prev, id],
        );
    };

    const openEditSubscriberGroups = (sub: SubscriberItem) => {
        setEditGroupsSubscriber(sub);
        editSubGroupsForm.setData({ groups: [...sub.groups] });
    };

    const openCreateCampaignModal = (existing?: CampaignItem) => {
        if (existing) {
            setEditingCampaign(existing);
            campaignForm.setData({
                title: existing.title,
                subject: existing.subject,
                preview_text: existing.preview_text || '',
                target_type: existing.target_type,
                target_group: existing.target_group || '',
                content_html: existing.content_html,
            });
        } else {
            setEditingCampaign(null);
            campaignForm.setData({
                title: '',
                subject: '',
                preview_text: '',
                target_type: 'all',
                target_group: '',
                content_html: `<h2>Exclusive Update for You</h2>\n<p>Hello {{name}},</p>\n<p>We are delighted to share our latest arrivals, special promotions, and hand-picked recommendations curated just for our community.</p>\n<p><a href="{{store_name}}" style="display:inline-block;padding:12px 24px;background-color:#18181b;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;">Explore the Store</a></p>\n<p>Thank you for being part of our journey.<br>Warm regards,<br>The {{store_name}} Team</p>`,
            });
        }
        setCampaignModalOpen(true);
    };

    // Quick templates for campaign editor
    const insertTemplate = (type: 'sale' | 'newsletter' | 'vip') => {
        if (type === 'sale') {
            campaignForm.setData((prev) => ({
                ...prev,
                title: prev.title || 'Flash Sale Announcement',
                subject: prev.subject || '⚡ 48-Hour Weekend Flash Sale Starts Now',
                preview_text:
                    prev.preview_text || 'Save up to 40% on curated bestsellers this weekend.',
                content_html: `<h2>Weekend Flash Sale is Live!</h2>\n<p>Hi {{name}},</p>\n<p>For a strictly limited time, enjoy exclusive savings of up to <strong>40% OFF</strong> across our most popular catalog collections.</p>\n<div style="background:#f4f4f5;border-radius:8px;padding:20px;margin:20px 0;text-align:center;">\n  <h3 style="margin-top:0;">Use Promo Code: FLASH40</h3>\n  <p style="margin-bottom:0;color:#71717a;">Valid on all eligible purchases through Sunday midnight.</p>\n</div>\n<p style="text-align:center;"><a href="{{store_name}}" style="display:inline-block;padding:14px 28px;background-color:#09090b;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;">Shop the Sale Now</a></p>`,
            }));
        } else if (type === 'vip') {
            campaignForm.setData((prev) => ({
                ...prev,
                title: prev.title || 'VIP Member Exclusive',
                subject: prev.subject || '✨ A special thank you for our valued customer',
                preview_text:
                    prev.preview_text || 'Early access to upcoming private collections.',
                content_html: `<h2>Private Early Access for VIPs</h2>\n<p>Dear {{name}},</p>\n<p>As one of our most valued patrons at {{store_name}}, you are receiving priority early access to our unreleased series before the public release next week.</p>\n<p>Your support means the world to our artisans and team. Click below to preview the catalog before stocks sell out:</p>\n<p><a href="{{store_name}}" style="display:inline-block;padding:12px 24px;background-color:#0ea5e9;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;">Access VIP Preview</a></p>`,
            }));
        } else {
            campaignForm.setData((prev) => ({
                ...prev,
                title: prev.title || 'Monthly Edition',
                subject: prev.subject || '🗞️ What is new at {{store_name}} this month',
                preview_text:
                    prev.preview_text || 'Stories, design notes, and new releases.',
                content_html: `<h2>Stories & Discoveries</h2>\n<p>Hello {{name}},</p>\n<p>Welcome to this month's digest from {{store_name}}. We've been working behind the scenes on new designs and improving our customer experience.</p>\n<p>Here are the highlights:</p>\n<ul>\n  <li><strong>Craftsmanship & Sourcing:</strong> How we select each material.</li>\n  <li><strong>Community Spotlight:</strong> Real setups from our customers.</li>\n  <li><strong>Sneak Peek:</strong> What is coming in our next drop.</li>\n</ul>\n<p>Read the complete story on our storefront blog.</p>`,
            }));
        }
    };

    // TAB CONTENTS
    const subscribersTab = (
        <div className="space-y-6">
            {/* Filters & Actions Bar */}
            <div className="flex flex-col gap-4 rounded-xl border bg-card p-4 shadow-xs sm:flex-row sm:items-center sm:justify-between">
                <form
                    onSubmit={handleFilterSubmit}
                    className="flex flex-1 flex-wrap items-center gap-3"
                >
                    <div className="relative min-w-[220px] flex-1 sm:max-w-xs">
                        <Search className="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
                        <Input
                            placeholder="Search by email or name..."
                            value={searchVal}
                            onChange={(e) => setSearchVal(e.target.value)}
                            className="pl-9"
                        />
                    </div>
                    <div className="w-36">
                        <NativeSelect
                            value={statusFilter}
                            onChange={(e) => setStatusFilter(e.target.value)}
                        >
                            <option value="all">All statuses</option>
                            <option value="active">Active only</option>
                            <option value="unsubscribed">Unsubscribed</option>
                        </NativeSelect>
                    </div>
                    <div className="w-44">
                        <NativeSelect
                            value={segmentFilter}
                            onChange={(e) => setSegmentFilter(e.target.value)}
                        >
                            <option value="all">All audiences</option>
                            <option value="customers">Store Customers</option>
                            <option value="leads">Storefront Leads</option>
                            {groups.map((g) => (
                                <option key={g.id} value={g.slug}>
                                    Group: {g.name}
                                </option>
                            ))}
                        </NativeSelect>
                    </div>
                    <Button type="submit" variant="secondary" size="sm">
                        Filter
                    </Button>
                    {(filters.search ||
                        filters.status !== 'all' ||
                        filters.segment !== 'all') && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={handleClearFilters}
                        >
                            Clear
                        </Button>
                    )}
                </form>

                <div className="flex flex-wrap items-center gap-2">
                    {selectedIds.length > 0 && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => {
                                bulkGroupForm.setData('subscriber_ids', selectedIds);
                                setBulkGroupOpen(true);
                            }}
                            className="border-primary/40 text-primary"
                        >
                            <Tag className="mr-1.5 h-3.5 w-3.5" />
                            Tag Selected ({selectedIds.length})
                        </Button>
                    )}
                    <Button
                        size="sm"
                        onClick={() => {
                            addSubForm.reset();
                            setAddSubscriberOpen(true);
                        }}
                    >
                        <Plus className="mr-1.5 h-4 w-4" /> Add Subscriber
                    </Button>
                    <Button variant="outline" size="sm" asChild>
                        <a href={exportUrl}>
                            <Download className="mr-1.5 h-3.5 w-3.5" /> Export CSV
                        </a>
                    </Button>
                </div>
            </div>

            {/* Subscribers Table */}
            {subscribers.length === 0 ? (
                <EmptyState
                    title="No subscribers match your query"
                    description="Subscribers will automatically appear here when customers subscribe on the storefront or complete a purchase."
                />
            ) : (
                <div className="overflow-hidden rounded-xl border bg-card shadow-xs">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b bg-muted/40 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                <tr>
                                    <th className="w-10 px-4 py-3 text-center">
                                        <input
                                            type="checkbox"
                                            checked={
                                                selectedIds.length === subscribers.length &&
                                                subscribers.length > 0
                                            }
                                            onChange={toggleSelectAll}
                                            className="h-4 w-4 rounded border-input"
                                            aria-label="Select all subscribers"
                                        />
                                    </th>
                                    <th className="px-4 py-3">Subscriber</th>
                                    <th className="px-4 py-3">Audience Groups</th>
                                    <th className="px-4 py-3">Source & Consent</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {subscribers.map((s) => {
                                    const isSelected = selectedIds.includes(s.id);
                                    return (
                                        <tr
                                            key={s.id}
                                            className={`transition-colors hover:bg-muted/30 ${
                                                isSelected ? 'bg-muted/40' : ''
                                            }`}
                                        >
                                            <td className="px-4 py-3 text-center">
                                                <input
                                                    type="checkbox"
                                                    checked={isSelected}
                                                    onChange={() => toggleSelectOne(s.id)}
                                                    className="h-4 w-4 rounded border-input"
                                                    aria-label={`Select ${s.email}`}
                                                />
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="font-medium text-foreground">
                                                    {s.email}
                                                </div>
                                                {s.name && (
                                                    <div className="text-xs text-muted-foreground">
                                                        {s.name}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex flex-wrap items-center gap-1.5">
                                                    {s.groups && s.groups.length > 0 ? (
                                                        s.groups.map((slug) => {
                                                            const grp = groups.find(
                                                                (g) => g.slug === slug,
                                                            );
                                                            return (
                                                                <Badge
                                                                    key={slug}
                                                                    variant="secondary"
                                                                    className="text-xs font-normal"
                                                                    style={
                                                                        grp?.color
                                                                            ? {
                                                                                  borderLeft: `3px solid ${grp.color}`,
                                                                              }
                                                                            : undefined
                                                                    }
                                                                >
                                                                    {grp ? grp.name : slug}
                                                                </Badge>
                                                            );
                                                        })
                                                    ) : (
                                                        <span className="text-xs text-muted-foreground italic">
                                                            No groups
                                                        </span>
                                                    )}
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            openEditSubscriberGroups(s)
                                                        }
                                                        className="inline-flex h-5 items-center rounded px-1.5 text-xs text-muted-foreground hover:bg-muted hover:text-foreground"
                                                        title="Assign or remove groups"
                                                    >
                                                        <Plus className="mr-0.5 h-3 w-3" />
                                                        Tag
                                                    </button>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-xs text-muted-foreground">
                                                <div className="capitalize font-medium text-foreground">
                                                    {s.source === 'order'
                                                        ? '🛍️ Customer (Order)'
                                                        : s.source === 'footer'
                                                          ? '📄 Footer form'
                                                          : s.source === 'home'
                                                            ? '🏠 Home page'
                                                            : '👤 Admin registered'}
                                                </div>
                                                <div>Since {s.consented_at}</div>
                                            </td>
                                            <td className="px-4 py-3">
                                                {s.unsubscribed_at ? (
                                                    <Badge
                                                        variant="outline"
                                                        className="border-destructive/30 text-destructive text-xs"
                                                    >
                                                        Unsubscribed {s.unsubscribed_at}
                                                    </Badge>
                                                ) : (
                                                    <Badge
                                                        variant="outline"
                                                        className="border-emerald-500/30 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 text-xs"
                                                    >
                                                        ● Active
                                                    </Badge>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <div className="flex items-center justify-end gap-1">
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() =>
                                                            openEditSubscriberGroups(s)
                                                        }
                                                        className="h-8 px-2 text-xs"
                                                    >
                                                        <Tag className="mr-1 h-3.5 w-3.5" />
                                                        Groups
                                                    </Button>
                                                    <Form
                                                        action={s.delete_url}
                                                        method="delete"
                                                        options={{ preserveScroll: true }}
                                                    >
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            className="h-8 px-2 text-xs text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                            aria-label={`Erase ${s.email}`}
                                                        >
                                                            <Trash2 className="h-3.5 w-3.5" />
                                                        </Button>
                                                    </Form>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                    <div className="flex items-center justify-between border-t bg-muted/20 px-4 py-3 text-xs text-muted-foreground">
                        <div>
                            Showing {subscribers.length} of {counts.total} subscribers
                        </div>
                        <div>Tip: Export carries full GDPR consent proofs and tokens.</div>
                    </div>
                </div>
            )}
        </div>
    );

    // TAB 2: AUDIENCE GROUPS
    const groupsTab = (
        <div className="space-y-6">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 className="text-base font-semibold">Audience Segments & Groups</h3>
                    <p className="text-sm text-muted-foreground">
                        Group customers and leads into targeted segments to run focused marketing
                        campaigns.
                    </p>
                </div>
                <Button
                    size="sm"
                    onClick={() => {
                        createGroupForm.reset();
                        setCreateGroupOpen(true);
                    }}
                >
                    <FolderPlus className="mr-1.5 h-4 w-4" /> Create Custom Group
                </Button>
            </div>

            {/* Smart Dynamic Segments */}
            <div className="grid gap-4 sm:grid-cols-3">
                <Card className="border-l-4 border-l-blue-500 py-4 shadow-xs">
                    <CardHeader className="pb-2">
                        <CardTitle className="text-sm font-semibold flex items-center justify-between">
                            <span>All Active Subscribers</span>
                            <Badge variant="secondary">{counts.active}</Badge>
                        </CardTitle>
                        <CardDescription className="text-xs">
                            Dynamic segment including all valid subscribers with active consent.
                        </CardDescription>
                    </CardHeader>
                </Card>

                <Card className="border-l-4 border-l-emerald-500 py-4 shadow-xs">
                    <CardHeader className="pb-2">
                        <CardTitle className="text-sm font-semibold flex items-center justify-between">
                            <span>Store Customers</span>
                            <Badge variant="secondary">{counts.customers}</Badge>
                        </CardTitle>
                        <CardDescription className="text-xs">
                            Subscribers converted directly during checkout and purchase.
                        </CardDescription>
                    </CardHeader>
                </Card>

                <Card className="border-l-4 border-l-purple-500 py-4 shadow-xs">
                    <CardHeader className="pb-2">
                        <CardTitle className="text-sm font-semibold flex items-center justify-between">
                            <span>Storefront Leads</span>
                            <Badge variant="secondary">
                                {Math.max(0, counts.active - counts.customers)}
                            </Badge>
                        </CardTitle>
                        <CardDescription className="text-xs">
                            Visitors who signed up via home banner or footer newsletter blocks.
                        </CardDescription>
                    </CardHeader>
                </Card>
            </div>

            {/* Custom Groups Table */}
            <div className="rounded-xl border bg-card shadow-xs">
                <div className="border-b px-4 py-3">
                    <h4 className="text-sm font-medium">Custom Audience Groups</h4>
                </div>
                {groups.length === 0 ? (
                    <div className="p-8 text-center">
                        <Tag className="mx-auto h-8 w-8 text-muted-foreground/50 mb-2" />
                        <h4 className="text-sm font-medium">No custom groups created yet</h4>
                        <p className="text-xs text-muted-foreground max-w-sm mx-auto mt-1 mb-4">
                            Organize your audience by interest, VIP status, seasonal campaigns, or
                            loyalty tiers.
                        </p>
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => {
                                createGroupForm.reset();
                                setCreateGroupOpen(true);
                            }}
                        >
                            <Plus className="mr-1 h-3.5 w-3.5" /> Create your first group
                        </Button>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b bg-muted/40 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-3">Group Name</th>
                                    <th className="px-4 py-3">Slug Identifier</th>
                                    <th className="px-4 py-3">Description</th>
                                    <th className="px-4 py-3 text-center">Subscribers</th>
                                    <th className="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {groups.map((g) => (
                                    <tr key={g.id} className="hover:bg-muted/30">
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-2">
                                                <span
                                                    className="h-3.5 w-3.5 rounded-full shrink-0 shadow-xs"
                                                    style={{ backgroundColor: g.color }}
                                                />
                                                <span className="font-medium text-foreground">
                                                    {g.name}
                                                </span>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-xs font-mono text-muted-foreground">
                                            {g.slug}
                                        </td>
                                        <td className="px-4 py-3 text-xs text-muted-foreground max-w-xs truncate">
                                            {g.description || '—'}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            <Badge variant="secondary" className="font-normal">
                                                {g.subscribers_count} member
                                                {g.subscribers_count !== 1 ? 's' : ''}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <Form
                                                action={g.delete_url}
                                                method="delete"
                                                options={{ preserveScroll: true }}
                                            >
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    className="h-8 px-2 text-xs text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                    aria-label={`Delete ${g.name}`}
                                                >
                                                    <Trash2 className="h-3.5 w-3.5" />
                                                </Button>
                                            </Form>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </div>
    );

    // TAB 3: CAMPAIGNS
    const campaignsTab = (
        <div className="space-y-6">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 className="text-base font-semibold">Email Campaigns & Broadcasts</h3>
                    <p className="text-sm text-muted-foreground">
                        Compose responsive emails, preview in real time, and broadcast them to targeted
                        customer segments.
                    </p>
                </div>
                <Button size="sm" onClick={() => openCreateCampaignModal()}>
                    <Mail className="mr-1.5 h-4 w-4" /> New Campaign
                </Button>
            </div>

            {campaigns.length === 0 ? (
                <EmptyState
                    title="No marketing campaigns yet"
                    description="Create your first email campaign to announce product launches, seasonal flash sales, or news updates."
                    action={
                        <Button size="sm" onClick={() => openCreateCampaignModal()}>
                            <Plus className="mr-1.5 h-4 w-4" /> Create Campaign
                        </Button>
                    }
                />
            ) : (
                <div className="grid gap-4">
                    {campaigns.map((c) => {
                        const targetLabel =
                            c.target_type === 'all'
                                ? 'All Active Subscribers'
                                : c.target_type === 'customers'
                                  ? 'Customers Only'
                                  : c.target_type === 'leads'
                                    ? 'Storefront Sign-ups'
                                    : `Group: ${groups.find((g) => g.slug === c.target_group)?.name || c.target_group}`;

                        return (
                            <Card key={c.id} className="py-4 shadow-xs hover:border-primary/30 transition-colors">
                                <CardHeader className="pb-3">
                                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <div className="flex items-center gap-2">
                                                <CardTitle className="text-base">
                                                    {c.title}
                                                </CardTitle>
                                                {c.status === 'draft' && (
                                                    <Badge variant="secondary" className="text-xs">
                                                        Draft
                                                    </Badge>
                                                )}
                                                {c.status === 'sent' && (
                                                    <Badge
                                                        variant="outline"
                                                        className="border-emerald-500/30 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 text-xs"
                                                    >
                                                        <CheckCircle2 className="mr-1 h-3 w-3 inline" /> Sent
                                                    </Badge>
                                                )}
                                                {c.status === 'failed' && (
                                                    <Badge
                                                        variant="outline"
                                                        className="border-destructive/30 bg-destructive/10 text-destructive text-xs"
                                                    >
                                                        <XCircle className="mr-1 h-3 w-3 inline" /> Failed
                                                    </Badge>
                                                )}
                                            </div>
                                            <CardDescription className="text-xs mt-1">
                                                Subject: <strong className="text-foreground">{c.subject}</strong>
                                                {c.preview_text && (
                                                    <span className="text-muted-foreground ml-2">
                                                        · {c.preview_text}
                                                    </span>
                                                )}
                                            </CardDescription>
                                        </div>

                                        <div className="flex items-center gap-2 shrink-0">
                                            <Badge variant="outline" className="text-xs font-normal">
                                                Target: {targetLabel}
                                            </Badge>
                                        </div>
                                    </div>
                                </CardHeader>

                                <CardContent className="pb-3 text-xs text-muted-foreground">
                                    <div className="flex flex-wrap items-center gap-4">
                                        {c.status === 'sent' && (
                                            <>
                                                <div>
                                                    Sent to: <strong className="text-foreground">{c.sent_count}</strong> recipient(s)
                                                </div>
                                                {c.failed_count > 0 && (
                                                    <div className="text-destructive">
                                                        Failed: {c.failed_count}
                                                    </div>
                                                )}
                                                <div>
                                                    Sent on: {c.sent_at ? new Date(c.sent_at).toLocaleString() : '—'}
                                                </div>
                                            </>
                                        )}
                                        {c.status === 'draft' && (
                                            <div>Created on {new Date(c.created_at).toLocaleDateString()}</div>
                                        )}
                                        {c.last_error && (
                                            <div className="text-destructive flex items-center gap-1">
                                                <AlertCircle className="h-3.5 w-3.5" />
                                                <span>{c.last_error}</span>
                                            </div>
                                        )}
                                    </div>
                                </CardContent>

                                <CardFooter className="pt-2 border-t flex flex-wrap items-center justify-between gap-2">
                                    <div className="flex items-center gap-2">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => setPreviewCampaign(c)}
                                            className="h-8 text-xs"
                                        >
                                            <Eye className="mr-1.5 h-3.5 w-3.5" /> Preview HTML
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => {
                                                setTestCampaign(c);
                                                testCampaignForm.setData(
                                                    'test_email',
                                                    smtp.from_address || '',
                                                );
                                            }}
                                            className="h-8 text-xs"
                                        >
                                            <Send className="mr-1.5 h-3.5 w-3.5" /> Send Test
                                        </Button>
                                    </div>

                                    <div className="flex items-center gap-2">
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() => openCreateCampaignModal(c)}
                                            className="h-8 text-xs"
                                        >
                                            Edit
                                        </Button>
                                        <Button
                                            size="sm"
                                            onClick={() => setBroadcastConfirmCampaign(c)}
                                            className="h-8 text-xs bg-primary text-primary-foreground"
                                        >
                                            <Send className="mr-1.5 h-3.5 w-3.5" />
                                            {c.status === 'sent' ? 'Resend Broadcast' : 'Broadcast Now'}
                                        </Button>
                                        <Form
                                            action={c.delete_url}
                                            method="delete"
                                            options={{ preserveScroll: true }}
                                        >
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                className="h-8 px-2 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                aria-label={`Delete campaign ${c.title}`}
                                            >
                                                <Trash2 className="h-3.5 w-3.5" />
                                            </Button>
                                        </Form>
                                    </div>
                                </CardFooter>
                            </Card>
                        );
                    })}
                </div>
            )}
        </div>
    );

    // TAB 4: SMTP CONFIGURATION
    const smtpTab = (
        <div className="space-y-6 max-w-4xl">
            {/* Status overview */}
            <Card className="py-4 shadow-xs">
                <CardHeader className="pb-3">
                    <div className="flex items-center justify-between">
                        <div className="space-y-1">
                            <CardTitle className="text-base flex items-center gap-2">
                                <span>App Core SMTP Connection</span>
                                {smtp.is_connected ? (
                                    <Badge
                                        variant="outline"
                                        className="border-emerald-500/30 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 text-xs font-normal"
                                    >
                                        ● Connected ({smtp.host}:{smtp.port})
                                    </Badge>
                                ) : (
                                    <Badge variant="secondary" className="text-xs font-normal">
                                        Server Default Mailer
                                    </Badge>
                                )}
                            </CardTitle>
                            <CardDescription className="text-xs">
                                Modrix Commerce connects directly to the platform's core SMTP service
                                (<code>App\Core\Settings\MailSettings</code>) for reliable transactional and marketing delivery.
                            </CardDescription>
                        </div>
                    </div>
                </CardHeader>
            </Card>

            {/* SMTP Setting Form */}
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    smtpForm.post(urls.save_smtp, { preserveScroll: true });
                }}
            >
                <Card className="py-4 shadow-xs">
                    <CardHeader>
                        <CardTitle className="text-base">SMTP Server Configuration</CardTitle>
                        <CardDescription className="text-xs">
                            Enter your mail provider's SMTP credentials (e.g. Amazon SES, Postmark, Mailgun, SendGrid, Mailpit, or custom SMTP).
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField
                                id="driver"
                                label="Mail Driver"
                                error={smtpForm.errors.driver}
                            >
                                <NativeSelect
                                    id="driver"
                                    value={smtpForm.data.driver}
                                    onChange={(e) =>
                                        smtpForm.setData('driver', e.target.value as 'smtp' | 'env')
                                    }
                                >
                                    <option value="smtp">Custom SMTP Server (Recommended)</option>
                                    <option value="env">Server Default (.env)</option>
                                </NativeSelect>
                            </FormField>

                            <FormField
                                id="encryption"
                                label="Encryption"
                                error={smtpForm.errors.encryption}
                            >
                                <NativeSelect
                                    id="encryption"
                                    value={smtpForm.data.encryption}
                                    onChange={(e) =>
                                        smtpForm.setData(
                                            'encryption',
                                            e.target.value as 'tls' | 'ssl' | 'none',
                                        )
                                    }
                                >
                                    <option value="tls">TLS (STARTTLS - Port 587)</option>
                                    <option value="ssl">SSL (Port 465)</option>
                                    <option value="none">None (Local / Development)</option>
                                </NativeSelect>
                            </FormField>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-3">
                            <div className="sm:col-span-2">
                                <FormField
                                    id="host"
                                    label="SMTP Host"
                                    error={smtpForm.errors.host}
                                    hint="e.g. smtp.mailgun.org, smtp.sendgrid.net, 127.0.0.1"
                                >
                                    <Input
                                        id="host"
                                        value={smtpForm.data.host}
                                        onChange={(e) => smtpForm.setData('host', e.target.value)}
                                        placeholder="smtp.example.com"
                                    />
                                </FormField>
                            </div>

                            <div>
                                <FormField
                                    id="port"
                                    label="Port"
                                    error={smtpForm.errors.port ? String(smtpForm.errors.port) : undefined}
                                >
                                    <Input
                                        id="port"
                                        type="number"
                                        value={smtpForm.data.port}
                                        onChange={(e) =>
                                            smtpForm.setData('port', Number(e.target.value))
                                        }
                                    />
                                </FormField>
                            </div>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField
                                id="username"
                                label="Username / API Key"
                                error={smtpForm.errors.username}
                            >
                                <Input
                                    id="username"
                                    autoComplete="off"
                                    value={smtpForm.data.username}
                                    onChange={(e) => smtpForm.setData('username', e.target.value)}
                                    placeholder="apikey or user@example.com"
                                />
                            </FormField>

                            <FormField
                                id="password"
                                label="Password"
                                error={smtpForm.errors.password}
                                hint={
                                    smtp.has_password
                                        ? 'Password saved in encrypted store. Leave empty to keep existing password.'
                                        : 'Required by authenticated SMTP servers.'
                                }
                            >
                                <Input
                                    id="password"
                                    type="password"
                                    autoComplete="new-password"
                                    value={smtpForm.data.password}
                                    onChange={(e) => smtpForm.setData('password', e.target.value)}
                                    placeholder={smtp.has_password ? '••••••••••••' : ''}
                                />
                            </FormField>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField
                                id="from_address"
                                label="From Email Address"
                                error={smtpForm.errors.from_address}
                                hint="Sender address appearing in recipient inboxes."
                            >
                                <Input
                                    id="from_address"
                                    type="email"
                                    value={smtpForm.data.from_address}
                                    onChange={(e) =>
                                        smtpForm.setData('from_address', e.target.value)
                                    }
                                    placeholder="newsletter@yourstore.com"
                                />
                            </FormField>

                            <FormField
                                id="from_name"
                                label="From Name"
                                error={smtpForm.errors.from_name}
                                hint="Sender display name."
                            >
                                <Input
                                    id="from_name"
                                    value={smtpForm.data.from_name}
                                    onChange={(e) => smtpForm.setData('from_name', e.target.value)}
                                    placeholder="Your Store Name"
                                />
                            </FormField>
                        </div>

                        <FormField
                            id="reply_to"
                            label="Reply-To Address (Optional)"
                            error={smtpForm.errors.reply_to}
                            hint="Replies to marketing broadcasts will route here."
                        >
                            <Input
                                id="reply_to"
                                type="email"
                                value={smtpForm.data.reply_to}
                                onChange={(e) => smtpForm.setData('reply_to', e.target.value)}
                                placeholder="support@yourstore.com"
                            />
                        </FormField>
                    </CardContent>

                    <CardFooter className="border-t pt-4 flex justify-end">
                        <Button type="submit" disabled={smtpForm.processing}>
                            {smtpForm.processing ? 'Saving...' : 'Save App Core SMTP Settings'}
                        </Button>
                    </CardFooter>
                </Card>
            </form>

            {/* Test Email Card */}
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    smtpTestForm.post(urls.test_smtp, { preserveScroll: true });
                }}
            >
                <Card className="py-4 shadow-xs">
                    <CardHeader>
                        <CardTitle className="text-base">Send SMTP Test Email</CardTitle>
                        <CardDescription className="text-xs">
                            Verify that your App Core SMTP configuration connects cleanly and delivers emails before running campaigns.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-end max-w-md">
                            <div className="flex-1">
                                <FormField
                                    id="test_to"
                                    label="Recipient Email"
                                    error={smtpTestForm.errors.to}
                                >
                                    <Input
                                        id="test_to"
                                        type="email"
                                        value={smtpTestForm.data.to}
                                        onChange={(e) => smtpTestForm.setData('to', e.target.value)}
                                        placeholder="admin@example.com"
                                        required
                                    />
                                </FormField>
                            </div>
                            <Button
                                type="submit"
                                variant="secondary"
                                disabled={smtpTestForm.processing}
                            >
                                <Send className="mr-1.5 h-3.5 w-3.5" />
                                {smtpTestForm.processing ? 'Delivering...' : 'Send Test Email'}
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </form>
        </div>
    );

    return (
        <>
            <Head title="Newsletter & Email Marketing" />
            <AdminPage>
                <PageHeader
                    title="Newsletter & Email Marketing"
                    description="Audience segmentation, email marketing broadcasts, GDPR-compliant consent capture, and App Core SMTP delivery."
                />

                {/* KPI STAT CARDS */}
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-5">
                    <div className="rounded-xl border bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between text-muted-foreground">
                            <span className="text-xs font-medium uppercase tracking-wider">
                                Subscribers
                            </span>
                            <Users className="h-4 w-4" />
                        </div>
                        <div className="mt-2 text-2xl font-bold">{counts.total}</div>
                        <div className="text-xs text-muted-foreground mt-0.5">
                            {counts.active} active
                        </div>
                    </div>

                    <div className="rounded-xl border bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between text-muted-foreground">
                            <span className="text-xs font-medium uppercase tracking-wider">
                                Active Rate
                            </span>
                            <CheckCircle2 className="h-4 w-4 text-emerald-500" />
                        </div>
                        <div className="mt-2 text-2xl font-bold">
                            {counts.total > 0
                                ? Math.round((counts.active / counts.total) * 100)
                                : 100}
                            %
                        </div>
                        <div className="text-xs text-muted-foreground mt-0.5">
                            {counts.unsubscribed} unsubscribed
                        </div>
                    </div>

                    <div className="rounded-xl border bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between text-muted-foreground">
                            <span className="text-xs font-medium uppercase tracking-wider">
                                Store Buyers
                            </span>
                            <Tag className="h-4 w-4 text-blue-500" />
                        </div>
                        <div className="mt-2 text-2xl font-bold">{counts.customers}</div>
                        <div className="text-xs text-muted-foreground mt-0.5">
                            From order checkouts
                        </div>
                    </div>

                    <div className="rounded-xl border bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between text-muted-foreground">
                            <span className="text-xs font-medium uppercase tracking-wider">
                                Groups
                            </span>
                            <FolderPlus className="h-4 w-4 text-purple-500" />
                        </div>
                        <div className="mt-2 text-2xl font-bold">{groups.length}</div>
                        <div className="text-xs text-muted-foreground mt-0.5">
                            Custom segments
                        </div>
                    </div>

                    <div className="rounded-xl border bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between text-muted-foreground">
                            <span className="text-xs font-medium uppercase tracking-wider">
                                Campaigns
                            </span>
                            <Mail className="h-4 w-4 text-amber-500" />
                        </div>
                        <div className="mt-2 text-2xl font-bold">{campaigns.length}</div>
                        <div className="text-xs text-muted-foreground mt-0.5">
                            Broadcasts created
                        </div>
                    </div>
                </div>

                {/* TABS COMPONENT */}
                <Tabs
                    label="Newsletter and marketing tabs"
                    items={[
                        {
                            id: 'subscribers',
                            label: (
                                <span className="flex items-center gap-1.5">
                                    <Users className="h-4 w-4" /> Subscribers ({counts.total})
                                </span>
                            ),
                            content: subscribersTab,
                        },
                        {
                            id: 'groups',
                            label: (
                                <span className="flex items-center gap-1.5">
                                    <Tag className="h-4 w-4" /> Audience Groups ({groups.length})
                                </span>
                            ),
                            content: groupsTab,
                        },
                        {
                            id: 'campaigns',
                            label: (
                                <span className="flex items-center gap-1.5">
                                    <Mail className="h-4 w-4" /> Campaigns ({campaigns.length})
                                </span>
                            ),
                            content: campaignsTab,
                        },
                        {
                            id: 'smtp',
                            label: (
                                <span className="flex items-center gap-1.5">
                                    <Settings className="h-4 w-4" /> SMTP Settings
                                    {smtp.is_connected && (
                                        <span className="h-2 w-2 rounded-full bg-emerald-500" />
                                    )}
                                </span>
                            ),
                            content: smtpTab,
                        },
                    ]}
                />

                {/* MODAL: ADD SUBSCRIBER */}
                <Dialog open={addSubscriberOpen} onOpenChange={setAddSubscriberOpen}>
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>Add Subscriber</DialogTitle>
                            <DialogDescription>
                                Manually register a new subscriber with explicit consent.
                            </DialogDescription>
                        </DialogHeader>
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                addSubForm.post(urls.store_subscriber, {
                                    onSuccess: () => setAddSubscriberOpen(false),
                                });
                            }}
                            className="space-y-4"
                        >
                            <FormField
                                id="new_sub_email"
                                label="Email Address"
                                error={addSubForm.errors.email}
                            >
                                <Input
                                    id="new_sub_email"
                                    type="email"
                                    required
                                    value={addSubForm.data.email}
                                    onChange={(e) => addSubForm.setData('email', e.target.value)}
                                    placeholder="customer@example.com"
                                />
                            </FormField>

                            <FormField
                                id="new_sub_name"
                                label="Subscriber Name (Optional)"
                                error={addSubForm.errors.name}
                            >
                                <Input
                                    id="new_sub_name"
                                    value={addSubForm.data.name}
                                    onChange={(e) => addSubForm.setData('name', e.target.value)}
                                    placeholder="Jane Doe"
                                />
                            </FormField>

                            {groups.length > 0 && (
                                <div className="space-y-2">
                                    <Label>Assign Audience Groups</Label>
                                    <div className="flex flex-wrap gap-2">
                                        {groups.map((g) => {
                                            const checked = addSubForm.data.groups.includes(g.slug);
                                            return (
                                                <button
                                                    key={g.id}
                                                    type="button"
                                                    onClick={() => {
                                                        const cur = addSubForm.data.groups;
                                                        addSubForm.setData(
                                                            'groups',
                                                            checked
                                                                ? cur.filter((x) => x !== g.slug)
                                                                : [...cur, g.slug],
                                                        );
                                                    }}
                                                    className={`rounded-md border px-2.5 py-1 text-xs font-medium transition-colors ${
                                                        checked
                                                            ? 'border-primary bg-primary text-primary-foreground'
                                                            : 'bg-muted/50 text-muted-foreground hover:bg-muted'
                                                    }`}
                                                >
                                                    {g.name}
                                                </button>
                                            );
                                        })}
                                    </div>
                                </div>
                            )}

                            <DialogFooter className="pt-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setAddSubscriberOpen(false)}
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={addSubForm.processing}>
                                    {addSubForm.processing ? 'Saving...' : 'Add Subscriber'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>

                {/* MODAL: EDIT SUBSCRIBER GROUPS */}
                <Dialog
                    open={editGroupsSubscriber !== null}
                    onOpenChange={(open) => !open && setEditGroupsSubscriber(null)}
                >
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>Manage Audience Groups</DialogTitle>
                            <DialogDescription>
                                Select which audience tags apply to{' '}
                                <strong className="text-foreground">
                                    {editGroupsSubscriber?.email}
                                </strong>
                                .
                            </DialogDescription>
                        </DialogHeader>
                        {editGroupsSubscriber && (
                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    editSubGroupsForm.post(
                                        editGroupsSubscriber.update_groups_url,
                                        {
                                            onSuccess: () => setEditGroupsSubscriber(null),
                                        },
                                    );
                                }}
                                className="space-y-4"
                            >
                                {groups.length === 0 ? (
                                    <p className="text-xs text-muted-foreground">
                                        No custom groups created yet. Create groups in the
                                        "Audience Groups" tab.
                                    </p>
                                ) : (
                                    <div className="grid gap-2">
                                        {groups.map((g) => {
                                            const checked =
                                                editSubGroupsForm.data.groups.includes(g.slug);
                                            return (
                                                <label
                                                    key={g.id}
                                                    className={`flex items-center justify-between rounded-lg border p-2.5 text-sm cursor-pointer transition-colors ${
                                                        checked
                                                            ? 'border-primary/50 bg-primary/5'
                                                            : 'hover:bg-muted/30'
                                                    }`}
                                                >
                                                    <div className="flex items-center gap-2">
                                                        <span
                                                            className="h-3 w-3 rounded-full"
                                                            style={{ backgroundColor: g.color }}
                                                        />
                                                        <span className="font-medium">
                                                            {g.name}
                                                        </span>
                                                    </div>
                                                    <input
                                                        type="checkbox"
                                                        checked={checked}
                                                        onChange={() => {
                                                            const cur =
                                                                editSubGroupsForm.data.groups;
                                                            editSubGroupsForm.setData(
                                                                'groups',
                                                                checked
                                                                    ? cur.filter(
                                                                          (x) => x !== g.slug,
                                                                      )
                                                                    : [...cur, g.slug],
                                                            );
                                                        }}
                                                        className="h-4 w-4 rounded border-input"
                                                    />
                                                </label>
                                            );
                                        })}
                                    </div>
                                )}

                                <DialogFooter className="pt-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setEditGroupsSubscriber(null)}
                                    >
                                        Cancel
                                    </Button>
                                    <Button type="submit" disabled={editSubGroupsForm.processing}>
                                        Save Groups
                                    </Button>
                                </DialogFooter>
                            </form>
                        )}
                    </DialogContent>
                </Dialog>

                {/* MODAL: BULK TAG */}
                <Dialog open={bulkGroupOpen} onOpenChange={setBulkGroupOpen}>
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>Bulk Tag Subscribers</DialogTitle>
                            <DialogDescription>
                                Add or remove a group tag for {selectedIds.length} selected
                                subscriber(s).
                            </DialogDescription>
                        </DialogHeader>
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                bulkGroupForm.post(urls.bulk_group, {
                                    onSuccess: () => {
                                        setBulkGroupOpen(false);
                                        setSelectedIds([]);
                                    },
                                });
                            }}
                            className="space-y-4"
                        >
                            <FormField id="bulk_action" label="Action">
                                <NativeSelect
                                    id="bulk_action"
                                    value={bulkGroupForm.data.action}
                                    onChange={(e) =>
                                        bulkGroupForm.setData(
                                            'action',
                                            e.target.value as 'add' | 'remove',
                                        )
                                    }
                                >
                                    <option value="add">Add tag to selected</option>
                                    <option value="remove">Remove tag from selected</option>
                                </NativeSelect>
                            </FormField>

                            <FormField id="bulk_group" label="Target Group">
                                <NativeSelect
                                    id="bulk_group"
                                    value={bulkGroupForm.data.group_slug}
                                    onChange={(e) =>
                                        bulkGroupForm.setData('group_slug', e.target.value)
                                    }
                                >
                                    {groups.map((g) => (
                                        <option key={g.id} value={g.slug}>
                                            {g.name}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </FormField>

                            <DialogFooter className="pt-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setBulkGroupOpen(false)}
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={bulkGroupForm.processing}>
                                    Apply to {selectedIds.length} Subscribers
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>

                {/* MODAL: CREATE CUSTOM GROUP */}
                <Dialog open={createGroupOpen} onOpenChange={setCreateGroupOpen}>
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>Create Audience Group</DialogTitle>
                            <DialogDescription>
                                Define a segment or tag for targeted campaigns.
                            </DialogDescription>
                        </DialogHeader>
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                createGroupForm.post(urls.store_group, {
                                    onSuccess: () => setCreateGroupOpen(false),
                                });
                            }}
                            className="space-y-4"
                        >
                            <FormField
                                id="grp_name"
                                label="Group Name"
                                error={createGroupForm.errors.name}
                            >
                                <Input
                                    id="grp_name"
                                    required
                                    value={createGroupForm.data.name}
                                    onChange={(e) =>
                                        createGroupForm.setData('name', e.target.value)
                                    }
                                    placeholder="e.g. VIP Club, Summer Sale, Newsletter Elite"
                                />
                            </FormField>

                            <FormField
                                id="grp_description"
                                label="Description (Optional)"
                                error={createGroupForm.errors.description}
                            >
                                <Input
                                    id="grp_description"
                                    value={createGroupForm.data.description}
                                    onChange={(e) =>
                                        createGroupForm.setData('description', e.target.value)
                                    }
                                    placeholder="Criteria or purpose for this segment"
                                />
                            </FormField>

                            <div>
                                <Label className="mb-2 block">Badge Accent Color</Label>
                                <div className="flex items-center gap-3">
                                    <input
                                        type="color"
                                        value={createGroupForm.data.color}
                                        onChange={(e) =>
                                            createGroupForm.setData('color', e.target.value)
                                        }
                                        className="h-9 w-12 rounded border p-1 cursor-pointer"
                                    />
                                    <div className="flex gap-1.5">
                                        {[
                                            '#0ea5e9',
                                            '#8b5cf6',
                                            '#10b981',
                                            '#f59e0b',
                                            '#ef4444',
                                            '#ec4899',
                                        ].map((c) => (
                                            <button
                                                key={c}
                                                type="button"
                                                onClick={() =>
                                                    createGroupForm.setData('color', c)
                                                }
                                                className="h-6 w-6 rounded-full border border-black/10 transition-transform hover:scale-110"
                                                style={{ backgroundColor: c }}
                                                title={c}
                                            />
                                        ))}
                                    </div>
                                </div>
                            </div>

                            <DialogFooter className="pt-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setCreateGroupOpen(false)}
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={createGroupForm.processing}>
                                    Create Group
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>

                {/* MODAL: CAMPAIGN EDITOR */}
                <Dialog open={campaignModalOpen} onOpenChange={setCampaignModalOpen}>
                    <DialogContent className="sm:max-w-3xl max-h-[90vh] overflow-y-auto">
                        <DialogHeader>
                            <DialogTitle>
                                {editingCampaign ? 'Edit Campaign' : 'Compose Email Campaign'}
                            </DialogTitle>
                            <DialogDescription>
                                Set the subject, preview preheader, audience target, and responsive HTML body.
                            </DialogDescription>
                        </DialogHeader>

                        {/* Template quick picks */}
                        <div className="flex flex-wrap items-center gap-2 rounded-lg bg-muted/40 p-2.5 text-xs">
                            <span className="font-semibold text-foreground">
                                Quick Starters:
                            </span>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="h-7 text-xs"
                                onClick={() => insertTemplate('sale')}
                            >
                                ⚡ Flash Sale
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="h-7 text-xs"
                                onClick={() => insertTemplate('vip')}
                            >
                                ✨ VIP Exclusive
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="h-7 text-xs"
                                onClick={() => insertTemplate('newsletter')}
                            >
                                🗞️ Monthly Digest
                            </Button>
                        </div>

                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                if (editingCampaign) {
                                    campaignForm.put(editingCampaign.update_url, {
                                        onSuccess: () => setCampaignModalOpen(false),
                                    });
                                } else {
                                    campaignForm.post(urls.store_campaign, {
                                        onSuccess: () => setCampaignModalOpen(false),
                                    });
                                }
                            }}
                            className="space-y-4"
                        >
                            <div className="grid gap-4 sm:grid-cols-2">
                                <FormField
                                    id="camp_title"
                                    label="Internal Campaign Title"
                                    error={campaignForm.errors.title}
                                    hint="Identifies this campaign in your dashboard."
                                >
                                    <Input
                                        id="camp_title"
                                        required
                                        value={campaignForm.data.title}
                                        onChange={(e) =>
                                            campaignForm.setData('title', e.target.value)
                                        }
                                        placeholder="e.g. November Mid-Season Launch"
                                    />
                                </FormField>

                                <FormField
                                    id="camp_target_type"
                                    label="Target Audience"
                                    error={campaignForm.errors.target_type}
                                >
                                    <NativeSelect
                                        id="camp_target_type"
                                        value={campaignForm.data.target_type}
                                        onChange={(e) =>
                                            campaignForm.setData(
                                                'target_type',
                                                e.target.value as any,
                                            )
                                        }
                                    >
                                        <option value="all">
                                            All Active Subscribers ({counts.active})
                                        </option>
                                        <option value="customers">
                                            Store Customers Only ({counts.customers})
                                        </option>
                                        <option value="leads">
                                            Storefront Leads Only
                                        </option>
                                        {groups.map((g) => (
                                            <option key={g.id} value="group">
                                                Audience Group: {g.name}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </FormField>
                            </div>

                            {campaignForm.data.target_type === 'group' && (
                                <FormField
                                    id="camp_target_group"
                                    label="Select Specific Group"
                                    error={campaignForm.errors.target_group}
                                >
                                    <NativeSelect
                                        id="camp_target_group"
                                        value={campaignForm.data.target_group}
                                        onChange={(e) =>
                                            campaignForm.setData('target_group', e.target.value)
                                        }
                                    >
                                        <option value="">Select a group...</option>
                                        {groups.map((g) => (
                                            <option key={g.id} value={g.slug}>
                                                {g.name} ({g.subscribers_count} subscribers)
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </FormField>
                            )}

                            <div className="grid gap-4 sm:grid-cols-2">
                                <FormField
                                    id="camp_subject"
                                    label="Email Subject Line"
                                    error={campaignForm.errors.subject}
                                    hint="Shown in the customer's mailbox."
                                >
                                    <Input
                                        id="camp_subject"
                                        required
                                        value={campaignForm.data.subject}
                                        onChange={(e) =>
                                            campaignForm.setData('subject', e.target.value)
                                        }
                                        placeholder="Discover what is new this week"
                                    />
                                </FormField>

                                <FormField
                                    id="camp_preview_text"
                                    label="Preheader Preview Text (Optional)"
                                    error={campaignForm.errors.preview_text}
                                    hint="Snippet shown alongside subject line."
                                >
                                    <Input
                                        id="camp_preview_text"
                                        value={campaignForm.data.preview_text}
                                        onChange={(e) =>
                                            campaignForm.setData('preview_text', e.target.value)
                                        }
                                        placeholder="Limited edition releases now live."
                                    />
                                </FormField>
                            </div>

                            <FormField
                                id="camp_content"
                                label="Campaign HTML Content"
                                error={campaignForm.errors.content_html}
                                hint="Personalization tags: {{name}}, {{email}}, {{store_name}}, {{unsubscribe_url}}"
                            >
                                <Textarea
                                    id="camp_content"
                                    rows={10}
                                    required
                                    className="font-mono text-xs"
                                    value={campaignForm.data.content_html}
                                    onChange={(e) =>
                                        campaignForm.setData('content_html', e.target.value)
                                    }
                                />
                            </FormField>

                            <div className="flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground">
                                <span>Supported tags:</span>
                                {[
                                    '{{name}}',
                                    '{{email}}',
                                    '{{store_name}}',
                                    '{{unsubscribe_url}}',
                                ].map((tag) => (
                                    <code
                                        key={tag}
                                        className="rounded bg-muted px-1.5 py-0.5 text-foreground font-mono"
                                    >
                                        {tag}
                                    </code>
                                ))}
                            </div>

                            <DialogFooter className="pt-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setCampaignModalOpen(false)}
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={campaignForm.processing}>
                                    {campaignForm.processing
                                        ? 'Saving...'
                                        : editingCampaign
                                          ? 'Update Campaign'
                                          : 'Save as Draft'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>

                {/* MODAL: PREVIEW CAMPAIGN */}
                <Dialog
                    open={previewCampaign !== null}
                    onOpenChange={(open) => !open && setPreviewCampaign(null)}
                >
                    <DialogContent className="sm:max-w-2xl max-h-[85vh] overflow-y-auto">
                        <DialogHeader>
                            <DialogTitle>Email Preview</DialogTitle>
                            <DialogDescription>
                                Subject:{' '}
                                <strong className="text-foreground">
                                    {previewCampaign?.subject}
                                </strong>
                            </DialogDescription>
                        </DialogHeader>

                        {previewCampaign && (
                            <div className="rounded-lg border bg-white text-zinc-900 p-6 shadow-xs space-y-4">
                                <div className="border-b pb-3 text-xs text-zinc-500">
                                    <div>
                                        <strong>From:</strong> {smtp.from_name} &lt;
                                        {smtp.from_address}&gt;
                                    </div>
                                    <div>
                                        <strong>Subject:</strong> {previewCampaign.subject}
                                    </div>
                                    {previewCampaign.preview_text && (
                                        <div>
                                            <strong>Preheader:</strong>{' '}
                                            {previewCampaign.preview_text}
                                        </div>
                                    )}
                                </div>
                                <iframe
                                    title="Email Preview"
                                    srcDoc={`<!DOCTYPE html><html><head><meta charset="utf-8"><style>body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;margin:20px;color:#27272a;line-height:1.6;font-size:14px;}a{color:#0ea5e9;}</style></head><body>${previewCampaign.content_html
                                        .replace(/\{\{name\}\}/g, 'Valued Customer')
                                        .replace(/\{\{email\}\}/g, 'customer@example.com')
                                        .replace(/\{\{store_name\}\}/g, smtp.from_name || 'Our Store')
                                        .replace(/\{\{unsubscribe_url\}\}/g, '#')}</body></html>`}
                                    sandbox="allow-same-origin"
                                    className="w-full h-80 rounded-md border bg-white"
                                />
                                <div className="border-t pt-4 text-center text-xs text-zinc-400">
                                    You received this email because you subscribed to updates from{' '}
                                    {smtp.from_name || 'Our Store'}.<br />
                                    <span className="text-zinc-500 underline cursor-pointer">
                                        Unsubscribe from marketing emails
                                    </span>
                                </div>
                            </div>
                        )}

                        <DialogFooter className="pt-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setPreviewCampaign(null)}
                            >
                                Close Preview
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                {/* MODAL: SEND TEST EMAIL */}
                <Dialog
                    open={testCampaign !== null}
                    onOpenChange={(open) => !open && setTestCampaign(null)}
                >
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>Send Test Preview Email</DialogTitle>
                            <DialogDescription>
                                Deliver a sample preview of "
                                <strong className="text-foreground">
                                    {testCampaign?.title}
                                </strong>
                                " to your own inbox.
                            </DialogDescription>
                        </DialogHeader>
                        {testCampaign && (
                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    testCampaignForm.post(testCampaign.send_test_url, {
                                        onSuccess: () => setTestCampaign(null),
                                    });
                                }}
                                className="space-y-4"
                            >
                                <FormField
                                    id="test_send_to"
                                    label="Send Test To"
                                    error={testCampaignForm.errors.test_email}
                                >
                                    <Input
                                        id="test_send_to"
                                        type="email"
                                        required
                                        value={testCampaignForm.data.test_email}
                                        onChange={(e) =>
                                            testCampaignForm.setData(
                                                'test_email',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="your.email@example.com"
                                    />
                                </FormField>

                                <DialogFooter className="pt-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setTestCampaign(null)}
                                    >
                                        Cancel
                                    </Button>
                                    <Button
                                        type="submit"
                                        disabled={testCampaignForm.processing}
                                    >
                                        <Send className="mr-1.5 h-3.5 w-3.5" />
                                        {testCampaignForm.processing
                                            ? 'Sending...'
                                            : 'Send Test Now'}
                                    </Button>
                                </DialogFooter>
                            </form>
                        )}
                    </DialogContent>
                </Dialog>

                {/* MODAL: BROADCAST CONFIRMATION */}
                <Dialog
                    open={broadcastConfirmCampaign !== null}
                    onOpenChange={(open) => !open && setBroadcastConfirmCampaign(null)}
                >
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2">
                                <Send className="h-5 w-5 text-primary" /> Confirm Campaign Broadcast
                            </DialogTitle>
                            <DialogDescription>
                                You are about to broadcast "
                                <strong className="text-foreground">
                                    {broadcastConfirmCampaign?.title}
                                </strong>
                                " to the selected target audience segment.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="rounded-lg bg-amber-500/10 border border-amber-500/20 p-3 text-xs text-amber-700 dark:text-amber-300">
                            <strong>Note:</strong> Delivery will proceed immediately via your
                            App Core SMTP connection. Each recipient will receive an email with their personal unsubscribe link.
                        </div>

                        {broadcastConfirmCampaign && (
                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    router.post(
                                        broadcastConfirmCampaign.send_url,
                                        {},
                                        {
                                            onSuccess: () => setBroadcastConfirmCampaign(null),
                                        },
                                    );
                                }}
                            >
                                <DialogFooter className="pt-3">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setBroadcastConfirmCampaign(null)}
                                    >
                                        Cancel
                                    </Button>
                                    <Button type="submit" className="bg-primary text-primary-foreground">
                                        <Send className="mr-1.5 h-3.5 w-3.5" /> Send Broadcast Now
                                    </Button>
                                </DialogFooter>
                            </form>
                        )}
                    </DialogContent>
                </Dialog>
            </AdminPage>
        </>
    );
}
