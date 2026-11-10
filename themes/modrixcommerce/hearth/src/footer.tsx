import { StoreMenuTree } from '@/components/storefront/store-menu';
import { StoreMenuLink as Link } from '@/components/storefront/footer-menus';
import { ExtensionSlot } from '@/components/extensions/extension-slot';
import { useHearth } from './components';
import './style.css';
export default function HearthFooter() {
    const { storefront, text, show } = useHearth();
    return (
        <footer className="hearth-footer">
            <div className="hearth-wrap">
                {show('footer_newsletter') && (
                    <ExtensionSlot
                        items={storefront?.footerSlots}
                        className="hearth-newsletter"
                        extraProps={{ heading: text('newsletter_heading') }}
                    />
                )}
                <p className="hearth-footer-message">
                    {text('footer_message')}
                </p>
                <div className="hearth-footer-grid">
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
                <div className="hearth-bottom">
                    <span>{text('footer_copyright')}</span>
                    <Link href="/account">Your account</Link>
                </div>
            </div>
        </footer>
    );
}
