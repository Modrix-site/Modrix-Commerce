import { PlatformCartPage } from '@/pages/storefront/cart';
import type { CartProps } from '@/pages/storefront/cart';
import './style.css';
export default function MarketplaceCart(props: CartProps) {
    return (
        <div className="marketplace-cart">
            <PlatformCartPage {...props} />
        </div>
    );
}
