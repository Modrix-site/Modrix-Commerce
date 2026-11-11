import { Head } from '@inertiajs/react';
import { PlatformCartPage } from '@/pages/storefront/cart';
import type { CartProps } from '@/pages/storefront/cart';
import { ExtensionSlot } from '@/components/extensions/extension-slot';
import { StoreMenuLink as Link } from '@/components/storefront/footer-menus';
import { useVoltora } from './components';
import './style.css';
export default function VoltoraCart(props: CartProps) {
    const { text, show } = useVoltora();
    return (
        <div className="voltora-wrap voltora-cart">
            {!props.cart?.lines.length ? (
                <>
                    <Head title="Your bag" />
                    <h1>{text('cart_empty_heading')}</h1>
                    <Link className="voltora-primary" href="/search">
                        {text('cart_continue_label', 'Continue shopping')}
                    </Link>
                    <ExtensionSlot items={props.slots} />
                </>
            ) : (
                <PlatformCartPage {...props} />
            )}
            {show('cart_show_promo') && (
                <section className="voltora-cart-promo">
                    {text('cart_promo_image') && (
                        <img
                            src={text('cart_promo_image')}
                            alt=""
                            loading="lazy"
                        />
                    )}
                    <div>
                        <h2>{text('cart_promo_heading')}</h2>
                        <p>{text('cart_promo_text')}</p>
                        <Link href={text('cart_promo_link', '/search')}>
                            {text('cart_continue_label')} →
                        </Link>
                    </div>
                </section>
            )}
        </div>
    );
}
