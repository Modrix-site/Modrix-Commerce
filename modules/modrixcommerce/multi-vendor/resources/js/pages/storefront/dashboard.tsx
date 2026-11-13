import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    BadgeCheck,
    Building2,
    Coins,
    ExternalLink,
    FileCheck2,
    LogOut,
    Package,
    Plus,
    ShoppingBag,
    Store,
    Truck,
    Wallet,
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
import { Textarea } from '@/components/ui/textarea';

interface SellerVendor {
    id: number;
    store_name: string;
    slug: string;
    tagline: string | null;
    email: string;
    phone: string | null;
    status: string;
    verification_status: string;
    business_type: string;
    legal_business_name: string | null;
    tax_id: string | null;
    trade_license_number: string | null;
    identity_document_ref: string | null;
    business_address: string | null;
    city: string | null;
    country_code: string | null;
    rejection_reason: string | null;
    effective_commission_rate: number;
    payout_method: string;
    auto_payout_enabled: boolean;
    bank_name: string | null;
    bank_account_name: string | null;
    bank_account_number: string | null;
    bank_swift_code: string | null;
    payout_email: string | null;
    total_sales_amount: number;
    total_commission_amount: number;
    available_balance: number;
    paid_out_total: number;
    storefront_url: string;
}

interface SellerProduct {
    id: number;
    product_id: number;
    name: string;
    slug: string | null;
    sku: string;
    price_formatted: string;
    stock: number;
    approval_status: string;
    rejection_note: string | null;
}

interface SellerOrder {
    id: number;
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

interface SellerPayout {
    id: number;
    reference: string;
    currency: string;
    amount: number;
    fee_amount: number;
    net_amount: number;
    payout_method_label: string;
    destination_summary: string | null;
    status: string;
    transaction_reference: string | null;
    created_at: string | null;
}

interface Props {
    vendor: SellerVendor;
    products: SellerProduct[];
    orders: SellerOrder[];
    payouts: SellerPayout[];
    categories: Array<{ id: number; name: string }>;
    businessTypes: Record<string, string>;
    payoutMethods: Record<string, string>;
    minPayoutAmount: number;
    payoutMode: string;
    currency: string;
    logoutUrl?: string;
}

type SellerTab = 'catalog' | 'orders' | 'payouts' | 'verification';

export default function VendorSellerPortal({
    vendor,
    products,
    orders,
    payouts,
    categories,
    businessTypes,
    payoutMethods,
    minPayoutAmount,
    currency,
    logoutUrl = '/m/modrixcommerce/multi-vendor/logout',
}: Props) {
    const [tab, setTab] = useState<SellerTab>('catalog');
    const [showNewProduct, setShowNewProduct] = useState(false);
    const [trackingInputs, setTrackingInputs] = useState<
        Record<number, string>
    >({});

    const productForm = useForm({
        name: '',
        sku: '',
        price: '',
        stock: '25',
        short_description: '',
        description: '',
        category_ids: categories[0]?.id ? [categories[0].id] : ([] as number[]),
    });

    const payoutRequestForm = useForm({
        amount:
            vendor.available_balance > 0
                ? String(vendor.available_balance)
                : '',
        notes: '',
    });

    const payoutSettingsForm = useForm({
        payout_method: vendor.payout_method || 'bank_transfer',
        auto_payout_enabled: Boolean(vendor.auto_payout_enabled),
        bank_name: vendor.bank_name || '',
        bank_account_name: vendor.bank_account_name || '',
        bank_account_number: vendor.bank_account_number || '',
        bank_swift_code: vendor.bank_swift_code || '',
        payout_email: vendor.payout_email || '',
    });

    const kybForm = useForm({
        business_type: vendor.business_type || 'llc',
        legal_business_name: vendor.legal_business_name || '',
        tax_id: vendor.tax_id || '',
        trade_license_number: vendor.trade_license_number || '',
        identity_document_ref: vendor.identity_document_ref || '',
        business_address: vendor.business_address || '',
        city: vendor.city || '',
        country_code: vendor.country_code || 'AE',
    });

    const submitProduct = (e: React.FormEvent) => {
        e.preventDefault();
        productForm.post('/m/modrixcommerce/multi-vendor/dashboard/products', {
            preserveScroll: true,
            onSuccess: () => {
                productForm.reset();
                setShowNewProduct(false);
            },
        });
    };

    const submitPayoutRequest = (e: React.FormEvent) => {
        e.preventDefault();
        payoutRequestForm.post(
            '/m/modrixcommerce/multi-vendor/dashboard/payouts/request',
            { preserveScroll: true },
        );
    };

    const submitPayoutSettings = (e: React.FormEvent) => {
        e.preventDefault();
        payoutSettingsForm.put(
            '/m/modrixcommerce/multi-vendor/dashboard/payout-settings',
            { preserveScroll: true },
        );
    };

    const submitKyb = (e: React.FormEvent) => {
        e.preventDefault();
        kybForm.post('/m/modrixcommerce/multi-vendor/dashboard/verification', {
            preserveScroll: true,
        });
    };

    const updateFulfillment = (order: SellerOrder, status: string) => {
        router.put(
            `/m/modrixcommerce/multi-vendor/dashboard/orders/${order.id}/fulfillment`,
            {
                fulfillment_status: status,
                tracking_number:
                    trackingInputs[order.id] ?? order.tracking_number ?? '',
            },
            { preserveScroll: true },
        );
    };

    return (
        <div className="mx-auto max-w-6xl space-y-8 px-4 py-8">
            <Head title={`${vendor.store_name} — Seller Portal`} />

            {/* Top Seller Header */}
            <div className="flex flex-col justify-between gap-4 rounded-2xl border bg-card p-6 md:flex-row md:items-center">
                <div className="space-y-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <Store className="size-6 text-primary" />
                        <h1 className="text-2xl font-bold">
                            {vendor.store_name}
                        </h1>
                        <Badge
                            variant={
                                vendor.status === 'approved'
                                    ? 'default'
                                    : 'secondary'
                            }
                        >
                            Store: {vendor.status}
                        </Badge>
                        {vendor.verification_status === 'verified' ? (
                            <Badge className="bg-emerald-600 text-white">
                                <BadgeCheck className="mr-1 size-3.5" />
                                Verified Business
                            </Badge>
                        ) : (
                            <Badge variant="outline">
                                KYB: {vendor.verification_status}
                            </Badge>
                        )}
                    </div>
                    <p className="text-xs text-muted-foreground">
                        Signed in as <strong>{vendor.email}</strong> •
                        Marketplace Commission Rate:{' '}
                        <strong>{vendor.effective_commission_rate}%</strong> •
                        Payout Mode:{' '}
                        <strong>
                            {vendor.auto_payout_enabled
                                ? 'Automatic Store Payout Enabled'
                                : 'Manual Payout Request'}
                        </strong>
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Button variant="outline" size="sm" asChild>
                        <Link href={vendor.storefront_url}>
                            <ExternalLink className="mr-1.5 size-4" />
                            View Public Storefront
                        </Link>
                    </Button>
                    <Button
                        size="sm"
                        onClick={() => {
                            setTab('catalog');
                            setShowNewProduct((v) => !v);
                        }}
                    >
                        <Plus className="mr-1.5 size-4" />
                        Add New Product
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => router.post(logoutUrl)}
                    >
                        <LogOut className="mr-1.5 size-4" />
                        Sign Out
                    </Button>
                </div>
            </div>

            {/* Seller Financial KPI Cards */}
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardContent className="pt-6">
                        <span className="text-xs font-medium text-muted-foreground uppercase">
                            Gross Vendor Sales
                        </span>
                        <div className="mt-2 text-2xl font-bold">
                            {currency} {vendor.total_sales_amount.toFixed(2)}
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Marketplace Fee: {currency}{' '}
                            {vendor.total_commission_amount.toFixed(2)}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="pt-6">
                        <span className="text-xs font-medium text-muted-foreground uppercase">
                            Available for Payout
                        </span>
                        <div className="mt-2 text-2xl font-bold text-emerald-600">
                            {currency} {vendor.available_balance.toFixed(2)}
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Min Payout Threshold: {currency} {minPayoutAmount}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="pt-6">
                        <span className="text-xs font-medium text-muted-foreground uppercase">
                            Total Paid Out by Store
                        </span>
                        <div className="mt-2 text-2xl font-bold">
                            {currency} {vendor.paid_out_total.toFixed(2)}
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {payouts.length} payout record(s)
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="pt-6">
                        <span className="text-xs font-medium text-muted-foreground uppercase">
                            Catalog & Orders
                        </span>
                        <div className="mt-2 text-2xl font-bold">
                            {products.length} Products
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {orders.length} customer order items
                        </p>
                    </CardContent>
                </Card>
            </div>

            {/* Seller Tabs */}
            <div className="flex flex-wrap gap-2 border-b pb-3">
                <Button
                    variant={tab === 'catalog' ? 'default' : 'outline'}
                    size="sm"
                    onClick={() => setTab('catalog')}
                >
                    <Package className="mr-1.5 size-4" />
                    My Product Catalog ({products.length})
                </Button>
                <Button
                    variant={tab === 'orders' ? 'default' : 'outline'}
                    size="sm"
                    onClick={() => setTab('orders')}
                >
                    <ShoppingBag className="mr-1.5 size-4" />
                    Orders & Fulfillment ({orders.length})
                </Button>
                <Button
                    variant={tab === 'payouts' ? 'default' : 'outline'}
                    size="sm"
                    onClick={() => setTab('payouts')}
                >
                    <Wallet className="mr-1.5 size-4" />
                    Payouts from Marketplace ({payouts.length})
                </Button>
                <Button
                    variant={tab === 'verification' ? 'default' : 'outline'}
                    size="sm"
                    onClick={() => setTab('verification')}
                >
                    <FileCheck2 className="mr-1.5 size-4" />
                    Business Verification (KYB)
                </Button>
            </div>

            {/* TAB 1: VENDOR PRODUCT CATALOG */}
            {tab === 'catalog' && (
                <div className="space-y-6">
                    {showNewProduct && (
                        <Card className="border-primary/40">
                            <CardHeader>
                                <CardTitle>
                                    Publish New Product to Your Vendor Catalog
                                </CardTitle>
                                <CardDescription>
                                    Add a product to your marketplace store.
                                    Once approved, it appears on your vendor
                                    storefront and the main catalog.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form
                                    onSubmit={submitProduct}
                                    className="grid gap-4 sm:grid-cols-2"
                                >
                                    <div className="space-y-1.5">
                                        <Label>Product Name *</Label>
                                        <Input
                                            required
                                            value={productForm.data.name}
                                            onChange={(e) =>
                                                productForm.setData(
                                                    'name',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Handcrafted Leather Weekender Bag"
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>Vendor SKU *</Label>
                                        <Input
                                            required
                                            value={productForm.data.sku}
                                            onChange={(e) =>
                                                productForm.setData(
                                                    'sku',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="VND-BAG-001"
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>Price ({currency}) *</Label>
                                        <Input
                                            type="number"
                                            step="0.01"
                                            min="0.01"
                                            required
                                            value={productForm.data.price}
                                            onChange={(e) =>
                                                productForm.setData(
                                                    'price',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="149.00"
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>Initial Stock Quantity</Label>
                                        <Input
                                            type="number"
                                            min="0"
                                            value={productForm.data.stock}
                                            onChange={(e) =>
                                                productForm.setData(
                                                    'stock',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="space-y-1.5 sm:col-span-2">
                                        <Label>Short Summary</Label>
                                        <Input
                                            value={
                                                productForm.data
                                                    .short_description
                                            }
                                            onChange={(e) =>
                                                productForm.setData(
                                                    'short_description',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Full-grain vegetable-tanned leather with solid brass hardware."
                                        />
                                    </div>
                                    <div className="space-y-1.5 sm:col-span-2">
                                        <Label>Full Product Description</Label>
                                        <Textarea
                                            rows={3}
                                            value={productForm.data.description}
                                            onChange={(e) =>
                                                productForm.setData(
                                                    'description',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="flex justify-end gap-2 sm:col-span-2">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            onClick={() =>
                                                setShowNewProduct(false)
                                            }
                                        >
                                            Cancel
                                        </Button>
                                        <Button
                                            type="submit"
                                            disabled={productForm.processing}
                                        >
                                            Publish Product
                                        </Button>
                                    </div>
                                </form>
                            </CardContent>
                        </Card>
                    )}

                    <Card>
                        <CardHeader>
                            <CardTitle>Your Vendor Product Catalog</CardTitle>
                            <CardDescription>
                                Manage your products listed on the marketplace.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {products.length === 0 ? (
                                <p className="py-8 text-center text-sm text-muted-foreground">
                                    You haven&apos;t added any products yet.
                                    Click &ldquo;Add New Product&rdquo; above to
                                    list your first item.
                                </p>
                            ) : (
                                <div className="space-y-3">
                                    {products.map((p) => (
                                        <div
                                            key={p.id}
                                            className="flex flex-col justify-between gap-3 rounded-lg border p-3 sm:flex-row sm:items-center"
                                        >
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <span className="font-medium">
                                                        {p.name}
                                                    </span>
                                                    <Badge variant="outline">
                                                        {p.price_formatted}
                                                    </Badge>
                                                    <Badge
                                                        variant={
                                                            p.approval_status ===
                                                            'approved'
                                                                ? 'default'
                                                                : 'secondary'
                                                        }
                                                    >
                                                        {p.approval_status}
                                                    </Badge>
                                                </div>
                                                <p className="mt-0.5 text-xs text-muted-foreground">
                                                    SKU: {p.sku} • Stock:{' '}
                                                    {p.stock} units
                                                </p>
                                            </div>
                                            {p.slug && (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={`/products/${p.slug}`}
                                                    >
                                                        View Product
                                                    </Link>
                                                </Button>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            )}

            {/* TAB 2: ORDERS & FULFILLMENT */}
            {tab === 'orders' && (
                <Card>
                    <CardHeader>
                        <CardTitle>
                            Customer Order Items & Seller Fulfillment
                        </CardTitle>
                        <CardDescription>
                            Update shipment status and provide tracking numbers
                            for your products in customer orders.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {orders.length === 0 ? (
                            <p className="py-8 text-center text-sm text-muted-foreground">
                                No customer orders for your products yet.
                            </p>
                        ) : (
                            <div className="space-y-3">
                                {orders.map((o) => (
                                    <div
                                        key={o.id}
                                        className="flex flex-col justify-between gap-4 rounded-lg border p-4 lg:flex-row lg:items-center"
                                    >
                                        <div>
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="font-semibold">
                                                    Order {o.order_number}
                                                </span>
                                                <span>
                                                    • {o.product_name} ×{' '}
                                                    {o.quantity}
                                                </span>
                                                <Badge variant="secondary">
                                                    {o.fulfillment_status}
                                                </Badge>
                                                <Badge variant="outline">
                                                    Payout: {o.payout_status}
                                                </Badge>
                                            </div>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                Gross: {o.currency}{' '}
                                                {o.gross_amount.toFixed(2)} •
                                                Commission ({o.commission_rate}
                                                %): {o.currency}{' '}
                                                {o.commission_amount.toFixed(2)}{' '}
                                                •{' '}
                                                <strong className="text-emerald-600">
                                                    Your Net Earning:{' '}
                                                    {o.currency}{' '}
                                                    {o.vendor_earning.toFixed(
                                                        2,
                                                    )}
                                                </strong>
                                            </p>
                                        </div>

                                        <div className="flex flex-wrap items-center gap-2">
                                            <Input
                                                className="w-44 text-xs"
                                                placeholder="Tracking Number..."
                                                value={
                                                    trackingInputs[o.id] ??
                                                    o.tracking_number ??
                                                    ''
                                                }
                                                onChange={(e) =>
                                                    setTrackingInputs(
                                                        (prev) => ({
                                                            ...prev,
                                                            [o.id]: e.target
                                                                .value,
                                                        }),
                                                    )
                                                }
                                            />
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    updateFulfillment(
                                                        o,
                                                        'shipped',
                                                    )
                                                }
                                            >
                                                <Truck className="mr-1 size-3.5" />
                                                Mark Shipped
                                            </Button>
                                            <Button
                                                size="sm"
                                                onClick={() =>
                                                    updateFulfillment(
                                                        o,
                                                        'delivered',
                                                    )
                                                }
                                            >
                                                Delivered
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            )}

            {/* TAB 3: PAYOUTS FROM MARKETPLACE STORE */}
            {tab === 'payouts' && (
                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Coins className="size-5 text-emerald-600" />
                                Request Payout from Marketplace Store
                            </CardTitle>
                            <CardDescription>
                                Available Balance:{' '}
                                <strong>
                                    {currency}{' '}
                                    {vendor.available_balance.toFixed(2)}
                                </strong>{' '}
                                (Minimum threshold: {currency} {minPayoutAmount}
                                )
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form
                                onSubmit={submitPayoutRequest}
                                className="space-y-4"
                            >
                                <div className="space-y-1.5">
                                    <Label>Payout Amount ({currency})</Label>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        min="1"
                                        max={vendor.available_balance || 100000}
                                        value={payoutRequestForm.data.amount}
                                        onChange={(e) =>
                                            payoutRequestForm.setData(
                                                'amount',
                                                e.target.value,
                                            )
                                        }
                                        placeholder={String(
                                            vendor.available_balance,
                                        )}
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label>Payout Note (Optional)</Label>
                                    <Input
                                        value={payoutRequestForm.data.notes}
                                        onChange={(e) =>
                                            payoutRequestForm.setData(
                                                'notes',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Monthly settlement request"
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    disabled={
                                        payoutRequestForm.processing ||
                                        vendor.available_balance <= 0
                                    }
                                >
                                    Request Store Payout Now
                                </Button>
                            </form>

                            <div className="mt-6 space-y-2 border-t pt-4">
                                <h4 className="text-sm font-semibold">
                                    Payout History
                                </h4>
                                {payouts.length === 0 ? (
                                    <p className="text-xs text-muted-foreground">
                                        No payouts recorded yet.
                                    </p>
                                ) : (
                                    payouts.map((p) => (
                                        <div
                                            key={p.id}
                                            className="flex items-center justify-between rounded-md border p-2.5 text-xs"
                                        >
                                            <div>
                                                <strong>{p.reference}</strong> •{' '}
                                                {p.payout_method_label}
                                                <div className="text-muted-foreground">
                                                    Net: {p.currency}{' '}
                                                    {p.net_amount.toFixed(2)}
                                                    {p.transaction_reference &&
                                                        ` • Ref: ${p.transaction_reference}`}
                                                </div>
                                            </div>
                                            <Badge
                                                variant={
                                                    p.status === 'paid'
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {p.status}
                                            </Badge>
                                        </div>
                                    ))
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>
                                Payout Method & Auto-Payout Settings
                            </CardTitle>
                            <CardDescription>
                                Configure where the marketplace store sends your
                                payouts.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form
                                onSubmit={submitPayoutSettings}
                                className="space-y-4"
                            >
                                <div className="space-y-1.5">
                                    <Label>Payout Method</Label>
                                    <Select
                                        value={
                                            payoutSettingsForm.data
                                                .payout_method
                                        }
                                        onValueChange={(v) =>
                                            payoutSettingsForm.setData(
                                                'payout_method',
                                                v,
                                            )
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {Object.entries(payoutMethods).map(
                                                ([key, label]) => (
                                                    <SelectItem
                                                        key={key}
                                                        value={key}
                                                    >
                                                        {label}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-1.5">
                                    <Label>Bank Name</Label>
                                    <Input
                                        value={
                                            payoutSettingsForm.data.bank_name
                                        }
                                        onChange={(e) =>
                                            payoutSettingsForm.setData(
                                                'bank_name',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label>IBAN / Account Number</Label>
                                    <Input
                                        value={
                                            payoutSettingsForm.data
                                                .bank_account_number
                                        }
                                        onChange={(e) =>
                                            payoutSettingsForm.setData(
                                                'bank_account_number',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label>
                                        PayPal / Wise / Stripe Connect Email or
                                        Account ID
                                    </Label>
                                    <Input
                                        value={
                                            payoutSettingsForm.data.payout_email
                                        }
                                        onChange={(e) =>
                                            payoutSettingsForm.setData(
                                                'payout_email',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>

                                <label className="flex items-center gap-2.5 rounded-lg border p-3 text-sm">
                                    <Checkbox
                                        checked={
                                            payoutSettingsForm.data
                                                .auto_payout_enabled
                                        }
                                        onCheckedChange={(v) =>
                                            payoutSettingsForm.setData(
                                                'auto_payout_enabled',
                                                Boolean(v),
                                            )
                                        }
                                    />
                                    <span>
                                        Receive automatic payouts from the
                                        marketplace store when orders are placed
                                    </span>
                                </label>

                                <Button
                                    type="submit"
                                    disabled={payoutSettingsForm.processing}
                                >
                                    Save Payout Preferences
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            )}

            {/* TAB 4: BUSINESS VERIFICATION (KYB) */}
            {tab === 'verification' && (
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Building2 className="size-5 text-primary" />
                            Business Verification Credentials (KYB / KYC)
                        </CardTitle>
                        <CardDescription>
                            Current Verification Status:{' '}
                            <strong className="uppercase">
                                {vendor.verification_status}
                            </strong>
                            {vendor.rejection_reason &&
                                ` — Note: ${vendor.rejection_reason}`}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={submitKyb}
                            className="grid gap-4 sm:grid-cols-2"
                        >
                            <div className="space-y-1.5">
                                <Label>Business Entity Type *</Label>
                                <Select
                                    value={kybForm.data.business_type}
                                    onValueChange={(v) =>
                                        kybForm.setData('business_type', v)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {Object.entries(businessTypes).map(
                                            ([key, label]) => (
                                                <SelectItem
                                                    key={key}
                                                    value={key}
                                                >
                                                    {label}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-1.5">
                                <Label>Registered Legal Business Name *</Label>
                                <Input
                                    required
                                    value={kybForm.data.legal_business_name}
                                    onChange={(e) =>
                                        kybForm.setData(
                                            'legal_business_name',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="space-y-1.5">
                                <Label>Trade License / Company Reg. No *</Label>
                                <Input
                                    required
                                    value={kybForm.data.trade_license_number}
                                    onChange={(e) =>
                                        kybForm.setData(
                                            'trade_license_number',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="space-y-1.5">
                                <Label>Tax ID / VAT Registration Number</Label>
                                <Input
                                    value={kybForm.data.tax_id}
                                    onChange={(e) =>
                                        kybForm.setData(
                                            'tax_id',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="space-y-1.5">
                                <Label>Signatory ID / Passport Reference</Label>
                                <Input
                                    value={kybForm.data.identity_document_ref}
                                    onChange={(e) =>
                                        kybForm.setData(
                                            'identity_document_ref',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="space-y-1.5">
                                <Label>Registered Business Address</Label>
                                <Input
                                    value={kybForm.data.business_address}
                                    onChange={(e) =>
                                        kybForm.setData(
                                            'business_address',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="sm:col-span-2">
                                <Button
                                    type="submit"
                                    disabled={kybForm.processing}
                                >
                                    Submit Business Verification for Review
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}
