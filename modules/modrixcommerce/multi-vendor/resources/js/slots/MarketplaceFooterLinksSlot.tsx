import { Link } from '@inertiajs/react';
import {
    Building2,
    KeyRound,
    LayoutDashboard,
    Package,
    Store,
} from 'lucide-react';

interface Props {
    allowRegistration?: boolean;
    catalogUrl?: string;
    directoryUrl?: string;
    registerUrl?: string;
    loginUrl?: string;
    dashboardUrl?: string;
}

export default function MarketplaceFooterLinksSlot({
    allowRegistration = true,
    catalogUrl = '/m/modrixcommerce/multi-vendor/catalog',
    directoryUrl = '/m/modrixcommerce/multi-vendor/vendors',
    registerUrl = '/m/modrixcommerce/multi-vendor/register',
    loginUrl = '/m/modrixcommerce/multi-vendor/login',
    dashboardUrl = '/m/modrixcommerce/multi-vendor/dashboard',
}: Props) {
    return (
        <div className="my-3 flex flex-wrap items-center gap-4 text-xs text-muted-foreground">
            <Link
                href={catalogUrl}
                className="inline-flex items-center gap-1.5 font-medium hover:text-foreground"
            >
                <Package className="size-3.5 text-primary" />
                Marketplace All Products Catalog
            </Link>
            <Link
                href={directoryUrl}
                className="inline-flex items-center gap-1.5 font-medium hover:text-foreground"
            >
                <Store className="size-3.5 text-primary" />
                Marketplace Sellers Directory
            </Link>
            {allowRegistration && (
                <Link
                    href={registerUrl}
                    className="inline-flex items-center gap-1.5 font-medium hover:text-foreground"
                >
                    <Building2 className="size-3.5 text-primary" />
                    Become a Verified Seller
                </Link>
            )}
            <Link
                href={loginUrl}
                className="inline-flex items-center gap-1.5 font-medium hover:text-foreground"
            >
                <KeyRound className="size-3.5 text-primary" />
                Seller Login
            </Link>
            <Link
                href={dashboardUrl}
                className="inline-flex items-center gap-1.5 font-medium hover:text-foreground"
            >
                <LayoutDashboard className="size-3.5 text-primary" />
                Vendor Seller Portal
            </Link>
        </div>
    );
}
