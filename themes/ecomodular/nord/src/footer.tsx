import { usePage } from '@inertiajs/react';
import { ExtensionSlot } from '@/components/extensions/extension-slot';
import { StoreMenuLink as Link } from '@/components/storefront/footer-menus';
import { useNord } from './components';
import './style.css';

export default function NordFooter() {
    const { storefront, text } = useNord();
    const { auth } = usePage().props;
    return (
        <footer className="nd-footer">
            <div className="nd-frame nd-footer-grid">
                {storefront?.navigation?.footer.map((menu, i) => (
                    <nav key={i} aria-label={menu.title}>
                        <h2>{menu.title}</h2>
                        {menu.links.map((link, j) => (
                            <Link key={j} href={link.url}>
                                {link.label}
                            </Link>
                        ))}
                    </nav>
                ))}
                <nav aria-label={text('footer_account_heading')}>
                    <h2>{text('footer_account_heading')}</h2>
                    <Link href={auth.user ? '/account' : '/login'}>
                        {auth.user ? 'My Account' : 'Sign In'}
                    </Link>
                    <Link href="/account/orders">Orders</Link>
                    <Link href="/cart">Shopping Bag</Link>
                </nav>
                <nav aria-label={text('footer_explore_heading')}>
                    <h2>{text('footer_explore_heading')}</h2>
                    {storefront?.categories.slice(0, 4).map((category) => (
                        <Link key={category.id} href={`/c/${category.slug}`}>
                            {category.name}
                        </Link>
                    ))}
                    <Link href="/search?sort=newest">New Arrivals</Link>
                </nav>
                <section>
                    <h2>{text('footer_connect_heading')}</h2>
                    <p>{text('footer_note')}</p>
                    <ExtensionSlot
                        items={storefront?.footerSlots}
                        className="nd-footer-newsletter"
                    />
                </section>
            </div>
            <div className="nd-footer-legal">
                <div className="nd-frame">
                    <nav aria-label="Legal">
                        {text('privacy_link') && (
                            <Link href={text('privacy_link')}>Privacy</Link>
                        )}
                        {text('terms_link') && (
                            <Link href={text('terms_link')}>
                                Terms & Conditions
                            </Link>
                        )}
                    </nav>
                    <p>
                        © {new Date().getFullYear()}{' '}
                        {text('brand', storefront?.storeName ?? 'NORD')}
                    </p>
                </div>
            </div>
        </footer>
    );
}
