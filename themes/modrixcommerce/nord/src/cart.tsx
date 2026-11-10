import { PlatformCartPage } from '@/pages/storefront/cart';
import type { CartProps } from '@/pages/storefront/cart';
import { StoreMenuLink as Link } from '@/components/storefront/footer-menus';
import './style.css';

/** Retain cart validation, coupons, live totals and extension content in the core. */
export default function NordCart(props: CartProps) {
    return (
        <div className="nd-frame nd-cart">
            <nav className="nd-crumbs" aria-label="Breadcrumb">
                <Link href="/">Home</Link>
                <span>/</span>
                <span>Shopping Bag</span>
            </nav>
            <PlatformCartPage {...props} />
        </div>
    );
}
