import { Form, Link } from '@inertiajs/react';
import { ShoppingBag } from 'lucide-react';
import CartController from '@/actions/App/Http/Controllers/Storefront/CartController';
import InputError from '@/components/input-error';
import { useStoreSettings } from '@/hooks/use-store-settings';
import { useStoreTheme } from '@/hooks/use-store-theme';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { product as productRoute } from '@/routes/storefront';
import type { ProductCard } from '@/types';

type Variant = 'default' | 'electronics' | 'atelier';

/**
 * The Buy button on a product card. Whether it appears is decided by the
 * product's own override first, then the theme setting
 * (`theme.settings.show_buy_button`).
 *
 * A simple, in-stock product buys straight into the cart in one tap. A product
 * with options (or one that is out of stock) cannot be bought from the card, so
 * the button links to the product page instead, keeping the card honest.
 */
export function BuyButton({
    product,
    variant = 'default',
    className,
}: {
    product: ProductCard;
    variant?: Variant;
    className?: string;
}) {
    const { theme } = useStoreTheme();
    // Catalog tab "Quick add to cart" (null: the theme decides).
    const quickAdd = useStoreSettings().catalog.quick_add;
    const t = useT();

    const themeDefault = theme?.settings?.show_buy_button;
    const enabled =
        product.show_buy_button ??
        quickAdd ??
        (themeDefault === undefined ? true : Boolean(themeDefault));

    if (!enabled) {
        return null;
    }

    const buyable = product.in_stock && product.variant_id != null;

    if (!buyable) {
        // Options to pick or nothing to sell: send the shopper to the product.
        if (!product.in_stock) {
            return null;
        }

        return (
            <Link
                href={productRoute(product.slug)}
                className={buttonClass(variant, className)}
            >
                {t('Choose options')}
            </Link>
        );
    }

    return (
        <Form
            {...CartController.store.form()}
            options={{ preserveScroll: true }}
            className="contents"
        >
            {({ processing, errors }) => (
                <>
                    <input
                        type="hidden"
                        name="variant_id"
                        value={product.variant_id ?? ''}
                    />
                    <input type="hidden" name="quantity" value="1" />
                    <button
                        type="submit"
                        className={buttonClass(variant, className)}
                        disabled={processing}
                    >
                        <ShoppingBag className="size-3.5" aria-hidden />
                        {processing ? t('Adding…') : t('Buy now')}
                    </button>
                    <InputError
                        message={errors.variant_id ?? errors.quantity}
                    />
                </>
            )}
        </Form>
    );
}

function buttonClass(variant: Variant, className?: string): string {
    const base =
        'inline-flex items-center justify-center gap-1.5 rounded-md text-sm font-medium transition disabled:opacity-60';

    const styles: Record<Variant, string> = {
        default:
            'mt-1 h-9 w-full border border-input bg-background hover:bg-muted',
        electronics: 'electronics-button',
        atelier:
            'mt-2 h-9 w-full bg-[var(--ecom-ink,#1a1a1a)] text-white hover:opacity-90',
    };

    return cn(base, styles[variant], className);
}
