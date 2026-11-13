import { Head, router, useForm } from '@inertiajs/react';
import {
    BadgeCheck,
    Building2,
    CheckCircle2,
    Coins,
    ExternalLink,
    FileCheck2,
    Package,
    Plus,
    RefreshCw,
    Settings2,
    ShieldAlert,
    ShieldCheck,
    ShoppingBag,
    Star,
    Store,
    Wallet,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

interface VendorRow {
    id: number;
    user_id: number | null;
    has_user_account?: boolean;
    store_name: string;
    slug: string;
    tagline: string | null;
    description: string | null;
    email: string;
    phone: string | null;
    status: 'pending' | 'approved' | 'suspended' | 'rejected';
    verification_status:
        | 'unverified'
        | 'pending_review'
        | 'verified'
        | 'rejected'
        | 'more_info_required';
    business_type: string;
    business_type_label: string;
    legal_business_name: string | null;
    tax_id: string | null;
    trade_license_number: string | null;
    identity_document_ref: string | null;
    business_address: string | null;
    city: string | null;
    country_code: string | null;
    verification_notes: string | null;
    rejection_reason: string | null;
    verified_at: string | null;
    commission_rate: number | null;
    effective_commission_rate: number;
    payout_method: string;
    payout_method_label: string;
    payout_destination: string;
    auto_payout_enabled: boolean;
    is_featured: boolean;
    rating: number;
    total_sales_amount: number;
    total_commission_amount: number;
    available_balance: number;
    paid_out_total: number;
    products_count: number;
    orders_count: number;
    storefront_url: string;
}

interface VendorProductRow {
    id: number;
    vendor_id: number;
    vendor_name: string;
    vendor_verified: boolean;
    product_id: number;
    product_name: string;
    product_slug: string | null;
    product_status: string;
    vendor_sku: string;
    price_formatted: string;
    approval_status: 'pending' | 'approved' | 'rejected';
    commission_override: number | null;
    rejection_note: string | null;
}

interface VendorOrderRow {
    id: number;
    vendor_id: number;
    vendor_name: string;
    order_id: number;
    order_number: string;
    product_name: string;
    quantity: number;
    currency: string;
    gross_amount: number;
    commission_rate: number;
    commission_amount: number;
    vendor_earning: number;
    fulfillment_status: string;
    payout_status: string;
    tracking_number: string | null;
    created_at: string | null;
}

interface PayoutRow {
    id: number;
    vendor_id: number;
    vendor_name: string;
    reference: string;
    currency: string;
    amount: number;
    fee_amount: number;
    net_amount: number;
    payout_method: string;
    payout_method_label: string;
    destination_summary: string | null;
    status: 'pending' | 'approved' | 'processing' | 'paid' | 'rejected';
    requested_by_vendor: boolean;
    transaction_reference: string | null;
    notes: string | null;
    processed_at: string | null;
    created_at: string | null;
}

interface MarketplaceStats {
    total_vendors: number;
    approved_vendors: number;
    pending_vendors: number;
    verified_vendors: number;
    pending_verifications: number;
    vendor_products: number;
    pending_products: number;
    gross_sales: number;
    marketplace_commission: number;
    pending_payouts_count: number;
    pending_payouts_amount: number;
    paid_payouts_amount: number;
}

interface SettingsData {
    enabled: boolean;
    allow_vendor_registration: boolean;
    auto_approve_vendors: boolean;
    require_business_verification: boolean;
    auto_approve_products: boolean;
    default_commission_rate: number;
    payout_mode: 'vendor_request' | 'automatic_on_order' | 'admin_batch';
    min_payout_amount: number;
    payout_fee_percent: number;
    show_vendor_on_cards: boolean;
    show_vendor_box_on_product: boolean;
}

interface Props {
    settings: SettingsData;
    stats: MarketplaceStats;
    vendors: VendorRow[];
    vendorProducts: VendorProductRow[];
    vendorOrders: VendorOrderRow[];
    payouts: PayoutRow[];
    catalogProducts: Array<{ id: number; name: string }>;
    businessTypes: Record<string, string>;
    payoutMethods: Record<string, string>;
    currency: string;
}

type AdminTab =
    | 'vendors'
    | 'verification'
    | 'catalog'
    | 'orders'
    | 'payouts'
    | 'settings';

export default function MultiVendorAdminPage({
    settings,
    stats,
    vendors,
    vendorProducts,
    vendorOrders,
    payouts,
    catalogProducts,
    businessTypes,
    payoutMethods,
    currency,
}: Props) {
    const [activeTab, setActiveTab] = useState<AdminTab>('vendors');
    const [showNewVendorForm, setShowNewVendorForm] = useState(false);
    const [txRefs, setTxRefs] = useState<Record<number, string>>({});
    const [kybNotes, setKybNotes] = useState<Record<number, string>>({});
    const [sellerPasswords, setSellerPasswords] = useState<
        Record<number, string>
    >({});

    const settingsForm = useForm<SettingsData>({
        ...settings,
    });

    const newVendorForm = useForm({
        store_name: '',
        email: '',
        password: '',
        phone: '',
        tagline: '',
        description: '',
        business_type: 'llc',
        legal_business_name: '',
        tax_id: '',
        trade_license_number: '',
        city: '',
        country_code: 'AE',
        commission_rate: '',
        payout_method: 'bank_transfer',
        auto_payout_enabled: false,
        bank_name: '',
        bank_account_number: '',
        payout_email: '',
        approve_immediately: true,
        verify_immediately: true,
    });

    const assignProductForm = useForm({
        product_id: catalogProducts[0]?.id ? String(catalogProducts[0].id) : '',
        vendor_id: vendors[0]?.id ? String(vendors[0].id) : '',
        commission_override: '',
        vendor_sku: '',
        approval_status: 'approved',
    });

    const submitSettings = (e: React.FormEvent) => {
        e.preventDefault();
        settingsForm.put('/admin/m/modrixcommerce/multi-vendor/settings', {
            preserveScroll: true,
        });
    };

    const submitNewVendor = (e: React.FormEvent) => {
        e.preventDefault();
        newVendorForm.post('/admin/m/modrixcommerce/multi-vendor/vendors', {
            preserveScroll: true,
            onSuccess: () => {
                newVendorForm.reset();
                setShowNewVendorForm(false);
            },
        });
    };

    const submitAssignProduct = (e: React.FormEvent) => {
        e.preventDefault();
        assignProductForm.post(
            '/admin/m/modrixcommerce/multi-vendor/products/assign',
            {
                preserveScroll: true,
            },
        );
    };

    const updateVendorStatus = (
        vendor: VendorRow,
        status: VendorRow['status'],
        isFeatured?: boolean,
    ) => {
        router.put(
            `/admin/m/modrixcommerce/multi-vendor/vendors/${vendor.id}/status`,
            {
                status,
                is_featured: isFeatured ?? vendor.is_featured,
                commission_rate: vendor.commission_rate,
            },
            { preserveScroll: true },
        );
    };

    const saveVendorPassword = (vendor: VendorRow) => {
        const pwd = (sellerPasswords[vendor.id] ?? '').trim();
        if (pwd.length < 8) {
            return;
        }
        router.put(
            `/admin/m/modrixcommerce/multi-vendor/vendors/${vendor.id}/credentials`,
            {
                email: vendor.email,
                password: pwd,
                account_name: vendor.store_name,
            },
            {
                preserveScroll: true,
                onSuccess: () =>
                    setSellerPasswords((prev) => ({
                        ...prev,
                        [vendor.id]: '',
                    })),
            },
        );
    };

    const reviewVerification = (
        vendor: VendorRow,
        verificationStatus:
            | 'verified'
            | 'rejected'
            | 'more_info_required'
            | 'pending_review',
    ) => {
        const note = kybNotes[vendor.id] ?? '';
        router.put(
            `/admin/m/modrixcommerce/multi-vendor/vendors/${vendor.id}/verification`,
            {
                verification_status: verificationStatus,
                verification_notes: note,
                rejection_reason:
                    verificationStatus === 'rejected' ||
                    verificationStatus === 'more_info_required'
                        ? note ||
                          'Please provide valid business registration documents.'
                        : null,
            },
            { preserveScroll: true },
        );
    };

    const reviewVendorProduct = (
        vp: VendorProductRow,
        approvalStatus: 'approved' | 'rejected',
    ) => {
        router.put(
            `/admin/m/modrixcommerce/multi-vendor/products/${vp.id}/review`,
            {
                approval_status: approvalStatus,
            },
            { preserveScroll: true },
        );
    };

    const processPayout = (
        payout: PayoutRow,
        status: 'approved' | 'processing' | 'paid' | 'rejected',
    ) => {
        router.put(
            `/admin/m/modrixcommerce/multi-vendor/payouts/${payout.id}/process`,
            {
                status,
                transaction_reference:
                    txRefs[payout.id] ||
                    payout.transaction_reference ||
                    (status === 'paid' ? `BANK-TX-${Date.now()}` : null),
            },
            { preserveScroll: true },
        );
    };

    const runBatchPayouts = () => {
        router.post(
            '/admin/m/modrixcommerce/multi-vendor/payouts/batch',
            {},
            { preserveScroll: true },
        );
    };

    return (
        <div className="space-y-6 p-6">
            <Head title="Multi-Vendor Marketplace" />

            {/* Top Header */}
            <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                <div>
                    <div className="flex items-center gap-2.5">
                        <Store className="size-6 text-primary" />
                        <h1 className="text-2xl font-bold tracking-tight">
                            Multi-Vendor Marketplace
                        </h1>
                        <Badge
                            variant={settings.enabled ? 'default' : 'secondary'}
                        >
                            {settings.enabled ? 'Active' : 'Disabled'}
                        </Badge>
                    </div>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Manage vendor registration, KYB business verification,
                        seller product catalogs, marketplace commissions, and
                        automated or manual vendor payouts.
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Button variant="outline" size="sm" asChild>
                        <a
                            href="/m/modrixcommerce/multi-vendor/vendors"
                            target="_blank"
                            rel="noreferrer"
                        >
                            <ExternalLink className="mr-1.5 size-4" />
                            Storefront Vendors Directory
                        </a>
                    </Button>
                    <Button variant="outline" size="sm" asChild>
                        <a
                            href="/m/modrixcommerce/multi-vendor/register"
                            target="_blank"
                            rel="noreferrer"
                        >
                            <Building2 className="mr-1.5 size-4" />
                            Vendor Onboarding Form
                        </a>
                    </Button>
                    <Button
                        size="sm"
                        onClick={() => {
                            setActiveTab('vendors');
                            setShowNewVendorForm((v) => !v);
                        }}
                    >
                        <Plus className="mr-1.5 size-4" />
                        Add Marketplace Vendor
                    </Button>
                </div>
            </div>

            {/* KPI Summary Cards */}
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardContent className="pt-6">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-muted-foreground uppercase">
                                Approved Vendors
                            </span>
                            <Store className="size-4 text-primary" />
                        </div>
                        <div className="mt-2 text-2xl font-bold">
                            {stats.approved_vendors}{' '}
                            <span className="text-sm font-normal text-muted-foreground">
                                / {stats.total_vendors} total
                            </span>
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {stats.verified_vendors} KYB Verified •{' '}
                            {stats.pending_verifications} pending KYB review
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="pt-6">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-muted-foreground uppercase">
                                Vendor Catalog Products
                            </span>
                            <Package className="size-4 text-primary" />
                        </div>
                        <div className="mt-2 text-2xl font-bold">
                            {stats.vendor_products}
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {stats.pending_products} awaiting approval
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="pt-6">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-muted-foreground uppercase">
                                Gross Vendor GMV & Commission
                            </span>
                            <Coins className="size-4 text-emerald-600" />
                        </div>
                        <div className="mt-2 text-2xl font-bold">
                            {currency} {stats.gross_sales.toFixed(2)}
                        </div>
                        <p className="mt-1 text-xs font-medium text-emerald-600">
                            Store Commission: {currency}{' '}
                            {stats.marketplace_commission.toFixed(2)} (Default{' '}
                            {settings.default_commission_rate}%)
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="pt-6">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-muted-foreground uppercase">
                                Vendor Payouts Disbursed
                            </span>
                            <Wallet className="size-4 text-primary" />
                        </div>
                        <div className="mt-2 text-2xl font-bold">
                            {currency} {stats.paid_payouts_amount.toFixed(2)}
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {stats.pending_payouts_count} pending ({currency}{' '}
                            {stats.pending_payouts_amount.toFixed(2)})
                        </p>
                    </CardContent>
                </Card>
            </div>

            {/* Navigation Tabs */}
            <div className="flex flex-wrap gap-2 border-b pb-3">
                <Button
                    variant={activeTab === 'vendors' ? 'default' : 'outline'}
                    size="sm"
                    onClick={() => setActiveTab('vendors')}
                >
                    <Store className="mr-1.5 size-4" />
                    Vendors ({vendors.length})
                </Button>
                <Button
                    variant={
                        activeTab === 'verification' ? 'default' : 'outline'
                    }
                    size="sm"
                    onClick={() => setActiveTab('verification')}
                >
                    <FileCheck2 className="mr-1.5 size-4" />
                    Business Verification (KYB)
                    {stats.pending_verifications > 0 && (
                        <Badge variant="secondary" className="ml-1.5">
                            {stats.pending_verifications}
                        </Badge>
                    )}
                </Button>
                <Button
                    variant={activeTab === 'catalog' ? 'default' : 'outline'}
                    size="sm"
                    onClick={() => setActiveTab('catalog')}
                >
                    <Package className="mr-1.5 size-4" />
                    Vendor Product Catalog ({vendorProducts.length})
                </Button>
                <Button
                    variant={activeTab === 'orders' ? 'default' : 'outline'}
                    size="sm"
                    onClick={() => setActiveTab('orders')}
                >
                    <ShoppingBag className="mr-1.5 size-4" />
                    Orders & Commissions ({vendorOrders.length})
                </Button>
                <Button
                    variant={activeTab === 'payouts' ? 'default' : 'outline'}
                    size="sm"
                    onClick={() => setActiveTab('payouts')}
                >
                    <Wallet className="mr-1.5 size-4" />
                    Vendor Payouts ({payouts.length})
                </Button>
                <Button
                    variant={activeTab === 'settings' ? 'default' : 'outline'}
                    size="sm"
                    onClick={() => setActiveTab('settings')}
                >
                    <Settings2 className="mr-1.5 size-4" />
                    Marketplace Settings
                </Button>
            </div>

            {/* TAB 1: VENDORS MANAGEMENT */}
            {activeTab === 'vendors' && (
                <div className="space-y-6">
                    {showNewVendorForm && (
                        <Card className="border-primary/40">
                            <CardHeader>
                                <CardTitle>
                                    Create New Marketplace Vendor
                                </CardTitle>
                                <CardDescription>
                                    Onboard a seller directly from the admin
                                    panel with business verification and payout
                                    preferences.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form
                                    onSubmit={submitNewVendor}
                                    className="grid gap-4 md:grid-cols-3"
                                >
                                    <div className="space-y-1.5">
                                        <Label>Store Name *</Label>
                                        <Input
                                            required
                                            value={
                                                newVendorForm.data.store_name
                                            }
                                            onChange={(e) =>
                                                newVendorForm.setData(
                                                    'store_name',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Oasis Artisan Roasters"
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>Business / Login Email *</Label>
                                        <Input
                                            type="email"
                                            required
                                            value={newVendorForm.data.email}
                                            onChange={(e) =>
                                                newVendorForm.setData(
                                                    'email',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="partners@example.com"
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>
                                            Seller Login Password (Min 8 chars)
                                        </Label>
                                        <Input
                                            type="password"
                                            minLength={8}
                                            value={newVendorForm.data.password}
                                            onChange={(e) =>
                                                newVendorForm.setData(
                                                    'password',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Create seller login password..."
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>Phone</Label>
                                        <Input
                                            value={newVendorForm.data.phone}
                                            onChange={(e) =>
                                                newVendorForm.setData(
                                                    'phone',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="+971 50 123 4567"
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>Business Entity Type</Label>
                                        <Select
                                            value={
                                                newVendorForm.data.business_type
                                            }
                                            onValueChange={(v) =>
                                                newVendorForm.setData(
                                                    'business_type',
                                                    v,
                                                )
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {Object.entries(
                                                    businessTypes,
                                                ).map(([key, label]) => (
                                                    <SelectItem
                                                        key={key}
                                                        value={key}
                                                    >
                                                        {label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>Legal Business Name</Label>
                                        <Input
                                            value={
                                                newVendorForm.data
                                                    .legal_business_name
                                            }
                                            onChange={(e) =>
                                                newVendorForm.setData(
                                                    'legal_business_name',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Oasis Roasters Trading LLC"
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>
                                            Trade License / Reg. Number
                                        </Label>
                                        <Input
                                            value={
                                                newVendorForm.data
                                                    .trade_license_number
                                            }
                                            onChange={(e) =>
                                                newVendorForm.setData(
                                                    'trade_license_number',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="TL-DXB-994821"
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>Tax ID / VAT Number</Label>
                                        <Input
                                            value={newVendorForm.data.tax_id}
                                            onChange={(e) =>
                                                newVendorForm.setData(
                                                    'tax_id',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="TRN-100492819200003"
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>City & Country</Label>
                                        <div className="flex gap-2">
                                            <Input
                                                value={newVendorForm.data.city}
                                                onChange={(e) =>
                                                    newVendorForm.setData(
                                                        'city',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Dubai"
                                            />
                                            <Input
                                                className="w-24"
                                                value={
                                                    newVendorForm.data
                                                        .country_code
                                                }
                                                onChange={(e) =>
                                                    newVendorForm.setData(
                                                        'country_code',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="AE"
                                            />
                                        </div>
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>
                                            Custom Commission % (Leave blank for{' '}
                                            {settings.default_commission_rate}%)
                                        </Label>
                                        <Input
                                            type="number"
                                            min={0}
                                            max={90}
                                            step="0.5"
                                            value={
                                                newVendorForm.data
                                                    .commission_rate
                                            }
                                            onChange={(e) =>
                                                newVendorForm.setData(
                                                    'commission_rate',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder={`${settings.default_commission_rate}`}
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>Payout Method</Label>
                                        <Select
                                            value={
                                                newVendorForm.data.payout_method
                                            }
                                            onValueChange={(v) =>
                                                newVendorForm.setData(
                                                    'payout_method',
                                                    v,
                                                )
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {Object.entries(
                                                    payoutMethods,
                                                ).map(([key, label]) => (
                                                    <SelectItem
                                                        key={key}
                                                        value={key}
                                                    >
                                                        {label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>
                                            Bank Name / Payout Provider
                                        </Label>
                                        <Input
                                            value={newVendorForm.data.bank_name}
                                            onChange={(e) =>
                                                newVendorForm.setData(
                                                    'bank_name',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Emirates NBD"
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>
                                            IBAN / Account / Payout Email
                                        </Label>
                                        <Input
                                            value={
                                                newVendorForm.data
                                                    .bank_account_number
                                            }
                                            onChange={(e) =>
                                                newVendorForm.setData(
                                                    'bank_account_number',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="AE070331234567890123456"
                                        />
                                    </div>

                                    <div className="col-span-full flex flex-wrap items-center gap-6 pt-2">
                                        <label className="flex items-center gap-2 text-sm">
                                            <Checkbox
                                                checked={
                                                    newVendorForm.data
                                                        .approve_immediately
                                                }
                                                onCheckedChange={(v) =>
                                                    newVendorForm.setData(
                                                        'approve_immediately',
                                                        Boolean(v),
                                                    )
                                                }
                                            />
                                            Approve Vendor Store Immediately
                                        </label>
                                        <label className="flex items-center gap-2 text-sm">
                                            <Checkbox
                                                checked={
                                                    newVendorForm.data
                                                        .verify_immediately
                                                }
                                                onCheckedChange={(v) =>
                                                    newVendorForm.setData(
                                                        'verify_immediately',
                                                        Boolean(v),
                                                    )
                                                }
                                            />
                                            Mark Business Verified (KYB Badge)
                                        </label>
                                        <label className="flex items-center gap-2 text-sm">
                                            <Checkbox
                                                checked={
                                                    newVendorForm.data
                                                        .auto_payout_enabled
                                                }
                                                onCheckedChange={(v) =>
                                                    newVendorForm.setData(
                                                        'auto_payout_enabled',
                                                        Boolean(v),
                                                    )
                                                }
                                            />
                                            Enable Automatic Store Payouts for
                                            Vendor
                                        </label>
                                    </div>

                                    <div className="col-span-full flex justify-end gap-2">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            onClick={() =>
                                                setShowNewVendorForm(false)
                                            }
                                        >
                                            Cancel
                                        </Button>
                                        <Button
                                            type="submit"
                                            disabled={newVendorForm.processing}
                                        >
                                            Create Vendor
                                        </Button>
                                    </div>
                                </form>
                            </CardContent>
                        </Card>
                    )}

                    <Card>
                        <CardHeader>
                            <CardTitle>Marketplace Sellers Directory</CardTitle>
                            <CardDescription>
                                Approve or suspend seller accounts, feature top
                                stores, and monitor commission and balance
                                ledgers.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {vendors.length === 0 ? (
                                <p className="py-8 text-center text-sm text-muted-foreground">
                                    No vendors registered yet. Click &ldquo;Add
                                    Marketplace Vendor&rdquo; above or share the
                                    public Vendor Onboarding link.
                                </p>
                            ) : (
                                <div className="space-y-4">
                                    {vendors.map((vendor) => (
                                        <div
                                            key={vendor.id}
                                            className="flex flex-col justify-between gap-4 rounded-lg border p-4 lg:flex-row lg:items-center"
                                        >
                                            <div className="space-y-1">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <span className="text-base font-semibold">
                                                        {vendor.store_name}
                                                    </span>
                                                    <Badge
                                                        variant={
                                                            vendor.status ===
                                                            'approved'
                                                                ? 'default'
                                                                : vendor.status ===
                                                                    'pending'
                                                                  ? 'secondary'
                                                                  : 'destructive'
                                                        }
                                                    >
                                                        {vendor.status}
                                                    </Badge>
                                                    {vendor.verification_status ===
                                                    'verified' ? (
                                                        <Badge className="bg-emerald-600 text-white">
                                                            <BadgeCheck className="mr-1 size-3.5" />
                                                            KYB Verified
                                                        </Badge>
                                                    ) : (
                                                        <Badge variant="outline">
                                                            KYB:{' '}
                                                            {
                                                                vendor.verification_status
                                                            }
                                                        </Badge>
                                                    )}
                                                    {vendor.is_featured && (
                                                        <Badge variant="secondary">
                                                            <Star className="mr-1 size-3 fill-amber-400 text-amber-500" />
                                                            Featured
                                                        </Badge>
                                                    )}
                                                    <Badge
                                                        variant={
                                                            vendor.has_user_account
                                                                ? 'outline'
                                                                : 'secondary'
                                                        }
                                                    >
                                                        {vendor.has_user_account
                                                            ? 'Login Account Active'
                                                            : 'No Login Password Set'}
                                                    </Badge>
                                                </div>
                                                <p className="text-xs text-muted-foreground">
                                                    {vendor.email} •{' '}
                                                    {vendor.business_type_label}{' '}
                                                    {vendor.legal_business_name
                                                        ? `(${vendor.legal_business_name})`
                                                        : ''}{' '}
                                                    • Commission:{' '}
                                                    <strong>
                                                        {
                                                            vendor.effective_commission_rate
                                                        }
                                                        %
                                                    </strong>{' '}
                                                    • Payout:{' '}
                                                    {vendor.payout_destination}
                                                    {vendor.auto_payout_enabled &&
                                                        ' (Auto-Payout ON)'}
                                                </p>
                                                <div className="flex flex-wrap gap-4 pt-1 text-xs">
                                                    <span>
                                                        Products:{' '}
                                                        <strong>
                                                            {
                                                                vendor.products_count
                                                            }
                                                        </strong>
                                                    </span>
                                                    <span>
                                                        Orders:{' '}
                                                        <strong>
                                                            {
                                                                vendor.orders_count
                                                            }
                                                        </strong>
                                                    </span>
                                                    <span>
                                                        Gross Sales:{' '}
                                                        <strong>
                                                            {currency}{' '}
                                                            {vendor.total_sales_amount.toFixed(
                                                                2,
                                                            )}
                                                        </strong>
                                                    </span>
                                                    <span>
                                                        Available Balance:{' '}
                                                        <strong className="text-emerald-600">
                                                            {currency}{' '}
                                                            {vendor.available_balance.toFixed(
                                                                2,
                                                            )}
                                                        </strong>
                                                    </span>
                                                    <span>
                                                        Paid Out:{' '}
                                                        <strong>
                                                            {currency}{' '}
                                                            {vendor.paid_out_total.toFixed(
                                                                2,
                                                            )}
                                                        </strong>
                                                    </span>
                                                </div>
                                                <div className="flex flex-wrap items-center gap-2 pt-2">
                                                    <Input
                                                        type="password"
                                                        className="h-8 w-52 text-xs"
                                                        placeholder={
                                                            vendor.has_user_account
                                                                ? 'Reset seller password (min 8)...'
                                                                : 'Set seller password (min 8)...'
                                                        }
                                                        value={
                                                            sellerPasswords[
                                                                vendor.id
                                                            ] ?? ''
                                                        }
                                                        onChange={(e) =>
                                                            setSellerPasswords(
                                                                (prev) => ({
                                                                    ...prev,
                                                                    [vendor.id]:
                                                                        e.target
                                                                            .value,
                                                                }),
                                                            )
                                                        }
                                                    />
                                                    <Button
                                                        type="button"
                                                        variant="secondary"
                                                        size="sm"
                                                        className="h-8 text-xs"
                                                        disabled={
                                                            (
                                                                sellerPasswords[
                                                                    vendor.id
                                                                ] ?? ''
                                                            ).trim().length < 8
                                                        }
                                                        onClick={() =>
                                                            saveVendorPassword(
                                                                vendor,
                                                            )
                                                        }
                                                    >
                                                        {vendor.has_user_account
                                                            ? 'Reset Login Password'
                                                            : 'Create Login Account'}
                                                    </Button>
                                                </div>
                                            </div>

                                            <div className="flex flex-wrap items-center gap-2">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <a
                                                        href={
                                                            vendor.storefront_url
                                                        }
                                                        target="_blank"
                                                        rel="noreferrer"
                                                    >
                                                        <ExternalLink className="mr-1 size-3.5" />
                                                        Storefront
                                                    </a>
                                                </Button>
                                                {vendor.status !==
                                                    'approved' && (
                                                    <Button
                                                        size="sm"
                                                        onClick={() =>
                                                            updateVendorStatus(
                                                                vendor,
                                                                'approved',
                                                            )
                                                        }
                                                    >
                                                        <CheckCircle2 className="mr-1 size-3.5" />
                                                        Approve
                                                    </Button>
                                                )}
                                                {vendor.status ===
                                                    'approved' && (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() =>
                                                            updateVendorStatus(
                                                                vendor,
                                                                'approved',
                                                                !vendor.is_featured,
                                                            )
                                                        }
                                                    >
                                                        <Star className="mr-1 size-3.5" />
                                                        {vendor.is_featured
                                                            ? 'Unfeature'
                                                            : 'Feature'}
                                                    </Button>
                                                )}
                                                {vendor.status !==
                                                    'suspended' && (
                                                    <Button
                                                        variant="destructive"
                                                        size="sm"
                                                        onClick={() =>
                                                            updateVendorStatus(
                                                                vendor,
                                                                'suspended',
                                                            )
                                                        }
                                                    >
                                                        Suspend
                                                    </Button>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            )}

            {/* TAB 2: BUSINESS VERIFICATION (KYB) */}
            {activeTab === 'verification' && (
                <Card>
                    <CardHeader>
                        <CardTitle>
                            Vendor Business Verification (KYB / KYC Compliance)
                        </CardTitle>
                        <CardDescription>
                            Inspect legal business names, tax registration
                            numbers, trade licenses, and identity documents
                            before granting the Verified Marketplace Seller
                            badge.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {vendors.map((vendor) => (
                            <div
                                key={vendor.id}
                                className="space-y-3 rounded-lg border p-4"
                            >
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <div className="flex items-center gap-2">
                                        <Building2 className="size-5 text-primary" />
                                        <span className="font-semibold">
                                            {vendor.store_name}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            ({vendor.business_type_label})
                                        </span>
                                    </div>
                                    <Badge
                                        variant={
                                            vendor.verification_status ===
                                            'verified'
                                                ? 'default'
                                                : vendor.verification_status ===
                                                    'pending_review'
                                                  ? 'secondary'
                                                  : 'outline'
                                        }
                                    >
                                        KYB Status: {vendor.verification_status}
                                    </Badge>
                                </div>

                                <div className="grid gap-3 rounded-md bg-muted/40 p-3 text-xs sm:grid-cols-2 lg:grid-cols-4">
                                    <div>
                                        <span className="block text-muted-foreground">
                                            Legal Business Name
                                        </span>
                                        <strong className="text-sm">
                                            {vendor.legal_business_name ||
                                                'Not submitted'}
                                        </strong>
                                    </div>
                                    <div>
                                        <span className="block text-muted-foreground">
                                            Trade License / Reg. No
                                        </span>
                                        <strong className="text-sm">
                                            {vendor.trade_license_number ||
                                                'Not submitted'}
                                        </strong>
                                    </div>
                                    <div>
                                        <span className="block text-muted-foreground">
                                            Tax ID / VAT Registration
                                        </span>
                                        <strong className="text-sm">
                                            {vendor.tax_id || 'Not submitted'}
                                        </strong>
                                    </div>
                                    <div>
                                        <span className="block text-muted-foreground">
                                            Identity Document Reference
                                        </span>
                                        <strong className="text-sm">
                                            {vendor.identity_document_ref ||
                                                'Not submitted'}
                                        </strong>
                                    </div>
                                    <div className="sm:col-span-2">
                                        <span className="block text-muted-foreground">
                                            Registered Business Address
                                        </span>
                                        <strong>
                                            {[
                                                vendor.business_address,
                                                vendor.city,
                                                vendor.country_code,
                                            ]
                                                .filter(Boolean)
                                                .join(', ') || 'Not specified'}
                                        </strong>
                                    </div>
                                    <div className="sm:col-span-2">
                                        <span className="block text-muted-foreground">
                                            Payout Destination
                                        </span>
                                        <strong>
                                            {vendor.payout_destination}
                                        </strong>
                                    </div>
                                </div>

                                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <Input
                                        className="max-w-md text-xs"
                                        placeholder="Verification compliance note or reason if requesting more info..."
                                        value={
                                            kybNotes[vendor.id] ??
                                            vendor.verification_notes ??
                                            ''
                                        }
                                        onChange={(e) =>
                                            setKybNotes((prev) => ({
                                                ...prev,
                                                [vendor.id]: e.target.value,
                                            }))
                                        }
                                    />
                                    <div className="flex flex-wrap gap-2">
                                        <Button
                                            size="sm"
                                            onClick={() =>
                                                reviewVerification(
                                                    vendor,
                                                    'verified',
                                                )
                                            }
                                        >
                                            <ShieldCheck className="mr-1.5 size-4" />
                                            Verify Business & Approve
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                reviewVerification(
                                                    vendor,
                                                    'more_info_required',
                                                )
                                            }
                                        >
                                            <ShieldAlert className="mr-1.5 size-4" />
                                            Request More Info
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="destructive"
                                            onClick={() =>
                                                reviewVerification(
                                                    vendor,
                                                    'rejected',
                                                )
                                            }
                                        >
                                            <XCircle className="mr-1.5 size-4" />
                                            Reject KYB
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            )}

            {/* TAB 3: VENDOR PRODUCT CATALOG */}
            {activeTab === 'catalog' && (
                <div className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                Assign Store Product to Marketplace Vendor
                            </CardTitle>
                            <CardDescription>
                                Link any existing catalog product to a vendor
                                and optionally set a product-level commission
                                rate override.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form
                                onSubmit={submitAssignProduct}
                                className="grid items-end gap-4 md:grid-cols-5"
                            >
                                <div className="space-y-1.5 md:col-span-2">
                                    <Label>Catalog Product</Label>
                                    <Select
                                        value={
                                            assignProductForm.data.product_id
                                        }
                                        onValueChange={(v) =>
                                            assignProductForm.setData(
                                                'product_id',
                                                v,
                                            )
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Choose product..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {catalogProducts.map((p) => (
                                                <SelectItem
                                                    key={p.id}
                                                    value={String(p.id)}
                                                >
                                                    #{p.id} — {p.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-1.5">
                                    <Label>Marketplace Vendor</Label>
                                    <Select
                                        value={assignProductForm.data.vendor_id}
                                        onValueChange={(v) =>
                                            assignProductForm.setData(
                                                'vendor_id',
                                                v,
                                            )
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Choose vendor..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {vendors.map((v) => (
                                                <SelectItem
                                                    key={v.id}
                                                    value={String(v.id)}
                                                >
                                                    {v.store_name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-1.5">
                                    <Label>Commission Override (%)</Label>
                                    <Input
                                        type="number"
                                        min={0}
                                        max={90}
                                        step="0.5"
                                        placeholder="Default"
                                        value={
                                            assignProductForm.data
                                                .commission_override
                                        }
                                        onChange={(e) =>
                                            assignProductForm.setData(
                                                'commission_override',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    disabled={assignProductForm.processing}
                                >
                                    Assign to Vendor
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>
                                Vendor Product Catalog & Moderation Queue
                            </CardTitle>
                            <CardDescription>
                                Products created by vendors or assigned to
                                vendors. Approved products appear on the
                                vendor&apos;s storefront and main store catalog.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {vendorProducts.length === 0 ? (
                                <p className="py-8 text-center text-sm text-muted-foreground">
                                    No products assigned to vendors yet.
                                </p>
                            ) : (
                                <div className="space-y-3">
                                    {vendorProducts.map((vp) => (
                                        <div
                                            key={vp.id}
                                            className="flex flex-col justify-between gap-3 rounded-lg border p-3 sm:flex-row sm:items-center"
                                        >
                                            <div>
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <span className="font-medium">
                                                        {vp.product_name}
                                                    </span>
                                                    <Badge variant="outline">
                                                        {vp.price_formatted}
                                                    </Badge>
                                                    <Badge
                                                        variant={
                                                            vp.approval_status ===
                                                            'approved'
                                                                ? 'default'
                                                                : vp.approval_status ===
                                                                    'pending'
                                                                  ? 'secondary'
                                                                  : 'destructive'
                                                        }
                                                    >
                                                        {vp.approval_status}
                                                    </Badge>
                                                </div>
                                                <p className="mt-0.5 text-xs text-muted-foreground">
                                                    Seller:{' '}
                                                    <strong>
                                                        {vp.vendor_name}
                                                    </strong>{' '}
                                                    {vp.vendor_verified && '✓'}{' '}
                                                    • SKU:{' '}
                                                    {vp.vendor_sku || '—'}{' '}
                                                    {vp.commission_override !==
                                                        null &&
                                                        `• Custom Commission: ${vp.commission_override}%`}
                                                </p>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                {vp.approval_status !==
                                                    'approved' && (
                                                    <Button
                                                        size="sm"
                                                        onClick={() =>
                                                            reviewVendorProduct(
                                                                vp,
                                                                'approved',
                                                            )
                                                        }
                                                    >
                                                        Approve Product
                                                    </Button>
                                                )}
                                                {vp.approval_status !==
                                                    'rejected' && (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            reviewVendorProduct(
                                                                vp,
                                                                'rejected',
                                                            )
                                                        }
                                                    >
                                                        Reject
                                                    </Button>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            )}

            {/* TAB 4: VENDOR ORDERS & COMMISSIONS LEDGER */}
            {activeTab === 'orders' && (
                <Card>
                    <CardHeader>
                        <CardTitle>
                            Vendor Order Splits & Commissions Ledger
                        </CardTitle>
                        <CardDescription>
                            Every storefront order containing vendor items is
                            automatically split here with commission calculation
                            and net vendor earnings.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {vendorOrders.length === 0 ? (
                            <p className="py-8 text-center text-sm text-muted-foreground">
                                No vendor orders recorded yet.
                            </p>
                        ) : (
                            <div className="space-y-3">
                                {vendorOrders.map((vo) => (
                                    <div
                                        key={vo.id}
                                        className="flex flex-col justify-between gap-3 rounded-lg border p-3 sm:flex-row sm:items-center"
                                    >
                                        <div>
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="font-semibold">
                                                    Order {vo.order_number}
                                                </span>
                                                <span className="text-sm">
                                                    • {vo.product_name} ×{' '}
                                                    {vo.quantity}
                                                </span>
                                                <Badge variant="outline">
                                                    Seller: {vo.vendor_name}
                                                </Badge>
                                                <Badge variant="secondary">
                                                    Fulfillment:{' '}
                                                    {vo.fulfillment_status}
                                                </Badge>
                                                <Badge
                                                    variant={
                                                        vo.payout_status ===
                                                        'paid'
                                                            ? 'default'
                                                            : 'outline'
                                                    }
                                                >
                                                    Payout: {vo.payout_status}
                                                </Badge>
                                            </div>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                Gross:{' '}
                                                <strong>
                                                    {vo.currency}{' '}
                                                    {vo.gross_amount.toFixed(2)}
                                                </strong>{' '}
                                                • Store Commission (
                                                {vo.commission_rate}%):{' '}
                                                <strong>
                                                    {vo.currency}{' '}
                                                    {vo.commission_amount.toFixed(
                                                        2,
                                                    )}
                                                </strong>{' '}
                                                • Net Vendor Earning:{' '}
                                                <strong className="text-emerald-600">
                                                    {vo.currency}{' '}
                                                    {vo.vendor_earning.toFixed(
                                                        2,
                                                    )}
                                                </strong>
                                                {vo.tracking_number &&
                                                    ` • Tracking: ${vo.tracking_number}`}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            )}

            {/* TAB 5: VENDOR PAYOUTS */}
            {activeTab === 'payouts' && (
                <Card>
                    <CardHeader className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                        <div>
                            <CardTitle>
                                Marketplace Store-to-Vendor Payouts
                            </CardTitle>
                            <CardDescription>
                                Disburse vendor earnings via Bank Transfer,
                                PayPal, Stripe Connect, or Wise, or generate
                                batch payouts for all eligible sellers.
                            </CardDescription>
                        </div>
                        <Button onClick={runBatchPayouts} size="sm">
                            <RefreshCw className="mr-1.5 size-4" />
                            Generate Batch Payouts (Min {currency}{' '}
                            {settings.min_payout_amount})
                        </Button>
                    </CardHeader>
                    <CardContent>
                        {payouts.length === 0 ? (
                            <p className="py-8 text-center text-sm text-muted-foreground">
                                No payouts created yet. Vendors can request
                                payouts from their Seller Portal, or you can
                                click &ldquo;Generate Batch Payouts&rdquo;.
                            </p>
                        ) : (
                            <div className="space-y-3">
                                {payouts.map((payout) => (
                                    <div
                                        key={payout.id}
                                        className="flex flex-col justify-between gap-4 rounded-lg border p-4 lg:flex-row lg:items-center"
                                    >
                                        <div className="space-y-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="font-semibold">
                                                    {payout.reference}
                                                </span>
                                                <Badge variant="outline">
                                                    {payout.vendor_name}
                                                </Badge>
                                                <Badge
                                                    variant={
                                                        payout.status === 'paid'
                                                            ? 'default'
                                                            : payout.status ===
                                                                'rejected'
                                                              ? 'destructive'
                                                              : 'secondary'
                                                    }
                                                >
                                                    {payout.status}
                                                </Badge>
                                                <span className="text-xs text-muted-foreground">
                                                    {payout.payout_method_label}{' '}
                                                    (
                                                    {payout.destination_summary}
                                                    )
                                                </span>
                                            </div>
                                            <p className="text-xs text-muted-foreground">
                                                Gross Payout:{' '}
                                                <strong>
                                                    {payout.currency}{' '}
                                                    {payout.amount.toFixed(2)}
                                                </strong>{' '}
                                                • Processing Fee:{' '}
                                                {payout.currency}{' '}
                                                {payout.fee_amount.toFixed(2)} •
                                                Net Disbursed:{' '}
                                                <strong className="text-emerald-600">
                                                    {payout.currency}{' '}
                                                    {payout.net_amount.toFixed(
                                                        2,
                                                    )}
                                                </strong>
                                                {payout.transaction_reference &&
                                                    ` • Bank/TX Ref: ${payout.transaction_reference}`}
                                            </p>
                                        </div>

                                        {payout.status !== 'paid' &&
                                            payout.status !== 'rejected' && (
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <Input
                                                        className="w-48 text-xs"
                                                        placeholder="Bank / Wire TX Ref..."
                                                        value={
                                                            txRefs[payout.id] ??
                                                            ''
                                                        }
                                                        onChange={(e) =>
                                                            setTxRefs(
                                                                (prev) => ({
                                                                    ...prev,
                                                                    [payout.id]:
                                                                        e.target
                                                                            .value,
                                                                }),
                                                            )
                                                        }
                                                    />
                                                    <Button
                                                        size="sm"
                                                        onClick={() =>
                                                            processPayout(
                                                                payout,
                                                                'paid',
                                                            )
                                                        }
                                                    >
                                                        <CheckCircle2 className="mr-1 size-3.5" />
                                                        Mark Paid
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="destructive"
                                                        onClick={() =>
                                                            processPayout(
                                                                payout,
                                                                'rejected',
                                                            )
                                                        }
                                                    >
                                                        Reject & Refund Balance
                                                    </Button>
                                                </div>
                                            )}
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            )}

            {/* TAB 6: MARKETPLACE SETTINGS */}
            {activeTab === 'settings' && (
                <Card>
                    <CardHeader>
                        <CardTitle>
                            Multi-Vendor Marketplace Configuration
                        </CardTitle>
                        <CardDescription>
                            Control vendor onboarding rules, KYB verification
                            requirements, marketplace commission rates, and
                            store-to-vendor payout policies.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submitSettings} className="space-y-6">
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                <div className="space-y-1.5">
                                    <Label>
                                        Default Marketplace Commission (%)
                                    </Label>
                                    <Input
                                        type="number"
                                        min={0}
                                        max={90}
                                        value={
                                            settingsForm.data
                                                .default_commission_rate
                                        }
                                        onChange={(e) =>
                                            settingsForm.setData(
                                                'default_commission_rate',
                                                Number(e.target.value),
                                            )
                                        }
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label>Vendor Payout Mode</Label>
                                    <Select
                                        value={settingsForm.data.payout_mode}
                                        onValueChange={(v) =>
                                            settingsForm.setData(
                                                'payout_mode',
                                                v as SettingsData['payout_mode'],
                                            )
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="vendor_request">
                                                Vendor Payout Request
                                                (On-Demand)
                                            </SelectItem>
                                            <SelectItem value="automatic_on_order">
                                                Automatic Payout When Order
                                                Placed
                                            </SelectItem>
                                            <SelectItem value="admin_batch">
                                                Admin Scheduled Batch Disbursal
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-1.5">
                                    <Label>
                                        Minimum Payout Threshold ({currency})
                                    </Label>
                                    <Input
                                        type="number"
                                        min={1}
                                        max={100000}
                                        value={
                                            settingsForm.data.min_payout_amount
                                        }
                                        onChange={(e) =>
                                            settingsForm.setData(
                                                'min_payout_amount',
                                                Number(e.target.value),
                                            )
                                        }
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label>Payout Transfer Fee (%)</Label>
                                    <Input
                                        type="number"
                                        min={0}
                                        max={20}
                                        value={
                                            settingsForm.data.payout_fee_percent
                                        }
                                        onChange={(e) =>
                                            settingsForm.setData(
                                                'payout_fee_percent',
                                                Number(e.target.value),
                                            )
                                        }
                                    />
                                </div>
                            </div>

                            <div className="grid gap-3 sm:grid-cols-2">
                                <label className="flex items-center gap-2.5 rounded-lg border p-3 text-sm">
                                    <Checkbox
                                        checked={settingsForm.data.enabled}
                                        onCheckedChange={(v) =>
                                            settingsForm.setData(
                                                'enabled',
                                                Boolean(v),
                                            )
                                        }
                                    />
                                    <span>Enable Multi-Vendor Marketplace</span>
                                </label>
                                <label className="flex items-center gap-2.5 rounded-lg border p-3 text-sm">
                                    <Checkbox
                                        checked={
                                            settingsForm.data
                                                .allow_vendor_registration
                                        }
                                        onCheckedChange={(v) =>
                                            settingsForm.setData(
                                                'allow_vendor_registration',
                                                Boolean(v),
                                            )
                                        }
                                    />
                                    <span>
                                        Allow Public Vendor Registration & KYB
                                        Onboarding
                                    </span>
                                </label>
                                <label className="flex items-center gap-2.5 rounded-lg border p-3 text-sm">
                                    <Checkbox
                                        checked={
                                            settingsForm.data
                                                .require_business_verification
                                        }
                                        onCheckedChange={(v) =>
                                            settingsForm.setData(
                                                'require_business_verification',
                                                Boolean(v),
                                            )
                                        }
                                    />
                                    <span>
                                        Require KYB Business Verification Before
                                        Publishing Vendor Products
                                    </span>
                                </label>
                                <label className="flex items-center gap-2.5 rounded-lg border p-3 text-sm">
                                    <Checkbox
                                        checked={
                                            settingsForm.data
                                                .auto_approve_vendors
                                        }
                                        onCheckedChange={(v) =>
                                            settingsForm.setData(
                                                'auto_approve_vendors',
                                                Boolean(v),
                                            )
                                        }
                                    />
                                    <span>
                                        Auto-Approve New Vendor Applications
                                    </span>
                                </label>
                                <label className="flex items-center gap-2.5 rounded-lg border p-3 text-sm">
                                    <Checkbox
                                        checked={
                                            settingsForm.data
                                                .auto_approve_products
                                        }
                                        onCheckedChange={(v) =>
                                            settingsForm.setData(
                                                'auto_approve_products',
                                                Boolean(v),
                                            )
                                        }
                                    />
                                    <span>
                                        Auto-Approve Vendor Catalog Products
                                    </span>
                                </label>
                                <label className="flex items-center gap-2.5 rounded-lg border p-3 text-sm">
                                    <Checkbox
                                        checked={
                                            settingsForm.data
                                                .show_vendor_on_cards
                                        }
                                        onCheckedChange={(v) =>
                                            settingsForm.setData(
                                                'show_vendor_on_cards',
                                                Boolean(v),
                                            )
                                        }
                                    />
                                    <span>
                                        Show Seller Badge on Storefront Product
                                        Cards
                                    </span>
                                </label>
                                <label className="flex items-center gap-2.5 rounded-lg border p-3 text-sm">
                                    <Checkbox
                                        checked={
                                            settingsForm.data
                                                .show_vendor_box_on_product
                                        }
                                        onCheckedChange={(v) =>
                                            settingsForm.setData(
                                                'show_vendor_box_on_product',
                                                Boolean(v),
                                            )
                                        }
                                    />
                                    <span>
                                        Show &ldquo;Sold & Fulfilled by
                                        Vendor&rdquo; Box on Product Detail
                                        Pages
                                    </span>
                                </label>
                            </div>

                            <Button
                                type="submit"
                                disabled={settingsForm.processing}
                            >
                                Save Marketplace Settings
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}
