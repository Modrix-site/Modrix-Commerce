import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    BadgeCheck,
    Building2,
    Calendar,
    Flame,
    MapPin,
    Package,
    ShieldCheck,
    Star,
    Store,
} from 'lucide-react';
import type { ThemeSectionData } from '@/components/storefront/theme-sections';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { ListingProps } from '@/pages/storefront/listing';
import {
    MarketplaceThemeCatalog,
    type ExtendedListingFilters,
    type MarketplaceCatalogStats,
    type MarketplaceDepartmentFacet,
    type MarketplaceProductVendorMeta,
} from '../../components/MarketplaceThemeCatalog';

interface VendorProfile {
    id: number;
    store_name: string;
    slug: string;
    tagline: string | null;
    description: string | null;
    logo_url: string | null;
    banner_url: string | null;
    city: string | null;
    country_code: string | null;
    is_verified: boolean;
    business_type_label: string;
    legal_business_name: string | null;
    rating: number;
    joined_at: string | null;
}

interface Props extends Omit<ListingProps, 'filters'> {
    filters?: ExtendedListingFilters;
    vendor: VendorProfile;
    themeCollectionSections?: ThemeSectionData[];
    departments?: MarketplaceDepartmentFacet[];
    productVendors?: Record<number, MarketplaceProductVendorMeta>;
    catalogStats?: MarketplaceCatalogStats;
    activeCategory?: MarketplaceDepartmentFacet | null;
    vendorsDirectoryUrl: string;
    catalogUrl: string;
}

export default function VendorStorefrontPage(props: Props) {
    const { vendor, catalogStats, vendorsDirectoryUrl, catalogUrl, filters } =
        props;
    const storeUrl = `/m/modrixcommerce/multi-vendor/vendors/${vendor.slug}`;

    return (
        <div className="mx-auto max-w-7xl space-y-6 py-2">
            <Head title={`${vendor.store_name} — Marketplace Storefront`} />

            {/* Breadcrumb Top Nav */}
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                    <Button
                        variant="ghost"
                        size="sm"
                        className="h-8 px-2"
                        asChild
                    >
                        <Link href={catalogUrl}>
                            <ArrowLeft className="mr-1.5 size-3.5" />
                            All Marketplace Products
                        </Link>
                    </Button>
                    <span>/</span>
                    <Link
                        href={vendorsDirectoryUrl}
                        className="hover:text-foreground hover:underline"
                    >
                        Sellers Directory
                    </Link>
                    <span>/</span>
                    <span className="font-medium text-foreground">
                        {vendor.store_name}
                    </span>
                </div>

                {vendor.is_verified && (
                    <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-3 py-1 text-xs font-medium text-emerald-700 dark:text-emerald-400">
                        <ShieldCheck className="size-3.5" />
                        KYB Verified Marketplace Storefront
                    </span>
                )}
            </div>

            {/* Amazon Brand Storefront Banner & Seller Header */}
            <div className="overflow-hidden rounded-2xl border bg-card shadow-xs">
                {vendor.banner_url ? (
                    <div
                        className="h-36 bg-cover bg-center sm:h-44"
                        style={{
                            backgroundImage: `linear-gradient(to top, rgba(0,0,0,0.45), rgba(0,0,0,0.08)), url(${vendor.banner_url})`,
                        }}
                    />
                ) : (
                    <div className="h-28 bg-gradient-to-r from-primary/15 via-primary/5 to-muted" />
                )}

                <div className="px-6 pb-5">
                    <div className="-mt-10 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                        <div className="flex items-end gap-4">
                            {vendor.logo_url ? (
                                <img
                                    src={vendor.logo_url}
                                    alt={vendor.store_name}
                                    className="size-20 rounded-2xl border-4 border-background bg-card object-cover shadow-sm"
                                />
                            ) : (
                                <div className="flex size-20 items-center justify-center rounded-2xl border-4 border-background bg-primary text-2xl font-bold text-primary-foreground shadow-sm">
                                    {vendor.store_name
                                        .slice(0, 2)
                                        .toUpperCase()}
                                </div>
                            )}

                            <div className="pt-2">
                                <div className="flex flex-wrap items-center gap-2">
                                    <h1 className="text-2xl font-bold tracking-tight">
                                        {vendor.store_name}
                                    </h1>
                                    {vendor.is_verified && (
                                        <Badge className="bg-emerald-600 text-white">
                                            <BadgeCheck className="mr-1 size-3.5" />
                                            Verified Business
                                        </Badge>
                                    )}
                                </div>
                                {vendor.tagline && (
                                    <p className="text-sm text-muted-foreground">
                                        {vendor.tagline}
                                    </p>
                                )}
                            </div>
                        </div>

                        <div className="flex flex-wrap items-center gap-4 text-xs text-muted-foreground">
                            <span className="flex items-center gap-1 font-medium text-foreground">
                                <Star className="size-4 fill-amber-400 text-amber-500" />
                                {vendor.rating.toFixed(1)} Seller Rating
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
                                    {vendor.legal_business_name} (
                                    {vendor.business_type_label})
                                </span>
                            )}
                            {vendor.joined_at && (
                                <span className="flex items-center gap-1">
                                    <Calendar className="size-3.5" />
                                    Joined {vendor.joined_at}
                                </span>
                            )}
                        </div>
                    </div>

                    {vendor.description && (
                        <p className="mt-4 max-w-3xl text-sm text-muted-foreground">
                            {vendor.description}
                        </p>
                    )}

                    {/* Amazon Storefront Sub-navigation Tabs */}
                    <div className="mt-5 flex flex-wrap items-center gap-2 border-t pt-3.5 text-xs font-medium">
                        <Link
                            href={storeUrl}
                            className={`inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 transition ${
                                !filters?.on_sale && !filters?.in_stock
                                    ? 'bg-primary text-primary-foreground'
                                    : 'border bg-background hover:bg-muted'
                            }`}
                        >
                            <Package className="size-3.5" />
                            Store Catalog ({catalogStats?.total_products ?? 0})
                        </Link>
                        <Link
                            href={`${storeUrl}?on_sale=1`}
                            className={`inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 transition ${
                                filters?.on_sale
                                    ? 'bg-rose-600 text-white'
                                    : 'border bg-background hover:bg-muted'
                            }`}
                        >
                            <Flame className="size-3.5" />
                            Seller Deals ({catalogStats?.deals_count ?? 0})
                        </Link>
                        <Link
                            href={`${storeUrl}?in_stock=1`}
                            className={`inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 transition ${
                                filters?.in_stock && !filters?.on_sale
                                    ? 'bg-primary text-primary-foreground'
                                    : 'border bg-background hover:bg-muted'
                            }`}
                        >
                            Ready to Ship
                        </Link>
                        <Link
                            href={catalogUrl}
                            className="ml-auto inline-flex items-center gap-1.5 text-primary hover:underline"
                        >
                            <Store className="size-3.5" />
                            Browse All Marketplace Sellers
                        </Link>
                    </div>
                </div>
            </div>

            {/* Active Theme Product Catalog Scoped to This Seller */}
            <MarketplaceThemeCatalog
                {...props}
                pageUrl={storeUrl}
                hideVendorFilter
                searchPlaceholder={`Search products inside ${vendor.store_name}...`}
            />
        </div>
    );
}
