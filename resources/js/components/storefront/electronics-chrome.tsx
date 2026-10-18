import { Form, router, usePage } from '@inertiajs/react';
import { PlaceSlot } from '@/components/extensions/place-slot';
import {
    CartIcon,
    useShowAccountLink,
    useShowCartCount,
} from '@/components/storefront/page-settings/header-parts';
import { StoreMenuLink as Link } from '@/components/storefront/footer-menus';
import {
    ChevronDown,
    Globe,
    Headphones,
    Mail,
    Menu,
    Phone,
    Search,
    ShieldCheck,
    Truck,
    User,
} from 'lucide-react';
import { useState } from 'react';
import { useStoreTheme } from '@/hooks/use-store-theme';
import { home, login } from '@/routes';
import { show as cartShow } from '@/routes/cart';
import { category, search } from '@/routes/storefront';
import { CustomerNotificationBell } from '@/components/storefront/customer-notification-bell';

export function ElectronicsHeader() {
    const { storefront, auth } = usePage().props;
    const showAccount = useShowAccountLink();
    const showCount = useShowCartCount();
    const { theme } = useStoreTheme();
    const content = theme?.content ?? {};
    const [categoriesOpen, setCategoriesOpen] = useState(false);
    return (
        <header className="electronics-header">
            <div className="electronics-width electronics-topbar">
                <Link href={home()} className="electronics-brand">
                    {theme?.logo ? (
                        <img
                            src={theme.logo}
                            alt={storefront?.storeName ?? 'Store'}
                        />
                    ) : (
                        <span>
                            {String(
                                theme?.settings?.header_brand ??
                                    storefront?.storeName ??
                                    'MARKETPLACE',
                            )}
                        </span>
                    )}
                </Link>
                <Form
                    {...search.form()}
                    role="search"
                    className="electronics-search"
                >
                    <input
                        type="search"
                        name="q"
                        aria-label="Search products"
                        placeholder={String(
                            theme?.settings?.header_search_placeholder ??
                                'Search products, brands and more',
                        )}
                    />
                    <button type="submit">
                        <Search size={17} /> Search
                    </button>
                </Form>
                <label className="electronics-language">
                    <Globe size={16} />
                    <span className="sr-only">Language</span>
                    <select
                        value={storefront?.locale ?? 'en'}
                        onChange={(event) => {
                            const params = new URLSearchParams(
                                window.location.search,
                            );
                            params.set('lang', event.target.value);
                            router.visit(
                                `${window.location.pathname}?${params}`,
                                { preserveScroll: true },
                            );
                        }}
                    >
                        {storefront?.locales.map((locale) => (
                            <option key={locale.code} value={locale.code}>
                                {locale.name}
                            </option>
                        ))}
                    </select>
                </label>
                <PlaceSlot place="header.actions" className="flex items-center gap-1" />
                <CustomerNotificationBell />
                <Link
                    href={auth.user ? '/account' : login()}
                    className={
                        showAccount
                            ? 'electronics-account'
                            : 'electronics-account store-hidden'
                    }
                >
                    <User size={17} />
                    <span>{auth.user ? 'My account' : 'Login / Sign up'}</span>
                </Link>
                <Link
                    href={cartShow()}
                    aria-label={`Shopping cart, ${storefront?.cartCount ?? 0} items`}
                    className="electronics-cart"
                >
                    <CartIcon size={22} fallback="cart" />
                    {showCount && Boolean(storefront?.cartCount) && (
                        <sup>{storefront?.cartCount}</sup>
                    )}
                </Link>
                <Link
                    href={content.help_link || '/search'}
                    className="electronics-help"
                >
                    Need help?
                </Link>
            </div>
            <nav
                className="electronics-width electronics-nav"
                aria-label="Main navigation"
            >
                <div className="electronics-category-menu">
                    <button
                        type="button"
                        onClick={() => setCategoriesOpen((open) => !open)}
                        aria-expanded={categoriesOpen}
                    >
                        <Menu size={17} /> Shop all categories{' '}
                        <ChevronDown size={13} />
                    </button>
                    {categoriesOpen && (
                        <ul>
                            {storefront?.categories.map((item) => (
                                <li key={item.id}>
                                    <Link
                                        href={category(item.slug)}
                                        onClick={() => setCategoriesOpen(false)}
                                    >
                                        {item.name}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
                {(storefront?.navigation?.header.links ?? []).map((link, i) => (
                    <Link key={i} href={link.url}>
                        {link.label}
                    </Link>
                ))}
            </nav>
        </header>
    );
}

export function ElectronicsFooter() {
    const { storefront } = usePage().props;
    const { theme } = useStoreTheme();
    const content = theme?.content ?? {};
    return (
        <footer className="electronics-footer">
            <div className="electronics-width electronics-footer-grid">
                <div className="electronics-contact">
                    <h3>Contact us</h3>
                    {content.contact_email && (
                        <a href={`mailto:${content.contact_email}`}>
                            <Mail size={15} />
                            {content.contact_email}
                        </a>
                    )}
                    {content.contact_phone && (
                        <a
                            href={`tel:${content.contact_phone.replace(/[^+0-9]/g, '')}`}
                        >
                            <Phone size={15} />
                            {content.contact_phone}
                        </a>
                    )}
                    <h3>Here to help</h3>
                    <Link href={content.help_link || '/search'}>
                        Contact customer support
                    </Link>
                    <Link href="/account/orders">Track your order</Link>
                    <p>
                        <Globe size={14} />
                        {storefront?.locales.find(
                            (locale) => locale.code === storefront.locale,
                        )?.name ?? 'English'}
                    </p>
                </div>
                {(storefront?.navigation?.footer ?? []).map((menu, column) => (
                    <div key={column}>
                        <h3>{menu.title}</h3>
                        {menu.links.map((link, i) => (
                            <Link key={i} href={link.url}>
                                {link.label}
                            </Link>
                        ))}
                    </div>
                ))}
                <div>
                    <h3>Payment and security</h3>
                    <div className="electronics-payments">
                        <span>VISA</span>
                        <span>Mastercard</span>
                        <span>Secure checkout</span>
                    </div>
                    <p>
                        <ShieldCheck size={16} /> Your payments are protected
                    </p>
                </div>
            </div>
            <div className="electronics-footer-bottom">
                <div className="electronics-width">
                    <strong>{storefront?.storeName || 'MARKETPLACE'}</strong>
                    <span>
                        {theme?.footerText ||
                            `© ${new Date().getFullYear()} ${storefront?.storeName || 'Marketplace'}. All rights reserved.`}
                    </span>
                    <a href="#main">Back to top ↑</a>
                </div>
            </div>
        </footer>
    );
}

export function ElectronicsBenefits() {
    return (
        <div className="electronics-benefits">
            {[
                [Truck, 'Delivery options'],
                [ShieldCheck, 'Secure checkout'],
                [Headphones, 'Customer support'],
            ].map(([Icon, label]) => {
                const Symbol = Icon as typeof Truck;
                return (
                    <div key={String(label)}>
                        <Symbol size={21} />
                        <span>{String(label)}</span>
                    </div>
                );
            })}
        </div>
    );
}
