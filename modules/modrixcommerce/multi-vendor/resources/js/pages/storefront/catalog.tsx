import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    BadgeCheck,
    Building2,
    Flame,
    Package,
    Star,
    Store,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    MarketplaceThemeCatalog,
    type MarketplaceCatalogStats,
    type MarketplaceDepartmentFacet,
    type MarketplaceProductVendorMeta,
    type MarketplaceVendorFacet,
    type ExtendedListingFilters,
} from '../../components/MarketplaceThemeCatalog';
import type { ThemeSectionData } from '@/components/storefront/theme-sections';
import type { ListingProps } from '@/pages/storefront/listing';

interface Props extends Omit<ListingProps, 'filters'> {
    filters?: ExtendedListingFilters;
    themeCollectionSections?: ThemeSectionData[];
    departments?: MarketplaceDepartmentFacet[];
    filterVendors?: MarketplaceVendorFacet[];
    productVendors?: Record<number, MarketplaceProductVendorMeta>;
    catalogStats?: MarketplaceCatalogStats;
    activeCategory?: MarketplaceDepartmentFacet | null;
    catalogUrl: string;
    vendorsDirectoryUrl: string;
    allowRegistration: boolean;
    registerUrl: string;
    loginUrl: string;
    dashboardUrl: string;
}

export default function MarketplaceAllProductsCatalogPage(props: Props) {
    const {
        title,
        catalogStats,
        filterVendors = [],
        catalogUrl,
        vendorsDirectoryUrl,
        allowRegistration,
        registerUrl,
        loginUrl,
        dashboardUrl,
    } = props;

    return (
        <div className="mx-auto max-w-7xl space-y-6 py-2">
            <Head title={`${title} — Marketplace`} />

            {/* Amazon-Style Marketplace Hero Banner using Theme Colors */}
            <div className="flex flex-col justify-between gap-5 rounded-2xl border bg-card p-5 shadow-xs sm:p-6 lg:flex-row lg:items-center">
                <div className="max-w-3xl space-y-2">
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge
                            variant="secondary"
                            className="gap-1 font-medium"
                        >
                            <Store className="size-3.5 text-primary" />
                            Multi-Vendor Marketplace
                        </Badge>
                        {catalogStats && (
                            <span className="text-xs text-muted-foreground">
                                {catalogStats.total_products} products ·{' '}
                                {catalogStats.sellers_count} active seller
                                stores
                                {catalogStats.deals_count > 0 &&
                                    ` · ${catalogStats.deals_count} today's deals`}
                            </span>
                        )}
                    </div>
                    <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">
                        {title}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Compare products across verified business sellers and
                        direct store inventory — filter by department, seller
                        rating, deals, and price inside your active storefront
                        theme.
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Button variant="outline" size="sm" asChild>
                        <Link href={`${catalogUrl}?on_sale=1`}>
                            <Flame className="mr-1.5 size-3.5 text-rose-600" />
                            Today&apos;s Deals
                        </Link>
                    </Button>
                    <Button variant="outline" size="sm" asChild>
                        <Link href={vendorsDirectoryUrl}>
                            <Store className="mr-1.5 size-3.5" />
                            Sellers Directory
                        </Link>
                    </Button>
                    {allowRegistration && (
                        <Button size="sm" asChild>
                            <Link href={registerUrl}>
                                <Building2 className="mr-1.5 size-3.5" />
                                Sell on Marketplace
                            </Link>
                        </Button>
                    )}
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={dashboardUrl}>Seller Portal</Link>
                    </Button>
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={loginUrl}>Seller Login</Link>
                    </Button>
                </div>
            </div>

            {/* Amazon-Style Storefront Seller Quick Switcher Strip */}
            {filterVendors.length > 0 && (
                <div className="space-y-2.5">
                    <div className="flex items-center justify-between text-xs">
                        <span className="font-semibold tracking-wider text-muted-foreground uppercase">
                            Shop Featured Verified Seller Storefronts
                        </span>
                        <Link
                            href={vendorsDirectoryUrl}
                            className="inline-flex items-center gap-1 font-medium text-primary hover:underline"
                        >
                            View all sellers
                            <ArrowRight className="size-3" />
                        </Link>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                        {filterVendors.slice(0, 4).map((vendor) => (
                            <div
                                key={vendor.id}
                                className="flex items-center justify-between gap-3 rounded-xl border bg-card p-3 transition hover:border-primary/40"
                            >
                                <div className="min-w-0">
                                    <div className="flex items-center gap-1">
                                        <Link
                                            href={vendor.url}
                                            className="truncate text-sm font-semibold hover:text-primary hover:underline"
                                        >
                                            {vendor.store_name}
                                        </Link>
                                        {vendor.is_verified && (
                                            <BadgeCheck
                                                className="size-3.5 shrink-0 text-emerald-600"
                                                aria-label="Verified Business"
                                            />
                                        )}
                                    </div>
                                    <div className="mt-0.5 flex items-center gap-2 text-[11px] text-muted-foreground">
                                        <span className="inline-flex items-center gap-0.5 font-medium text-foreground">
                                            <Star className="size-3 fill-amber-400 text-amber-500" />
                                            {vendor.rating.toFixed(1)}
                                        </span>
                                        <span className="inline-flex items-center gap-0.5">
                                            <Package className="size-3" />
                                            {vendor.products_count} items
                                        </span>
                                    </div>
                                </div>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="h-7 shrink-0 px-2 text-xs"
                                    asChild
                                >
                                    <Link href={vendor.url}>Store</Link>
                                </Button>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {/* Theme-Native Catalog & Amazon Filters */}
            <MarketplaceThemeCatalog
                {...props}
                pageUrl={catalogUrl}
                searchPlaceholder="Search all marketplace products, brands, SKUs & sellers..."
            />
        </div>
    );
}
