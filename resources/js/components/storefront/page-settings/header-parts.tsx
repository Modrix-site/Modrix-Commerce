import { Link } from '@inertiajs/react';
import { Search, ShoppingBag, ShoppingCart } from 'lucide-react';
import { useStoreSettings } from '@/hooks/use-store-settings';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { search } from '@/routes/storefront';

/** The cart icon the Header tab picks (bag by default). */
export function CartIcon({
    className,
    size,
}: {
    className?: string;
    size?: number;
}) {
    const { header } = useStoreSettings();
    const Icon = header.cart_icon === 'cart' ? ShoppingCart : ShoppingBag;

    return <Icon className={className} size={size} aria-hidden />;
}

/** Whether the cart count shows next to the cart icon. */
export function useShowCartCount(): boolean {
    return useStoreSettings().header.cart_count;
}

/** Whether the header shows the account / log in link. */
export function useShowAccountLink(): boolean {
    return useStoreSettings().header.account_link;
}

/**
 * A search icon that replaces the search field when the Header tab sets
 * search to "icon" (hidden otherwise; see page-settings.css).
 */
export function SearchIconLink({ className }: { className?: string }) {
    const t = useT();

    return (
        <Link
            href={search()}
            aria-label={t('Search products')}
            className={cn(
                'store-search-icon items-center justify-center rounded-md p-2 hover:bg-accent',
                className,
            )}
        >
            <Search className="size-4" aria-hidden />
        </Link>
    );
}
