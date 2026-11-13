import { Link } from '@inertiajs/react';
import { ArrowRight, BadgeCheck, Flame, Package, Store } from 'lucide-react';
import { Button } from '@/components/ui/button';

interface Props {
    sellersCount?: number;
    verifiedSellersCount?: number;
    vendorProductsCount?: number;
    catalogUrl?: string;
    directoryUrl?: string;
    registerUrl?: string;
    allowRegistration?: boolean;
}

export default function MarketplaceCatalogStripSlot({
    sellersCount = 0,
    verifiedSellersCount = 0,
    vendorProductsCount = 0,
    catalogUrl = '/m/modrixcommerce/multi-vendor/catalog',
    directoryUrl = '/m/modrixcommerce/multi-vendor/vendors',
}: Props) {
    if (sellersCount === 0 && vendorProductsCount === 0) {
        return null;
    }

    return (
        <div className="mb-6 flex flex-col gap-3 rounded-xl border bg-card/90 p-3.5 shadow-xs sm:flex-row sm:items-center sm:justify-between sm:px-5">
            <div className="flex flex-wrap items-center gap-2.5 text-xs">
                <span className="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-2.5 py-1 font-semibold text-primary">
                    <Store className="size-3.5" />
                    Marketplace Catalog
                </span>
                <span className="text-muted-foreground">
                    Filter across{' '}
                    <strong className="text-foreground">
                        {sellersCount} active seller stores
                    </strong>
                    {verifiedSellersCount > 0 && (
                        <>
                            {' '}
                            (
                            <strong className="text-emerald-700 dark:text-emerald-400">
                                {verifiedSellersCount} verified businesses
                            </strong>
                            )
                        </>
                    )}{' '}
                    and{' '}
                    <strong className="text-foreground">
                        {vendorProductsCount} seller listings
                    </strong>
                    .
                </span>
            </div>

            <div className="flex flex-wrap items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    className="h-8 text-xs"
                    asChild
                >
                    <Link href={`${catalogUrl}?verified_only=1`}>
                        <BadgeCheck className="mr-1.5 size-3.5 text-emerald-600" />
                        Verified Sellers
                    </Link>
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    className="h-8 text-xs"
                    asChild
                >
                    <Link href={`${catalogUrl}?on_sale=1`}>
                        <Flame className="mr-1.5 size-3.5 text-rose-600" />
                        Marketplace Deals
                    </Link>
                </Button>
                <Button size="sm" className="h-8 text-xs" asChild>
                    <Link href={catalogUrl}>
                        <Package className="mr-1.5 size-3.5" />
                        All Products &amp; Sellers
                        <ArrowRight className="ml-1 size-3.5" />
                    </Link>
                </Button>
                <Link
                    href={directoryUrl}
                    className="px-2 text-xs font-medium text-muted-foreground hover:text-foreground hover:underline"
                >
                    Sellers
                </Link>
            </div>
        </div>
    );
}
