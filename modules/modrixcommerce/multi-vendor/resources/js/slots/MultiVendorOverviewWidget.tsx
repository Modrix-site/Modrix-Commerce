import { Link } from '@inertiajs/react';
import {
    ArrowRight,
    BadgeCheck,
    Coins,
    Package,
    Store,
    Wallet,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

interface Props {
    stats?: {
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
    };
    currency?: string;
    dashboardUrl?: string;
}

export default function MultiVendorOverviewWidget({
    stats,
    currency = 'USD',
    dashboardUrl = '/admin/m/modrixcommerce/multi-vendor',
}: Props) {
    if (!stats) {
        return null;
    }

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between pb-2">
                <div>
                    <CardTitle className="flex items-center gap-2 text-base">
                        <Store className="size-4 text-primary" />
                        Multi-Vendor Marketplace
                    </CardTitle>
                    <CardDescription className="text-xs">
                        Vendors, KYB verification, commissions & store payouts
                    </CardDescription>
                </div>
                <Button variant="outline" size="sm" asChild>
                    <Link href={dashboardUrl}>
                        Manage
                        <ArrowRight className="ml-1 size-3.5" />
                    </Link>
                </Button>
            </CardHeader>
            <CardContent className="grid grid-cols-2 gap-3 pt-2 sm:grid-cols-4">
                <div className="rounded-lg border p-3">
                    <div className="flex items-center justify-between text-xs text-muted-foreground">
                        <span>Active Sellers</span>
                        <BadgeCheck className="size-3.5 text-emerald-600" />
                    </div>
                    <div className="mt-1 text-lg font-bold">
                        {stats.approved_vendors}
                    </div>
                    <div className="text-[11px] text-muted-foreground">
                        {stats.verified_vendors} KYB Verified
                    </div>
                </div>

                <div className="rounded-lg border p-3">
                    <div className="flex items-center justify-between text-xs text-muted-foreground">
                        <span>Vendor Products</span>
                        <Package className="size-3.5 text-primary" />
                    </div>
                    <div className="mt-1 text-lg font-bold">
                        {stats.vendor_products}
                    </div>
                    <div className="text-[11px] text-muted-foreground">
                        {stats.pending_products} pending review
                    </div>
                </div>

                <div className="rounded-lg border p-3">
                    <div className="flex items-center justify-between text-xs text-muted-foreground">
                        <span>Store Commission</span>
                        <Coins className="size-3.5 text-emerald-600" />
                    </div>
                    <div className="mt-1 text-lg font-bold">
                        {currency} {stats.marketplace_commission.toFixed(2)}
                    </div>
                    <div className="text-[11px] text-muted-foreground">
                        GMV: {currency} {stats.gross_sales.toFixed(2)}
                    </div>
                </div>

                <div className="rounded-lg border p-3">
                    <div className="flex items-center justify-between text-xs text-muted-foreground">
                        <span>Vendor Payouts</span>
                        <Wallet className="size-3.5 text-primary" />
                    </div>
                    <div className="mt-1 text-lg font-bold">
                        {currency} {stats.paid_payouts_amount.toFixed(2)}
                    </div>
                    <div className="flex items-center gap-1 text-[11px] text-muted-foreground">
                        {stats.pending_payouts_count > 0 ? (
                            <Badge
                                variant="secondary"
                                className="h-4 px-1 text-[10px]"
                            >
                                {stats.pending_payouts_count} pending
                            </Badge>
                        ) : (
                            <span>All settled</span>
                        )}
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
