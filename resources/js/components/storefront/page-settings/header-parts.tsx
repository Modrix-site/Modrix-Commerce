import { ShoppingBag, ShoppingCart } from 'lucide-react';
import { SearchToggle } from '@/components/storefront/search-toggle';
import { useStoreSettings } from '@/hooks/use-store-settings';
import { cn } from '@/lib/utils';

/** The cart icon the Header tab picks; "Theme default" keeps the theme's own. */
export function CartIcon({
    className,
    size,
    fallback = 'bag',
}: {
    className?: string;
    size?: number;
    fallback?: 'bag' | 'cart';
}) {
    const { header } = useStoreSettings();
    const Icon =
        (header.cart_icon ?? fallback) === 'cart' ? ShoppingCart : ShoppingBag;

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
 * search to "icon" (hidden otherwise; see page-settings.css). It opens a
 * search field instead of leaving for the catalog.
 */
export function SearchIconLink({ className }: { className?: string }) {
    return (
        <SearchToggle
            className={cn(
                'store-search-icon items-center justify-center rounded-md p-2 hover:bg-accent',
                className,
            )}
        />
    );
}
