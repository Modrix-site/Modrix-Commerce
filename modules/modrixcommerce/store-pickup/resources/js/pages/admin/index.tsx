import { Form, Head, Link, router, useForm } from '@inertiajs/react';
import {
    Building2,
    Calendar,
    CheckCircle2,
    Clock,
    Edit,
    ExternalLink,
    PackageCheck,
    Plus,
    Search,
    Settings,
    Store,
    Trash2,
    UserCheck,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { EmptyState } from '@/components/admin/empty-state';
import { FormField, NativeSelect } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Tabs } from '@/components/admin/tabs';
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
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type OrderItem = {
    id: number;
    order_id: number;
    order_number: string;
    order_url: string | null;
    customer_email: string | null;
    grand_total: string | null;
    currency: string;
    branch_id: number | null;
    branch_name: string;
    branch_address: string;
    branch_phone: string | null;
    pickup_hours: string | null;
    collector_name: string | null;
    collector_phone: string | null;
    pickup_date: string | null;
    pickup_time: string | null;
    status: string;
    badge: {
        label: string;
        variant: string;
        color: string;
    };
    ready_at: string | null;
    picked_up_at: string | null;
    customer_notes: string | null;
    created_at: string;
    update_status_url: string;
};

type BranchItem = {
    id: number;
    name: string;
    code: string;
    address_line1: string;
    address_line2: string | null;
    city: string;
    region: string | null;
    postal_code: string | null;
    country_code: string;
    full_address: string;
    phone: string | null;
    email: string | null;
    pickup_hours: string;
    instructions: string | null;
    prep_time: string;
    fee: number;
    is_active: boolean;
    order: number;
    orders_count: number;
    update_url: string;
    delete_url: string;
};

type Props = {
    counts: {
        total_orders: number;
        pending: number;
        ready: number;
        picked_up: number;
        branches: number;
        active_branches: number;
    };
    filters: {
        status: string;
        branch_id: string;
        search: string;
        tab: string;
    };
    orders: {
        data: OrderItem[];
        current_page: number;
        last_page: number;
        total: number;
    };
    branches: BranchItem[];
    countries: { value: string; label: string }[];
    settings: {
        enabled: boolean;
        shipping_label: string;
        allow_alternative_collector: boolean;
        allow_schedule: boolean;
        instructions: string;
        cod_behavior?: string;
        pay_in_store_label?: string;
        pay_in_store_instructions?: string;
    };
    urls: {
        index: string;
        store_branch: string;
        save_settings: string;
    };
};

export default function StorePickupAdmin({
    counts,
    filters,
    orders,
    branches,
    countries,
    settings,
    urls,
}: Props) {
    const [searchVal, setSearchVal] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || 'all');
    const [branchFilter, setBranchFilter] = useState(
        filters.branch_id || 'all',
    );

    // Modals
    const [branchModalOpen, setBranchModalOpen] = useState(false);
    const [editingBranch, setEditingBranch] = useState<BranchItem | null>(null);

    // Filter submit
    const handleFilterSubmit = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            urls.index,
            {
                search: searchVal,
                status: statusFilter,
                branch_id: branchFilter,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const handleClearFilters = () => {
        setSearchVal('');
        setStatusFilter('all');
        setBranchFilter('all');
        router.get(
            urls.index,
            {},
            { preserveState: true, preserveScroll: true },
        );
    };

    // Branch Form
    const branchForm = useForm({
        name: '',
        code: '',
        address_line1: '',
        address_line2: '',
        city: '',
        region: '',
        postal_code: '',
        country_code: 'AE',
        phone: '',
        email: '',
        pickup_hours: 'Mon-Sat 10:00 - 20:00',
        instructions:
            'Please present your Order # and valid photo ID upon arrival.',
        prep_time: 'Ready in 2-4 hours',
        fee: 0,
        is_active: true,
        order: 0,
    });

    const openCreateBranchModal = () => {
        setEditingBranch(null);
        branchForm.setData({
            name: '',
            code: '',
            address_line1: '',
            address_line2: '',
            city: '',
            region: '',
            postal_code: '',
            country_code: 'AE',
            phone: '',
            email: '',
            pickup_hours: 'Mon-Sat 10:00 - 20:00',
            instructions:
                'Please present your Order # and valid photo ID upon arrival.',
            prep_time: 'Ready in 2-4 hours',
            fee: 0,
            is_active: true,
            order: 0,
        });
        setBranchModalOpen(true);
    };

    const openEditBranchModal = (branch: BranchItem) => {
        setEditingBranch(branch);
        branchForm.setData({
            name: branch.name,
            code: branch.code,
            address_line1: branch.address_line1,
            address_line2: branch.address_line2 || '',
            city: branch.city,
            region: branch.region || '',
            postal_code: branch.postal_code || '',
            country_code: branch.country_code,
            phone: branch.phone || '',
            email: branch.email || '',
            pickup_hours: branch.pickup_hours || '',
            instructions: branch.instructions || '',
            prep_time: branch.prep_time || '',
            fee: branch.fee,
            is_active: branch.is_active,
            order: branch.order,
        });
        setBranchModalOpen(true);
    };

    // Settings Form
    const settingsForm = useForm({
        enabled: settings.enabled,
        shipping_label: settings.shipping_label,
        allow_alternative_collector: settings.allow_alternative_collector,
        allow_schedule: settings.allow_schedule,
        instructions: settings.instructions,
        cod_behavior: settings.cod_behavior || 'pay_in_store',
        pay_in_store_label: settings.pay_in_store_label || 'Pay in store',
        pay_in_store_instructions:
            settings.pay_in_store_instructions ||
            'Pay in store (cash or card) when you collect your order.',
    });

    // Update order status quick action
    const handleUpdateStatus = (orderPickup: OrderItem, newStatus: string) => {
        router.post(
            orderPickup.update_status_url,
            { status: newStatus },
            { preserveScroll: true },
        );
    };

    // TAB 1: PICKUP ORDERS
    const ordersTab = (
        <div className="space-y-6">
            {/* Filter Bar */}
            <div className="flex flex-col gap-4 rounded-xl border bg-card p-4 shadow-xs sm:flex-row sm:items-center sm:justify-between">
                <form
                    onSubmit={handleFilterSubmit}
                    className="flex flex-1 flex-wrap items-center gap-3"
                >
                    <div className="relative min-w-[220px] flex-1 sm:max-w-xs">
                        <Search className="absolute top-2.5 left-3 h-4 w-4 text-muted-foreground" />
                        <Input
                            placeholder="Search order #, customer, branch..."
                            value={searchVal}
                            onChange={(e) => setSearchVal(e.target.value)}
                            className="pl-9"
                        />
                    </div>
                    <div className="w-44">
                        <NativeSelect
                            value={statusFilter}
                            onChange={(e) => setStatusFilter(e.target.value)}
                        >
                            <option value="all">All Pickup Statuses</option>
                            <option value="pending_prep">
                                Preparing Order
                            </option>
                            <option value="ready_for_pickup">
                                Ready for Pickup
                            </option>
                            <option value="picked_up">
                                Picked Up / Completed
                            </option>
                            <option value="cancelled">Cancelled</option>
                        </NativeSelect>
                    </div>
                    <div className="w-48">
                        <NativeSelect
                            value={branchFilter}
                            onChange={(e) => setBranchFilter(e.target.value)}
                        >
                            <option value="all">All Branches</option>
                            {branches.map((b) => (
                                <option key={b.id} value={b.id}>
                                    {b.name}
                                </option>
                            ))}
                        </NativeSelect>
                    </div>
                    <Button type="submit" variant="secondary" size="sm">
                        Filter
                    </Button>
                    {(filters.search ||
                        filters.status !== 'all' ||
                        filters.branch_id !== 'all') && (
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
            </div>

            {/* Orders Table */}
            {orders.data.length === 0 ? (
                <EmptyState
                    title="No store pickup orders found"
                    description="When customers select In-Store or Branch Pickup at checkout, their orders will be organized here for fulfillment."
                />
            ) : (
                <div className="overflow-hidden rounded-xl border bg-card shadow-xs">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b bg-muted/40 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-4 py-3">Order</th>
                                    <th className="px-4 py-3">Pickup Branch</th>
                                    <th className="px-4 py-3">
                                        Collector & Schedule
                                    </th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3 text-right">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {orders.data.map((order) => (
                                    <tr
                                        key={order.id}
                                        className="transition-colors hover:bg-muted/30"
                                    >
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-1.5 font-medium text-foreground">
                                                {order.order_url ? (
                                                    <Link
                                                        href={order.order_url}
                                                        className="flex items-center gap-1 text-primary hover:underline"
                                                    >
                                                        {order.order_number}
                                                        <ExternalLink className="h-3 w-3" />
                                                    </Link>
                                                ) : (
                                                    order.order_number
                                                )}
                                            </div>
                                            <div className="mt-0.5 text-xs text-muted-foreground">
                                                {order.customer_email ||
                                                    'Guest customer'}
                                            </div>
                                            {order.grand_total && (
                                                <div className="mt-1 text-xs font-semibold">
                                                    {order.currency}{' '}
                                                    {Number(
                                                        order.grand_total,
                                                    ).toFixed(2)}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-1 font-semibold text-foreground">
                                                <Store className="h-3.5 w-3.5 text-primary" />
                                                <span>{order.branch_name}</span>
                                            </div>
                                            <div className="mt-0.5 max-w-xs truncate text-xs text-muted-foreground">
                                                {order.branch_address}
                                            </div>
                                            {order.branch_phone && (
                                                <div className="mt-0.5 text-xs text-muted-foreground">
                                                    Tel: {order.branch_phone}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-xs">
                                            {order.collector_name ? (
                                                <div className="flex items-center gap-1 font-medium text-foreground">
                                                    <UserCheck className="h-3.5 w-3.5 text-blue-500" />
                                                    <span>
                                                        Collector:{' '}
                                                        {order.collector_name}
                                                    </span>
                                                    {order.collector_phone && (
                                                        <span className="text-muted-foreground">
                                                            (
                                                            {
                                                                order.collector_phone
                                                            }
                                                            )
                                                        </span>
                                                    )}
                                                </div>
                                            ) : (
                                                <div className="text-muted-foreground">
                                                    Buyer collecting
                                                </div>
                                            )}
                                            {order.pickup_date && (
                                                <div className="mt-1 flex items-center gap-1 text-muted-foreground">
                                                    <Calendar className="h-3 w-3" />
                                                    <span>
                                                        Date:{' '}
                                                        {order.pickup_date}{' '}
                                                        {order.pickup_time
                                                            ? `(${order.pickup_time})`
                                                            : ''}
                                                    </span>
                                                </div>
                                            )}
                                            {order.customer_notes && (
                                                <div className="mt-1 max-w-xs truncate text-xs text-muted-foreground italic">
                                                    "{order.customer_notes}"
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge
                                                variant="outline"
                                                style={{
                                                    borderColor: `${order.badge.color}60`,
                                                    backgroundColor: `${order.badge.color}15`,
                                                    color: order.badge.color,
                                                }}
                                                className="text-xs font-semibold"
                                            >
                                                {order.badge.label}
                                            </Badge>
                                            {order.ready_at && (
                                                <div className="mt-1 text-xs text-muted-foreground">
                                                    Ready:{' '}
                                                    {new Date(
                                                        order.ready_at,
                                                    ).toLocaleDateString()}
                                                </div>
                                            )}
                                            {order.picked_up_at && (
                                                <div className="mt-1 text-xs text-muted-foreground">
                                                    Collected:{' '}
                                                    {new Date(
                                                        order.picked_up_at,
                                                    ).toLocaleDateString()}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex items-center justify-end gap-1.5">
                                                {order.status ===
                                                    'pending_prep' && (
                                                    <Button
                                                        size="sm"
                                                        onClick={() =>
                                                            handleUpdateStatus(
                                                                order,
                                                                'ready_for_pickup',
                                                            )
                                                        }
                                                        className="h-8 bg-emerald-600 text-xs text-white hover:bg-emerald-700"
                                                    >
                                                        <CheckCircle2 className="mr-1 h-3.5 w-3.5" />{' '}
                                                        Mark Ready
                                                    </Button>
                                                )}
                                                {order.status ===
                                                    'ready_for_pickup' && (
                                                    <Button
                                                        size="sm"
                                                        onClick={() =>
                                                            handleUpdateStatus(
                                                                order,
                                                                'picked_up',
                                                            )
                                                        }
                                                        className="h-8 bg-primary text-xs text-primary-foreground"
                                                    >
                                                        <PackageCheck className="mr-1 h-3.5 w-3.5" />{' '}
                                                        Mark Picked Up
                                                    </Button>
                                                )}
                                                {order.status ===
                                                    'picked_up' && (
                                                    <Badge
                                                        variant="secondary"
                                                        className="text-xs"
                                                    >
                                                        Completed
                                                    </Badge>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}
        </div>
    );

    // TAB 2: BRANCH LOCATIONS
    const branchesTab = (
        <div className="space-y-6">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 className="text-base font-semibold">
                        Store & Branch Locations
                    </h3>
                    <p className="text-sm text-muted-foreground">
                        Manage your physical stores, retail outlets, and pickup
                        counters.
                    </p>
                </div>
                <Button size="sm" onClick={openCreateBranchModal}>
                    <Plus className="mr-1.5 h-4 w-4" /> Add Branch Location
                </Button>
            </div>

            {branches.length === 0 ? (
                <EmptyState
                    title="No branch locations configured"
                    description="Add at least one retail store or pickup point so customers can choose in-store collection during checkout."
                    action={
                        <Button size="sm" onClick={openCreateBranchModal}>
                            <Plus className="mr-1.5 h-4 w-4" /> Add your first
                            branch
                        </Button>
                    }
                />
            ) : (
                <div className="overflow-hidden rounded-xl border bg-card shadow-xs">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b bg-muted/40 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-4 py-3">
                                        Branch Location
                                    </th>
                                    <th className="px-4 py-3">
                                        Address & City
                                    </th>
                                    <th className="px-4 py-3">
                                        Operating Hours & Contact
                                    </th>
                                    <th className="px-4 py-3 text-center">
                                        Pickup Orders
                                    </th>
                                    <th className="px-4 py-3 text-center">
                                        Status
                                    </th>
                                    <th className="px-4 py-3 text-right">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {branches.map((b) => (
                                    <tr
                                        key={b.id}
                                        className="transition-colors hover:bg-muted/30"
                                    >
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-1.5 font-semibold text-foreground">
                                                <Store className="h-4 w-4 text-primary" />
                                                <span>{b.name}</span>
                                            </div>
                                            <div className="mt-0.5 font-mono text-xs text-muted-foreground">
                                                Code: {b.code}
                                            </div>
                                            <div className="mt-0.5 text-xs text-muted-foreground">
                                                {b.prep_time} ·{' '}
                                                {b.fee === 0
                                                    ? 'Free pickup'
                                                    : `Fee: $${b.fee.toFixed(2)}`}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-xs">
                                            <div className="font-medium text-foreground">
                                                {b.address_line1}
                                            </div>
                                            {b.address_line2 && (
                                                <div>{b.address_line2}</div>
                                            )}
                                            <div className="text-muted-foreground">
                                                {b.city},{' '}
                                                {b.region
                                                    ? `${b.region}, `
                                                    : ''}
                                                {b.country_code}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-xs text-muted-foreground">
                                            <div>{b.pickup_hours}</div>
                                            {b.phone && (
                                                <div className="mt-0.5">
                                                    Tel: {b.phone}
                                                </div>
                                            )}
                                            {b.email && (
                                                <div>Email: {b.email}</div>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            <Badge variant="secondary">
                                                {b.orders_count} order
                                                {b.orders_count !== 1
                                                    ? 's'
                                                    : ''}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            {b.is_active ? (
                                                <Badge
                                                    variant="outline"
                                                    className="border-emerald-500/30 text-xs text-emerald-600 dark:text-emerald-400"
                                                >
                                                    ● Active
                                                </Badge>
                                            ) : (
                                                <Badge
                                                    variant="secondary"
                                                    className="text-xs text-muted-foreground"
                                                >
                                                    Inactive
                                                </Badge>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex items-center justify-end gap-1">
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        openEditBranchModal(b)
                                                    }
                                                    className="h-8 px-2 text-xs"
                                                >
                                                    <Edit className="h-3.5 w-3.5" />
                                                </Button>
                                                <Form
                                                    action={b.delete_url}
                                                    method="delete"
                                                    options={{
                                                        preserveScroll: true,
                                                    }}
                                                >
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        className="h-8 px-2 text-xs text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                        aria-label={`Delete branch ${b.name}`}
                                                    >
                                                        <Trash2 className="h-3.5 w-3.5" />
                                                    </Button>
                                                </Form>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}
        </div>
    );

    // TAB 3: SETTINGS
    const settingsTab = (
        <div className="max-w-3xl space-y-6">
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    settingsForm.post(urls.save_settings, {
                        preserveScroll: true,
                    });
                }}
            >
                <Card className="py-4 shadow-xs">
                    <CardHeader>
                        <CardTitle className="text-base">
                            Store Pickup Configuration
                        </CardTitle>
                        <CardDescription className="text-xs">
                            Configure how store pickup is presented to customers
                            during checkout.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex items-center justify-between rounded-lg border p-3">
                            <div className="space-y-0.5">
                                <Label
                                    htmlFor="enabled"
                                    className="text-sm font-medium"
                                >
                                    Enable In-Store Pickup
                                </Label>
                                <p className="text-xs text-muted-foreground">
                                    Allows shoppers to select store pickup at
                                    checkout.
                                </p>
                            </div>
                            <input
                                id="enabled"
                                type="checkbox"
                                checked={settingsForm.data.enabled}
                                onChange={(e) =>
                                    settingsForm.setData(
                                        'enabled',
                                        e.target.checked,
                                    )
                                }
                                className="h-4 w-4 rounded border-input"
                            />
                        </div>

                        <FormField
                            id="shipping_label"
                            label="Shipping Method Title"
                            error={settingsForm.errors.shipping_label}
                            hint="Label displayed for this shipping method at checkout."
                        >
                            <Input
                                id="shipping_label"
                                value={settingsForm.data.shipping_label}
                                onChange={(e) =>
                                    settingsForm.setData(
                                        'shipping_label',
                                        e.target.value,
                                    )
                                }
                            />
                        </FormField>

                        <div className="flex items-center justify-between rounded-lg border p-3">
                            <div className="space-y-0.5">
                                <Label
                                    htmlFor="allow_alternative_collector"
                                    className="text-sm font-medium"
                                >
                                    Allow Alternative Collector
                                </Label>
                                <p className="text-xs text-muted-foreground">
                                    Permit customers to authorize another person
                                    to pick up their order.
                                </p>
                            </div>
                            <input
                                id="allow_alternative_collector"
                                type="checkbox"
                                checked={
                                    settingsForm.data
                                        .allow_alternative_collector
                                }
                                onChange={(e) =>
                                    settingsForm.setData(
                                        'allow_alternative_collector',
                                        e.target.checked,
                                    )
                                }
                                className="h-4 w-4 rounded border-input"
                            />
                        </div>

                        <div className="flex items-center justify-between rounded-lg border p-3">
                            <div className="space-y-0.5">
                                <Label
                                    htmlFor="allow_schedule"
                                    className="text-sm font-medium"
                                >
                                    Allow Preferred Pickup Scheduling
                                </Label>
                                <p className="text-xs text-muted-foreground">
                                    Customers can specify preferred pickup date
                                    and time window.
                                </p>
                            </div>
                            <input
                                id="allow_schedule"
                                type="checkbox"
                                checked={settingsForm.data.allow_schedule}
                                onChange={(e) =>
                                    settingsForm.setData(
                                        'allow_schedule',
                                        e.target.checked,
                                    )
                                }
                                className="h-4 w-4 rounded border-input"
                            />
                        </div>

                        <FormField
                            id="instructions"
                            label="Default Collection Instructions"
                            error={settingsForm.errors.instructions}
                            hint="Shown on customer receipts, order confirmation, and readiness notices."
                        >
                            <Textarea
                                id="instructions"
                                rows={3}
                                value={settingsForm.data.instructions}
                                onChange={(e) =>
                                    settingsForm.setData(
                                        'instructions',
                                        e.target.value,
                                    )
                                }
                            />
                        </FormField>

                        <div className="space-y-4 rounded-lg border bg-muted/20 p-4">
                            <div>
                                <Label
                                    htmlFor="cod_behavior"
                                    className="text-sm font-semibold text-foreground"
                                >
                                    Cash on Delivery & Payment Handling
                                </Label>
                                <p className="mt-0.5 text-xs text-muted-foreground">
                                    Configure how Cash on Delivery behaves when
                                    in-store or branch pickup is chosen.
                                </p>
                            </div>

                            <FormField
                                id="cod_behavior"
                                label="Cash on Delivery Behavior for Pickup"
                                error={settingsForm.errors.cod_behavior}
                                hint="Choose whether to transform Cash on Delivery into 'Pay in store' (waiving courier fees) or hide it entirely."
                            >
                                <select
                                    id="cod_behavior"
                                    value={settingsForm.data.cod_behavior}
                                    onChange={(e) =>
                                        settingsForm.setData(
                                            'cod_behavior',
                                            e.target.value,
                                        )
                                    }
                                    className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                                >
                                    <option value="pay_in_store">
                                        Change to "Pay in store" (Recommended -
                                        courier fee waived)
                                    </option>
                                    <option value="hide">
                                        Hide Cash on Delivery (only allow
                                        prepaid online / bank transfer)
                                    </option>
                                    <option value="keep">
                                        Keep Cash on Delivery as-is (standard
                                        courier fee applies)
                                    </option>
                                </select>
                            </FormField>

                            {settingsForm.data.cod_behavior ===
                                'pay_in_store' && (
                                <div className="space-y-4 border-t pt-2">
                                    <FormField
                                        id="pay_in_store_label"
                                        label="Pay in Store Title"
                                        error={
                                            settingsForm.errors
                                                .pay_in_store_label
                                        }
                                        hint="Payment option label shown at checkout (default: 'Pay in store')."
                                    >
                                        <Input
                                            id="pay_in_store_label"
                                            value={
                                                settingsForm.data
                                                    .pay_in_store_label
                                            }
                                            onChange={(e) =>
                                                settingsForm.setData(
                                                    'pay_in_store_label',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </FormField>

                                    <FormField
                                        id="pay_in_store_instructions"
                                        label="Pay in Store Instructions"
                                        error={
                                            settingsForm.errors
                                                .pay_in_store_instructions
                                        }
                                        hint="Instructions displayed to the customer under the Pay in store option."
                                    >
                                        <Textarea
                                            id="pay_in_store_instructions"
                                            rows={2}
                                            value={
                                                settingsForm.data
                                                    .pay_in_store_instructions
                                            }
                                            onChange={(e) =>
                                                settingsForm.setData(
                                                    'pay_in_store_instructions',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </FormField>
                                </div>
                            )}
                        </div>
                    </CardContent>

                    <CardFooter className="flex justify-end border-t pt-4">
                        <Button
                            type="submit"
                            disabled={settingsForm.processing}
                        >
                            {settingsForm.processing
                                ? 'Saving...'
                                : 'Save Settings'}
                        </Button>
                    </CardFooter>
                </Card>
            </form>
        </div>
    );

    return (
        <>
            <Head title="Store Pickup & Click & Collect" />
            <AdminPage>
                <PageHeader
                    title="Store Pickup & Click & Collect"
                    description="Allow shoppers to collect orders in-store or at local retail branches with multi-location management, preparation workflow, and readiness notifications."
                />

                {/* KPI Stat Cards */}
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-5">
                    <div className="rounded-xl border bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between text-muted-foreground">
                            <span className="text-xs font-medium tracking-wider uppercase">
                                Total Pickups
                            </span>
                            <Store className="h-4 w-4" />
                        </div>
                        <div className="mt-2 text-2xl font-bold">
                            {counts.total_orders}
                        </div>
                        <div className="mt-0.5 text-xs text-muted-foreground">
                            Orders collected
                        </div>
                    </div>

                    <div className="rounded-xl border bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between text-muted-foreground">
                            <span className="text-xs font-medium tracking-wider uppercase">
                                Preparing
                            </span>
                            <Clock className="h-4 w-4 text-amber-500" />
                        </div>
                        <div className="mt-2 text-2xl font-bold">
                            {counts.pending}
                        </div>
                        <div className="mt-0.5 text-xs text-muted-foreground">
                            Needs preparation
                        </div>
                    </div>

                    <div className="rounded-xl border bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between text-muted-foreground">
                            <span className="text-xs font-medium tracking-wider uppercase">
                                Ready for Pickup
                            </span>
                            <CheckCircle2 className="h-4 w-4 text-emerald-500" />
                        </div>
                        <div className="mt-2 text-2xl font-bold">
                            {counts.ready}
                        </div>
                        <div className="mt-0.5 text-xs text-muted-foreground">
                            Waiting for customer
                        </div>
                    </div>

                    <div className="rounded-xl border bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between text-muted-foreground">
                            <span className="text-xs font-medium tracking-wider uppercase">
                                Completed
                            </span>
                            <PackageCheck className="h-4 w-4 text-blue-500" />
                        </div>
                        <div className="mt-2 text-2xl font-bold">
                            {counts.picked_up}
                        </div>
                        <div className="mt-0.5 text-xs text-muted-foreground">
                            Customer collected
                        </div>
                    </div>

                    <div className="rounded-xl border bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between text-muted-foreground">
                            <span className="text-xs font-medium tracking-wider uppercase">
                                Branches
                            </span>
                            <Building2 className="h-4 w-4 text-purple-500" />
                        </div>
                        <div className="mt-2 text-2xl font-bold">
                            {counts.active_branches}
                        </div>
                        <div className="mt-0.5 text-xs text-muted-foreground">
                            Active locations
                        </div>
                    </div>
                </div>

                {/* Tabs */}
                <Tabs
                    label="Store pickup tabs"
                    items={[
                        {
                            id: 'orders',
                            label: (
                                <span className="flex items-center gap-1.5">
                                    <PackageCheck className="h-4 w-4" /> Pickup
                                    Orders ({counts.total_orders})
                                </span>
                            ),
                            content: ordersTab,
                        },
                        {
                            id: 'branches',
                            label: (
                                <span className="flex items-center gap-1.5">
                                    <Building2 className="h-4 w-4" /> Branches &
                                    Locations ({counts.branches})
                                </span>
                            ),
                            content: branchesTab,
                        },
                        {
                            id: 'settings',
                            label: (
                                <span className="flex items-center gap-1.5">
                                    <Settings className="h-4 w-4" /> Settings
                                </span>
                            ),
                            content: settingsTab,
                        },
                    ]}
                />

                {/* Modal: Add / Edit Branch */}
                <Dialog
                    open={branchModalOpen}
                    onOpenChange={setBranchModalOpen}
                >
                    <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                        <DialogHeader>
                            <DialogTitle>
                                {editingBranch
                                    ? 'Edit Branch Location'
                                    : 'Add Pickup Branch Location'}
                            </DialogTitle>
                            <DialogDescription>
                                Set branch contact details, address, pickup
                                hours, and preparation lead time.
                            </DialogDescription>
                        </DialogHeader>

                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                if (editingBranch) {
                                    branchForm.put(editingBranch.update_url, {
                                        onSuccess: () =>
                                            setBranchModalOpen(false),
                                    });
                                } else {
                                    branchForm.post(urls.store_branch, {
                                        onSuccess: () =>
                                            setBranchModalOpen(false),
                                    });
                                }
                            }}
                            className="space-y-4"
                        >
                            <div className="grid gap-4 sm:grid-cols-2">
                                <FormField
                                    id="branch_name"
                                    label="Branch Name"
                                    error={branchForm.errors.name}
                                >
                                    <Input
                                        id="branch_name"
                                        required
                                        value={branchForm.data.name}
                                        onChange={(e) =>
                                            branchForm.setData(
                                                'name',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="e.g. Downtown Mall Flagship"
                                    />
                                </FormField>

                                <FormField
                                    id="branch_code"
                                    label="Code / Slug"
                                    error={branchForm.errors.code}
                                    hint="Unique identifier (auto-generated if empty)"
                                >
                                    <Input
                                        id="branch_code"
                                        value={branchForm.data.code}
                                        onChange={(e) =>
                                            branchForm.setData(
                                                'code',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="downtown-flagship"
                                    />
                                </FormField>
                            </div>

                            <FormField
                                id="branch_address1"
                                label="Street Address"
                                error={branchForm.errors.address_line1}
                            >
                                <Input
                                    id="branch_address1"
                                    required
                                    value={branchForm.data.address_line1}
                                    onChange={(e) =>
                                        branchForm.setData(
                                            'address_line1',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="123 Retail Boulevard, Suite 100"
                                />
                            </FormField>

                            <div className="grid gap-4 sm:grid-cols-3">
                                <div>
                                    <FormField
                                        id="branch_city"
                                        label="City"
                                        error={branchForm.errors.city}
                                    >
                                        <Input
                                            id="branch_city"
                                            required
                                            value={branchForm.data.city}
                                            onChange={(e) =>
                                                branchForm.setData(
                                                    'city',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Dubai"
                                        />
                                    </FormField>
                                </div>
                                <div>
                                    <FormField
                                        id="branch_region"
                                        label="Region / State"
                                        error={branchForm.errors.region}
                                    >
                                        <Input
                                            id="branch_region"
                                            value={branchForm.data.region}
                                            onChange={(e) =>
                                                branchForm.setData(
                                                    'region',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Dubai"
                                        />
                                    </FormField>
                                </div>
                                <div>
                                    <FormField
                                        id="branch_country"
                                        label="Country"
                                        error={branchForm.errors.country_code}
                                    >
                                        <NativeSelect
                                            id="branch_country"
                                            value={branchForm.data.country_code}
                                            onChange={(e) =>
                                                branchForm.setData(
                                                    'country_code',
                                                    e.target.value,
                                                )
                                            }
                                        >
                                            {countries.map((c) => (
                                                <option
                                                    key={c.value}
                                                    value={c.value}
                                                >
                                                    {c.label}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                    </FormField>
                                </div>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <FormField
                                    id="branch_phone"
                                    label="Contact Phone"
                                    error={branchForm.errors.phone}
                                >
                                    <Input
                                        id="branch_phone"
                                        value={branchForm.data.phone}
                                        onChange={(e) =>
                                            branchForm.setData(
                                                'phone',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="+971 4 000 0000"
                                    />
                                </FormField>

                                <FormField
                                    id="branch_email"
                                    label="Contact Email"
                                    error={branchForm.errors.email}
                                >
                                    <Input
                                        id="branch_email"
                                        type="email"
                                        value={branchForm.data.email}
                                        onChange={(e) =>
                                            branchForm.setData(
                                                'email',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="branch@store.com"
                                    />
                                </FormField>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <FormField
                                    id="branch_hours"
                                    label="Pickup / Operating Hours"
                                    error={branchForm.errors.pickup_hours}
                                >
                                    <Input
                                        id="branch_hours"
                                        value={branchForm.data.pickup_hours}
                                        onChange={(e) =>
                                            branchForm.setData(
                                                'pickup_hours',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Mon-Sat 10:00 - 20:00"
                                    />
                                </FormField>

                                <FormField
                                    id="branch_prep"
                                    label="Preparation Lead Time"
                                    error={branchForm.errors.prep_time}
                                >
                                    <Input
                                        id="branch_prep"
                                        value={branchForm.data.prep_time}
                                        onChange={(e) =>
                                            branchForm.setData(
                                                'prep_time',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Ready in 2 hours"
                                    />
                                </FormField>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <FormField
                                    id="branch_fee"
                                    label="Pickup Fee (0 for Free)"
                                    error={
                                        branchForm.errors.fee
                                            ? String(branchForm.errors.fee)
                                            : undefined
                                    }
                                >
                                    <Input
                                        id="branch_fee"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        value={branchForm.data.fee}
                                        onChange={(e) =>
                                            branchForm.setData(
                                                'fee',
                                                Number(e.target.value),
                                            )
                                        }
                                    />
                                </FormField>

                                <div className="flex items-center gap-2 pt-6">
                                    <input
                                        id="branch_active"
                                        type="checkbox"
                                        checked={branchForm.data.is_active}
                                        onChange={(e) =>
                                            branchForm.setData(
                                                'is_active',
                                                e.target.checked,
                                            )
                                        }
                                        className="h-4 w-4 rounded border-input"
                                    />
                                    <Label
                                        htmlFor="branch_active"
                                        className="cursor-pointer"
                                    >
                                        Branch is Active & Accepting Pickups
                                    </Label>
                                </div>
                            </div>

                            <FormField
                                id="branch_instructions"
                                label="Special Instructions / Directions"
                                error={branchForm.errors.instructions}
                                hint="e.g. Ground floor customer counter near east entrance."
                            >
                                <Textarea
                                    id="branch_instructions"
                                    rows={2}
                                    value={branchForm.data.instructions}
                                    onChange={(e) =>
                                        branchForm.setData(
                                            'instructions',
                                            e.target.value,
                                        )
                                    }
                                />
                            </FormField>

                            <DialogFooter className="pt-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setBranchModalOpen(false)}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={branchForm.processing}
                                >
                                    {branchForm.processing
                                        ? 'Saving...'
                                        : editingBranch
                                          ? 'Update Branch'
                                          : 'Save Branch'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </AdminPage>
        </>
    );
}
