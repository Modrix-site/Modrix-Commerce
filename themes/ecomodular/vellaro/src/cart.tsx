import { Head } from "@inertiajs/react";
import { PlatformCartPage } from "@/pages/storefront/cart";
import type { CartProps } from "@/pages/storefront/cart";
import { StoreMenuLink as Link } from "@/components/storefront/footer-menus";
import { useVellaro } from "./components";
import "./style.css";
export default function VellaroCart(props: CartProps) {
    const { text } = useVellaro();
    return (
        <div className="vellaro-wrap vellaro-cart">
            {!props.cart?.lines.length ? (
                <>
                    <Head title="Your bag" />
                    {text("cart_image") && (
                        <img
                            className="vellaro-empty-cart"
                            src={text("cart_image")}
                            alt=""
                        />
                    )}
                    <h1>{text("cart_empty_heading")}</h1>
                    <Link className="vellaro-primary" href="/search">
                        EXPLORE THE CATALOGUE
                    </Link>
                </>
            ) : (
                <PlatformCartPage {...props} />
            )}
        </div>
    );
}
