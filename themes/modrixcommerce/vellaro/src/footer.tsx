import { StoreMenuTree } from '@/components/storefront/store-menu';
import { StoreMenuLink as Link } from '@/components/storefront/footer-menus';
import { ExtensionSlot } from '@/components/extensions/extension-slot';
import { useVellaro } from './components';
import './style.css';
export default function VellaroFooter() {
    const { storefront, text, show } = useVellaro();
    return (
        <footer className="vellaro-footer">
            <div className="vellaro-wrap">
                {show('footer_newsletter') && (
                    <ExtensionSlot
                        items={storefront?.footerSlots}
                        className="vellaro-newsletter"
                        extraProps={{ heading: text('newsletter_heading') }}
                    />
                )}
                <p className="vellaro-footer-message">
                    {text('footer_message')}
                </p>
                <div className="vellaro-footer-grid">
                    {text('footer_image') && (
                        <Link href="/search">
                            <img
                                src={text('footer_image')}
                                alt="Explore the catalogue"
                                loading="lazy"
                            />
                        </Link>
                    )}
                    {storefront?.navigation?.footer.map((menu, i) => (
                        <nav key={i} aria-label={menu.title}>
                            <h3>{menu.title}</h3>
                            <StoreMenuTree
                                menu={menu}
                                orientation={menu.layout ?? 'vertical'}
                            />
                        </nav>
                    ))}
                </div>
                <p className="vellaro-footer-wordmark">
                    {text('brand', 'VELLARO')}
                </p>
                <div className="vellaro-bottom">
                    <span>{text('footer_copyright')}</span>
                    <Link href="/account">Your account</Link>
                </div>
            </div>
        </footer>
    );
}
