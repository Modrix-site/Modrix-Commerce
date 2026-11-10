import { StoreMenuTree } from '@/components/storefront/store-menu';
import { ExtensionSlot } from '@/components/extensions/extension-slot';
import { StoreMenuLink as Link } from '@/components/storefront/footer-menus';
import { useTempo } from './components';
import './style.css';
export default function TempoFooter() {
    const { storefront, text } = useTempo();
    return (
        <footer className="tp-footer">
            <div className="tp-frame tp-footer-grid">
                {storefront?.navigation?.footer.map((menu, i) => (
                    <nav key={i} aria-label={menu.title}>
                        <h2>{menu.title}</h2>
                        <StoreMenuTree
                            menu={menu}
                            orientation={menu.layout ?? 'vertical'}
                        />
                    </nav>
                ))}
                <section className="tp-newsletter">
                    <h2>{text('footer_connect_heading')}</h2>
                    <p>{text('footer_note')}</p>
                    <ExtensionSlot items={storefront?.footerSlots} />
                </section>
            </div>
            <div className="tp-frame tp-footer-bottom">
                <p>
                    © {new Date().getFullYear()}{' '}
                    {text('brand', storefront?.storeName ?? 'Tempo')}
                </p>
                <nav aria-label="Legal">
                    {text('privacy_link') && (
                        <Link href={text('privacy_link')}>Privacy policy</Link>
                    )}
                    {text('terms_link') && (
                        <Link href={text('terms_link')}>
                            Terms & conditions
                        </Link>
                    )}
                </nav>
            </div>
        </footer>
    );
}
