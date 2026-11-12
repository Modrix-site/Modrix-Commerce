import { StoreMenuTree } from '@/components/storefront/store-menu';
import { usePage } from '@inertiajs/react';
import { PlaceSlot } from '@/components/extensions/place-slot';
import {
    CartIcon,
    useShowAccountLink,
    useShowCartCount,
} from '@/components/storefront/page-settings/header-parts';
import { Search, User } from 'lucide-react';
import { StoreMenuLink as Link } from '@/components/storefront/footer-menus';
import { useStoreTheme } from '@/hooks/use-store-theme';
import { home, login } from '@/routes';
import { show as cartShow } from '@/routes/cart';
import { search } from '@/routes/storefront';
import { CustomerNotificationBell } from '@/components/storefront/customer-notification-bell';

/**
 * Editorial "atelier" chrome: a slim announcement bar, a centred wordmark
 * between utility links and icons, a centered uppercase nav, a trust band and
 * a two-column footer. Rendered when the theme's layout header/footer are
 * "atelier".
 */
export function AtelierHeader() {
    const { storefront, auth } = usePage().props;
    const showAccount = useShowAccountLink();
    const showCount = useShowCartCount();
    const { theme } = useStoreTheme();
    const menuLabels = storefront?.navigation?.header.links ?? [];

    return (
        <header className="ecom-header">
            <div className="ecom-header-row">
                <div className="ecom-utility">
                    <span>{storefront?.currency}</span>
                    <span>{storefront?.locale?.toUpperCase()}</span>
                </div>
                <Link href={home()} className="ecom-logo" aria-label="Home">
                    {theme?.logo ? (
                        <img
                            src={theme.logo}
                            alt=""
                            style={{ height: 30, width: 'auto' }}
                        />
                    ) : (
                        (storefront?.storeName ?? 'STORE').toUpperCase()
                    )}
                </Link>
                <div className="ecom-icons">
                    <Link href={search()} aria-label="Search">
                        <Search size={17} />
                    </Link>
                    <PlaceSlot
                        place="header.actions"
                        className="flex items-center gap-1"
                    />
                    <CustomerNotificationBell />
                    <Link
                        href={auth.user ? '/account' : login()}
                        aria-label="Account"
                        className={showAccount ? undefined : 'store-hidden'}
                    >
                        <User size={17} />
                    </Link>
                    <Link href={cartShow()} aria-label="Cart">
                        <CartIcon size={17} />
                        {showCount && Boolean(storefront?.cartCount) && (
                            <span className="ecom-count">
                                {storefront?.cartCount}
                            </span>
                        )}
                    </Link>
                </div>
            </div>
            {menuLabels.length > 0 && (
                <nav className="ecom-nav" aria-label="Main navigation">
                    <StoreMenuTree menu={storefront?.navigation?.header} />
                </nav>
            )}
        </header>
    );
}

/** A quiet marquee of assurances shown just above the footer. */
export function AtelierTrustBand() {
    const { theme } = useStoreTheme();
    const items = String(theme?.settings?.trust_items ?? '')
        .split('|')
        .map((item) => item.trim())
        .filter(Boolean);

    if (items.length === 0) {
        return null;
    }

    return (
        <section className="ecom-trustband" aria-label="Our promises">
            {items.map((item) => (
                <span key={item}>{item}</span>
            ))}
        </section>
    );
}

export function AtelierFooter() {
    const { storefront } = usePage().props;
    const { theme } = useStoreTheme();
    const settings = theme?.settings ?? {};
    const columns = storefront?.navigation?.footer ?? [];
    const heading = String(settings.footer_heading ?? 'Stories worth keeping.');
    const note = String(settings.footer_note ?? 'thought made everyday');

    return (
        <footer className="ecom-footer">
            <div className="ecom-footer-grid">
                <div className="ecom-footer-intro">
                    <span className="ecom-eyebrow">
                        {(storefront?.storeName ?? 'STORE').toUpperCase()}
                    </span>
                    <h2>{heading}</h2>
                    <p>
                        {theme?.footerText ??
                            'Considered objects, made to be used.'}
                    </p>
                </div>
                <div className="ecom-footer-links">
                    {columns.map(
                        (column) =>
                            column.title && (
                                <div key={column.title}>
                                    <h3>{column.title}</h3>
                                    <StoreMenuTree
                                        menu={column}
                                        orientation={
                                            column.layout ?? 'vertical'
                                        }
                                    />
                                </div>
                            ),
                    )}
                </div>
            </div>
            <div className="ecom-footer-bottom">
                <span>
                    © {new Date().getFullYear()}{' '}
                    {storefront?.storeName ?? 'Store'}
                </span>
                <span className="ecom-footer-meta">
                    {storefront?.currency && <span>{storefront.currency}</span>}
                    {storefront?.locale && (
                        <span>{storefront.locale.toUpperCase()}</span>
                    )}
                    <span>{note}</span>
                </span>
            </div>
        </footer>
    );
}
