import { Link } from '@inertiajs/react';
import {
    ArrowRight,
    BadgeCheck,
    Building2,
    MapPin,
    Package,
    ShieldCheck,
    Star,
    Store,
    Truck,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

interface Props {
    vendor?: {
        id: number;
        store_name: string;
        slug: string;
        tagline: string | null;
        city: string | null;
        country_code: string | null;
        is_verified: boolean;
        legal_business_name: string | null;
        rating: number;
        products_count: number;
        store_url: string;
    };
    catalogUrl?: string;
    directoryUrl?: string;
}

export default function ProductVendorBoxSlot({
    vendor,
    catalogUrl = '/m/modrixcommerce/multi-vendor/catalog',
    directoryUrl,
}: Props) {
    if (!vendor) {
        return null;
    }

    return (
        <div className="my-4 space-y-3 rounded-xl border bg-card/85 p-4 shadow-xs">
            {/* Amazon-style Buy Box Seller & Fulfillment Attribution */}
            <div className="grid gap-1.5 rounded-lg border bg-muted/40 p-3 text-xs">
                <div className="flex items-center justify-between gap-2">
                    <span className="text-muted-foreground">Ships from</span>
                    <span className="inline-flex items-center gap-1 font-medium text-foreground">
                        <Truck className="size-3.5 text-primary" />
                        Marketplace Direct Dispatch
                    </span>
                </div>
                <div className="flex items-center justify-between gap-2">
                    <span className="text-muted-foreground">Sold by</span>
                    <Link
                        href={vendor.store_url}
                        className="inline-flex items-center gap-1 font-semibold text-primary hover:underline"
                    >
                        <span>{vendor.store_name}</span>
                        {vendor.is_verified && (
                            <BadgeCheck className="size-3.5 text-emerald-600" />
                        )}
                    </Link>
                </div>
            </div>

            <div className="flex items-start justify-between gap-3">
                <div className="flex items-center gap-3">
                    <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 font-bold text-primary">
                        {vendor.store_name.slice(0, 2).toUpperCase()}
                    </div>
                    <div>
                        <div className="flex flex-wrap items-center gap-1.5">
                            <Link
                                href={vendor.store_url}
                                className="font-semibold hover:text-primary hover:underline"
                            >
                                {vendor.store_name}
                            </Link>
                            {vendor.is_verified && (
                                <Badge className="h-5 bg-emerald-600 px-1.5 text-[10px] text-white">
                                    <ShieldCheck className="mr-0.5 size-3" />
                                    Verified Business
                                </Badge>
                            )}
                        </div>
                        {vendor.tagline && (
                            <p className="text-xs text-muted-foreground">
                                {vendor.tagline}
                            </p>
                        )}
                    </div>
                </div>

                <Button variant="outline" size="sm" asChild>
                    <Link href={vendor.store_url}>
                        Visit Store
                        <ArrowRight className="ml-1 size-3.5" />
                    </Link>
                </Button>
            </div>

            <div className="flex flex-wrap items-center gap-4 border-t pt-2.5 text-xs text-muted-foreground">
                <span className="flex items-center gap-1 font-medium text-foreground">
                    <Star className="size-3.5 fill-amber-400 text-amber-500" />
                    {vendor.rating.toFixed(1)} Seller Rating
                </span>
                <span className="flex items-center gap-1">
                    <Package className="size-3.5" />
                    {vendor.products_count} catalog items
                </span>
                {(vendor.city || vendor.country_code) && (
                    <span className="flex items-center gap-1">
                        <MapPin className="size-3.5" />
                        {[vendor.city, vendor.country_code]
                            .filter(Boolean)
                            .join(', ')}
                    </span>
                )}
                {vendor.legal_business_name && (
                    <span className="flex items-center gap-1">
                        <Building2 className="size-3.5" />
                        {vendor.legal_business_name}
                    </span>
                )}
                <div className="ml-auto flex items-center gap-3">
                    <Link
                        href={catalogUrl}
                        className="flex items-center gap-1 font-medium text-primary hover:underline"
                    >
                        Marketplace Catalog
                    </Link>
                    {directoryUrl && (
                        <Link
                            href={directoryUrl}
                            className="flex items-center gap-1 font-medium text-muted-foreground hover:text-foreground hover:underline"
                        >
                            <Store className="size-3" />
                            All Sellers
                        </Link>
                    )}
                </div>
            </div>
        </div>
    );
}
