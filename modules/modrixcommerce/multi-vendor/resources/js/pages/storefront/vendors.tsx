import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowRight,
    BadgeCheck,
    Building2,
    MapPin,
    Package,
    Search,
    Star,
    Store,
} from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { product as productRoute } from '@/routes/storefront';
import type { ProductCard as ProductCardData } from '@/types';

interface PublicVendor {
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
    is_featured: boolean;
    rating: number;
    products_count: number;
    url: string;
    preview_products?: ProductCardData[];
}

interface Props {
    vendors: PublicVendor[];
    filters: {
        q: string;
        verified: boolean;
    };
    catalogUrl?: string;
    allowRegistration: boolean;
    registerUrl: string;
    loginUrl?: string;
    dashboardUrl: string;
}

export default function StorefrontVendorsDirectory({
    vendors,
    filters,
    catalogUrl = '/m/modrixcommerce/multi-vendor/catalog',
    allowRegistration,
    registerUrl,
    loginUrl = '/m/modrixcommerce/multi-vendor/login',
    dashboardUrl,
}: Props) {
    const [search, setSearch] = useState(filters.q || '');
    const [verifiedOnly, setVerifiedOnly] = useState(Boolean(filters.verified));

    const applyFilter = (nextQ: string, nextVerified: boolean) => {
        router.get(
            '/m/modrixcommerce/multi-vendor/vendors',
            {
                q: nextQ || undefined,
                verified: nextVerified ? '1' : undefined,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <div className="mx-auto max-w-6xl space-y-8 px-4 py-10">
            <Head title="Marketplace Sellers Directory" />

            {/* Hero Header */}
            <div className="flex flex-col justify-between gap-6 rounded-2xl border bg-card p-6 sm:p-8 md:flex-row md:items-center">
                <div className="max-w-2xl space-y-2">
                    <div className="inline-flex items-center gap-2 rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary">
                        <Store className="size-3.5" />
                        Multi-Vendor Marketplace
                    </div>
                    <h1 className="text-3xl font-bold tracking-tight">
                        Discover Verified Marketplace Sellers
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Shop directly from independent brands, verified
                        businesses, and official store partners. Every verified
                        seller passes our KYB business compliance review.
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <Button variant="secondary" asChild>
                        <Link href={catalogUrl}>
                            <Package className="mr-1.5 size-4" />
                            All Products Catalog
                        </Link>
                    </Button>
                    <Button variant="ghost" asChild>
                        <Link href={loginUrl}>Seller Login</Link>
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={dashboardUrl}>Seller Portal</Link>
                    </Button>
                    {allowRegistration && (
                        <Button asChild>
                            <Link href={registerUrl}>
                                <Building2 className="mr-1.5 size-4" />
                                Become a Seller
                            </Link>
                        </Button>
                    )}
                </div>
            </div>

            {/* Search & Filter Bar */}
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    applyFilter(search, verifiedOnly);
                }}
                className="flex flex-col gap-3 sm:flex-row sm:items-center"
            >
                <div className="relative flex-1">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search sellers by store name, specialty, or city..."
                        className="pl-9"
                    />
                </div>
                <Button
                    type="button"
                    variant={verifiedOnly ? 'default' : 'outline'}
                    onClick={() => {
                        const next = !verifiedOnly;
                        setVerifiedOnly(next);
                        applyFilter(search, next);
                    }}
                >
                    <BadgeCheck className="mr-1.5 size-4" />
                    Verified Businesses Only
                </Button>
                <Button type="submit">Search</Button>
            </form>

            {/* Vendors Grid */}
            {vendors.length === 0 ? (
                <div className="space-y-3 rounded-xl border border-dashed p-12 text-center">
                    <Store className="mx-auto size-10 text-muted-foreground" />
                    <h2 className="text-lg font-semibold">
                        No marketplace sellers found
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Try clearing your search filters or become the first
                        verified seller on our marketplace.
                    </p>
                    {allowRegistration && (
                        <Button asChild size="sm">
                            <Link href={registerUrl}>Apply as a Seller</Link>
                        </Button>
                    )}
                </div>
            ) : (
                <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    {vendors.map((vendor) => (
                        <div
                            key={vendor.id}
                            className="group flex flex-col justify-between rounded-xl border bg-card p-5 transition hover:border-primary/50 hover:shadow-md"
                        >
                            <div className="space-y-3">
                                <div className="flex items-start justify-between gap-2">
                                    <Link
                                        href={vendor.url}
                                        className="flex items-center gap-3"
                                    >
                                        <div className="flex size-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-lg font-bold text-primary">
                                            {vendor.store_name
                                                .slice(0, 2)
                                                .toUpperCase()}
                                        </div>
                                        <div>
                                            <div className="flex items-center gap-1.5">
                                                <h3 className="font-semibold group-hover:text-primary">
                                                    {vendor.store_name}
                                                </h3>
                                                {vendor.is_verified && (
                                                    <BadgeCheck
                                                        className="size-4 shrink-0 text-emerald-600"
                                                        aria-label="Verified Business"
                                                    />
                                                )}
                                            </div>
                                            {(vendor.city ||
                                                vendor.country_code) && (
                                                <p className="flex items-center gap-1 text-xs text-muted-foreground">
                                                    <MapPin className="size-3" />
                                                    {[
                                                        vendor.city,
                                                        vendor.country_code,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(', ')}
                                                </p>
                                            )}
                                        </div>
                                    </Link>
                                    {vendor.is_featured && (
                                        <Badge variant="secondary">
                                            Featured
                                        </Badge>
                                    )}
                                </div>

                                {vendor.tagline && (
                                    <p className="text-sm font-medium text-foreground/90">
                                        {vendor.tagline}
                                    </p>
                                )}

                                {vendor.description && (
                                    <p className="line-clamp-2 text-xs text-muted-foreground">
                                        {vendor.description}
                                    </p>
                                )}

                                {vendor.preview_products &&
                                    vendor.preview_products.length > 0 && (
                                        <div className="grid grid-cols-4 gap-2 pt-1">
                                            {vendor.preview_products
                                                .slice(0, 4)
                                                .map((prod) => (
                                                    <Link
                                                        key={prod.id}
                                                        href={productRoute(
                                                            prod.slug,
                                                        )}
                                                        title={prod.name}
                                                        className="group/item block overflow-hidden rounded-lg border bg-background p-1 transition hover:border-primary"
                                                    >
                                                        <div className="aspect-square overflow-hidden rounded bg-muted">
                                                            {prod.image ? (
                                                                <img
                                                                    src={
                                                                        prod
                                                                            .image
                                                                            .url
                                                                    }
                                                                    alt={
                                                                        prod.name
                                                                    }
                                                                    className="size-full object-cover transition group-hover/item:scale-105"
                                                                />
                                                            ) : (
                                                                <div className="flex size-full items-center justify-center text-[10px] text-muted-foreground">
                                                                    Item
                                                                </div>
                                                            )}
                                                        </div>
                                                        {prod.price && (
                                                            <div className="mt-1 truncate text-center text-[10px] font-semibold">
                                                                {
                                                                    prod.price
                                                                        .formatted
                                                                }
                                                            </div>
                                                        )}
                                                    </Link>
                                                ))}
                                        </div>
                                    )}
                            </div>

                            <Link
                                href={vendor.url}
                                className="mt-5 flex items-center justify-between border-t pt-3 text-xs text-muted-foreground hover:text-foreground"
                            >
                                <div className="flex items-center gap-3">
                                    <span className="flex items-center gap-1 font-medium text-foreground">
                                        <Star className="size-3.5 fill-amber-400 text-amber-500" />
                                        {vendor.rating.toFixed(1)}
                                    </span>
                                    <span className="flex items-center gap-1">
                                        <Package className="size-3.5" />
                                        {vendor.products_count} products
                                    </span>
                                </div>
                                <span className="inline-flex items-center gap-1 font-medium text-primary transition group-hover:translate-x-0.5">
                                    Visit Store
                                    <ArrowRight className="size-3.5" />
                                </span>
                            </Link>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
