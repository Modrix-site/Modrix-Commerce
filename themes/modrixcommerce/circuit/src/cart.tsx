import { Head } from '@inertiajs/react';
import { ShoppingCart } from 'lucide-react';
import { StoreMenuLink as Link } from '@/components/storefront/footer-menus';
import { PlatformCartPage } from '@/pages/storefront/cart';
import type { CartProps } from '@/pages/storefront/cart';
import { PaymentBadges, useCircuit } from './components';
import './style.css';

export default function CircuitCart(props: CartProps) {
    const { text, show } = useCircuit();
    const empty = !props.cart || props.cart.lines.length === 0;
    return (
        <div className="ci-frame ci-cart-page">
            <nav className="ci-crumbs ci-crumbs-center" aria-label="Breadcrumb">
                <Link href="/">{text('home_label', 'Home')}</Link>
                <span>{text('cart_heading', 'Shopping cart')}</span>
            </nav>
            {empty ? (
                <section
                    className="ci-cart-empty"
                    aria-label="Your cart is empty"
                >
                    <Head title={text('cart_heading', 'Shopping cart')} />
                    <ShoppingCart size={44} aria-hidden />
                    <h1>{text('empty_heading', 'Your cart is empty')}</h1>
                    <p>
                        {text(
                            'empty_text',
                            'Browse our products and find something you love.',
                        )}
                    </p>
                    <Link href="/search" className="store-button ci-button">
                        {text('continue_label', 'Continue shopping')}
                    </Link>
                </section>
            ) : (
                <>
                    <PlatformCartPage {...props} />
                    <div className="ci-cart-foot">
                        {text('cart_note') && (
                            <p className="ci-muted">{text('cart_note')}</p>
                        )}
                        {show('cart_payments') && (
                            <PaymentBadges labels={text('payment_labels')} />
                        )}
                    </div>
                </>
            )}
        </div>
    );
}
