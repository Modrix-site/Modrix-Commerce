import { Link } from '@inertiajs/react';
import {
    ArrowRight,
    BadgeCheck,
    Building2,
    MapPin,
    Package,
    Star,
    Store,
} from 'lucide-react';
import { ProductGrid } from '@/components/storefront/product-card';
import { Button } from '@/components/ui/button';
import type { ProductCard as ProductCardData } from '@/types';

interface VendorItem {
    id: number;
    store_name: string;
    slug: string;
    tagline: string | null;
    city: string | null;
    country_code: string | null;
    is_verified: boolean;
    rating: number;
    products_count: number;
    url: string;
}

interface Props {
    vendors?: VendorItem[];
    featuredProducts?: ProductCardData[];
    allowRegistration?: boolean;
    catalogUrl?: string;
    directoryUrl?: string;
    registerUrl?: string;
}

export default function FeaturedVendorsBlock({
    vendors = [],
    featuredProducts = [],
    allowRegistration = true,
    catalogUrl = '/m/modrixcommerce/multi-vendor/catalog',
    directoryUrl = '/m/modrixcommerce/multi-vendor/vendors',
    registerUrl = '/m/modrixcommerce/multi-vendor/register',
}: Props) {
    if (
        vendors.length === 0 &&
        featuredProducts.length === 0 &&
        !allowRegistration
    ) {
        return null;
    }

    return (
        <section className="my-10 space-y-6 rounded-2xl border bg-card/60 p-6 sm:p-8">
            <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div className="space-y-1">
                    <div className="inline-flex items-center gap-1.5 text-xs font-semibold tracking-wider text-primary uppercase">
                        <Store className="size-3.5" />
                        Multi-Vendor Marketplace
                    </div>
                    <h2 className="text-2xl font-bold tracking-tight">
                        Featured Marketplace Sellers &amp; Catalog
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Shop curated collections from verified independent
                        brands and official store partners.
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Button variant="outline" size="sm" asChild>
                        <Link href={catalogUrl}>
                            <Package className="mr-1.5 size-3.5" />
                            All Marketplace Products
                        </Link>
                    </Button>
                    <Button variant="outline" size="sm" asChild>
                        <Link href={directoryUrl}>
                            Browse All Sellers
                            <ArrowRight className="ml-1.5 size-3.5" />
                        </Link>
                    </Button>
                    {allowRegistration && (
                        <Button size="sm" asChild>
                            <Link href={registerUrl}>
                                <Building2 className="mr-1.5 size-3.5" />
                                Become a Seller
                            </Link>
                        </Button>
                    )}
                </div>
            </div>

            {vendors.length > 0 && (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {vendors.map((v) => (
                        <Link
                            key={v.id}
                            href={v.url}
                            className="group flex flex-col justify-between rounded-xl border bg-background p-4 transition hover:border-primary/50 hover:shadow-sm"
                        >
                            <div className="flex items-start gap-3">
                                <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 font-bold text-primary">
                                    {v.store_name.slice(0, 2).toUpperCase()}
                                </div>
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-1.5">
                                        <span className="truncate font-semibold group-hover:text-primary">
                                            {v.store_name}
                                        </span>
                                        {v.is_verified && (
                                            <BadgeCheck
                                                className="size-4 shrink-0 text-emerald-600"
                                                aria-label="Verified Business"
                                            />
                                        )}
                                    </div>
                                    {v.tagline && (
                                        <p className="line-clamp-1 text-xs text-muted-foreground">
                                            {v.tagline}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div className="mt-4 flex items-center justify-between border-t pt-2.5 text-xs text-muted-foreground">
                                <div className="flex items-center gap-3">
                                    <span className="flex items-center gap-1 font-medium text-foreground">
                                        <Star className="size-3.5 fill-amber-400 text-amber-500" />
                                        {v.rating.toFixed(1)}
                                    </span>
                                    <span className="flex items-center gap-1">
                                        <Package className="size-3.5" />
                                        {v.products_count} items
                                    </span>
                                    {v.city && (
                                        <span className="flex items-center gap-0.5">
                                            <MapPin className="size-3" />
                                            {v.city}
                                        </span>
                                    )}
                                </div>
                                <span className="font-medium text-primary">
                                    Shop Store →
                                </span>
                            </div>
                        </Link>
                    ))}
                </div>
            )}

            {featuredProducts.length > 0 && (
                <div className="space-y-4 border-t pt-6">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <h3 className="text-base font-semibold tracking-tight">
                            Marketplace Catalog Highlights
                        </h3>
                        <Link
                            href={catalogUrl}
                            className="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                        >
                            Explore full catalog &amp; filters
                            <ArrowRight className="size-3.5" />
                        </Link>
                    </div>
                    <ProductGrid products={featuredProducts} />
                </div>
            )}
        </section>
    );
}
