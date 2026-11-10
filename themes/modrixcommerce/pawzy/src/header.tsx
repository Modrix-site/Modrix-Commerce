import { Form, Link, router, usePage } from '@inertiajs/react';
import { Menu, Phone, Search, ShoppingBag, User, X } from 'lucide-react';
import { useState } from 'react';
import { PlaceSlot } from '@/components/extensions/place-slot';
import { StoreMenuTree } from '@/components/storefront/store-menu';
import { CustomerNotificationBell } from '@/components/storefront/customer-notification-bell';
import {
    useShowAccountLink,
    useShowCartCount,
} from '@/components/storefront/page-settings/header-parts';
import { category, search } from '@/routes/storefront';
import { show as cart } from '@/routes/cart';
import { Brand } from './components';

export default function PawzyHeader() {
    const { storefront } = usePage().props;
    const s = storefront?.theme.settings ?? {};
    const content = storefront?.theme.content ?? {};
    const [open, setOpen] = useState(false);
    const showAccount = useShowAccountLink();
    const showCount = useShowCartCount();
    const switchValue = (key: string, value: string) => {
        const params = new URLSearchParams(window.location.search);
        params.set(key, value);
        router.visit(`${window.location.pathname}?${params}`, {
            preserveScroll: true,
        });
    };
    return (
        <header className="pawzy-header">
            <div className="pawzy-utility">
                <div>
                    {s.show_announcement !== false &&
                        storefront?.theme.announcement}
                </div>
                <div>
                    {(storefront?.locales.length ?? 0) > 1 && (
                        <label>
                            <span className="sr-only">Language</span>
                            <select
                                value={storefront?.locale}
                                onChange={(e) =>
                                    switchValue('lang', e.target.value)
                                }
                            >
                                {storefront?.locales.map((l) => (
                                    <option key={l.code} value={l.code}>
                                        {l.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                    )}
                    {(storefront?.currencies?.length ?? 0) > 1 && (
                        <label>
                            <span className="sr-only">Currency</span>
                            <select
                                value={storefront?.displayCurrency}
                                disabled={!storefront?.canSwitchCurrency}
                                onChange={(e) =>
                                    switchValue('currency', e.target.value)
                                }
                            >
                                {storefront?.currencies?.map((c) => (
                                    <option value={c.code} key={c.code}>
                                        {c.code}
                                    </option>
                                ))}
                            </select>
                        </label>
                    )}
                </div>
            </div>
            <div className="pawzy-header-main">
                <Brand />
                {s.show_search !== false && (
                    <Form
                        {...search.form()}
                        role="search"
                        className="pawzy-search"
                    >
                        <input
                            type="search"
                            name="q"
                            placeholder={String(
                                s.search_placeholder ?? 'Enter your keywords',
                            )}
                            aria-label="Search products"
                        />
                        <button type="submit" aria-label="Search">
                            <Search size={19} />
                        </button>
                    </Form>
                )}
                <div className="pawzy-actions">
                    {showAccount && (
                        <Link href="/account" aria-label="Account">
                            <User size={20} />
                            <span>Account</span>
                        </Link>
                    )}
                    <PlaceSlot place="header.actions" />
                    <CustomerNotificationBell />
                    <Link
                        href={cart()}
                        aria-label={`Cart, ${storefront?.cartCount ?? 0} items`}
                    >
                        <ShoppingBag size={21} />
                        <span>Cart</span>
                        {showCount && <sup>{storefront?.cartCount ?? 0}</sup>}
                    </Link>
                </div>
            </div>
            {s.show_navigation !== false && (
                <div className="pawzy-nav">
                    <div className="pawzy-nav-inner">
                        {s.show_sidebar !== false && (
                            <div className="pawzy-category-toggle">
                                <button
                                    className="pawzy-category-trigger"
                                    type="button"
                                    aria-expanded={open}
                                    aria-controls="pawzy-category-drawer"
                                    onClick={() => setOpen(!open)}
                                >
                                    <Menu size={18} />
                                    {String(
                                        s.sidebar_label ?? 'Shop by categories',
                                    )}
                                </button>
                                {open && (
                                    <>
                                        <button
                                            className="pawzy-menu-backdrop"
                                            aria-label="Close categories"
                                            onClick={() => setOpen(false)}
                                        />
                                        <aside
                                            id="pawzy-category-drawer"
                                            className="pawzy-category-drawer"
                                            aria-label="Shop by categories"
                                        >
                                            <div>
                                                <strong>
                                                    Shop by categories
                                                </strong>
                                                <button
                                                    onClick={() =>
                                                        setOpen(false)
                                                    }
                                                    aria-label="Close categories"
                                                >
                                                    <X />
                                                </button>
                                            </div>
                                            {storefront?.categories.map((c) => (
                                                <Link
                                                    href={category(c.slug)}
                                                    key={c.id}
                                                    onClick={() =>
                                                        setOpen(false)
                                                    }
                                                >
                                                    {c.name}
                                                </Link>
                                            ))}
                                            <StoreMenuTree
                                                menu={
                                                    storefront?.navigation
                                                        ?.sidebar
                                                }
                                                orientation="vertical"
                                            />
                                        </aside>
                                    </>
                                )}
                            </div>
                        )}
                        <nav aria-label="Main navigation">
                            {s.use_store_navigation === true ? (
                                <StoreMenuTree
                                    menu={storefront?.navigation?.header}
                                />
                            ) : (
                                <ul>
                                    {Array.from(
                                        { length: 5 },
                                        (_, i) => i + 1,
                                    ).map(
                                        (i) =>
                                            content[`menu_${i}_label`] && (
                                                <li key={i}>
                                                    <Link
                                                        href={
                                                            content[
                                                                `menu_${i}_link`
                                                            ] || '/search'
                                                        }
                                                    >
                                                        {
                                                            content[
                                                                `menu_${i}_label`
                                                            ]
                                                        }
                                                    </Link>
                                                </li>
                                            ),
                                    )}
                                </ul>
                            )}
                        </nav>
                        {content.contact_phone && (
                            <a
                                className="pawzy-phone"
                                href={`tel:${content.contact_phone.replace(/[^+0-9]/g, '')}`}
                            >
                                <Phone size={15} />
                                {content.contact_phone}
                            </a>
                        )}
                    </div>
                </div>
            )}
        </header>
    );
}
