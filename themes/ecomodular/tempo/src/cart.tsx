import { Head } from '@inertiajs/react';
import { PlatformCartPage } from '@/pages/storefront/cart';
import type { CartProps } from '@/pages/storefront/cart';
import { StoreMenuLink as Link } from '@/components/storefront/footer-menus';
import { useTempo } from './components';
import './style.css';
export default function TempoCart(props: CartProps) {
    const empty = !props.cart || props.cart.lines.length === 0;
    return (
        <div className="tp-frame tp-cart">
            <nav className="tp-crumbs" aria-label="Breadcrumb">
                <Link href="/">Home</Link>
                <span>›</span>
                <span>My cart</span>
            </nav>
            {empty ? <EmptyCart /> : <PlatformCartPage {...props} />}
        </div>
    );
}

/** The design's empty bag: heading, a way back to the shop, and the summary panel. */
function EmptyCart() {
    const { text } = useTempo();
    return (
        <div className="tp-cart-empty">
            <Head title="My cart" />
            <h1>{text('cart_heading', 'My cart')} (0)</h1>
            <div className="tp-cart-empty-layout">
                <section>
                    <p>{text('cart_empty_heading', 'Your cart is empty')}</p>
                    <Link
                        href="/search"
                        className="store-button tp-button tp-button-primary"
                    >
                        {text('cart_continue_label', 'Continue shopping')}
                    </Link>
                </section>
                <aside aria-label="Order summary">
                    <h2>{text('cart_summary_heading', 'Order summary')}</h2>
                    <p>
                        Add items to your bag to see prices, shipping and taxes.
                    </p>
                    <button
                        type="button"
                        className="store-button tp-button tp-button-primary"
                        disabled
                    >
                        {text('cart_checkout_label', 'Checkout')}
                    </button>
                </aside>
            </div>
        </div>
    );
}
