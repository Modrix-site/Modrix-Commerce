import { Head } from '@inertiajs/react';
import { PlatformCartPage } from '@/pages/storefront/cart';
import type { CartProps } from '@/pages/storefront/cart';
import { StoreMenuLink as Link } from '@/components/storefront/footer-menus';
import { useHearth } from './components';
import './style.css';
export default function HearthCart(props: CartProps) {
    const { text } = useHearth();
    return (
        <div className="hearth-wrap hearth-cart">
            {!props.cart?.lines.length ? (
                <>
                    <Head title="Your bag" />
                    {text('cart_image') && (
                        <img
                            className="hearth-empty-cart"
                            src={text('cart_image')}
                            alt=""
                        />
                    )}
                    <h1>{text('cart_empty_heading')}</h1>
                    <Link className="hearth-primary" href="/search">
                        EXPLORE THE CATALOGUE
                    </Link>
                </>
            ) : (
                <PlatformCartPage {...props} />
            )}
        </div>
    );
}
