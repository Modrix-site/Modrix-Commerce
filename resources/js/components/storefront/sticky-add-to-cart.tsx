import { Form } from '@inertiajs/react';
import { ImageOff, ShoppingBag, Zap } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import CartController from '@/actions/App/Http/Controllers/Storefront/CartController';
import type { VariantOption } from '@/components/storefront/variant-selector';
import { useStoreSettings } from '@/hooks/use-store-settings';
import { useStoreTheme } from '@/hooks/use-store-theme';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { MoneyValue } from '@/types';

/**
 * Once the page's own add-to-cart form scrolls out of view, a bar at the
 * bottom keeps the product, its price, its options (when the page passes
 * them) and Add to cart / Buy now in reach. Without an option picker,
 * products whose options are not chosen yet scroll back to the pickers.
 *
 * The merchant sets it in the customizer's Product tab
 * (`product.sticky_bar`, `sticky_bar_buttons`, `sticky_bar_options`); left
 * at the theme default, the theme's `sticky_add_to_cart` setting decides
 * (phones only, on unless the theme turns it off).
 *
 * Themes with their own fixed bottom bar set `--store-sticky-offset` to its
 * height so this bar sits just above it.
 */
export function StickyAddToCart({
    targetId,
    name,
    image,
    variant,
    hasOptions = false,
    showBuyButton,
    options,
    selected,
    onOption,
}: {
    /** id of the page's add-to-cart form, which the bar stands in for. */
    targetId: string;
    name: string;
    image?: string | null;
    variant?: {
        id: number;
        price: MoneyValue | null;
        compare_at_price?: MoneyValue | null;
        purchasable: boolean;
    } | null;
    /** The product has options to choose (sizes, colours, …). */
    hasOptions?: boolean;
    showBuyButton?: boolean | null;
    /** The product's options, to pick them in the bar too. */
    options?: VariantOption[];
    selected?: Record<number, number>;
    onOption?: (attributeId: number, valueId: number) => void;
}) {
    const { theme } = useStoreTheme();
    const product = useStoreSettings().product;
    const t = useT();
    const [visible, setVisible] = useState(false);
    const settings =
        (theme as { settings?: Record<string, unknown> } | undefined)
            ?.settings ?? {};
    const mode =
        product.sticky_bar ??
        (settings.sticky_add_to_cart === false ? 'off' : 'phones');
    const enabled = mode !== 'off';
    const buyEnabled =
        showBuyButton ??
        (settings.show_buy_button === undefined
            ? true
            : Boolean(settings.show_buy_button));
    const buttons = product.sticky_bar_buttons ?? 'both';
    // Buy now follows the product / theme switch; when it is off, a
    // "Buy now only" bar falls back to Add to cart.
    const buyButton = buyEnabled && buttons !== 'add_to_cart';
    const cartButton = !buyButton || buttons !== 'buy_now';
    const picker =
        product.sticky_bar_options !== false &&
        onOption !== undefined &&
        options !== undefined &&
        options.length > 0;

    useEffect(() => {
        if (!enabled) {
            return;
        }

        let frame = 0;
        // Visible once the form has scrolled up past the top of the screen.
        // The form is looked up each time: pages re-create it when the
        // variant changes (and may drop it while no variant is chosen; the
        // bar then keeps its state).
        const update = () => {
            frame = 0;
            const target = document.getElementById(targetId);

            if (target !== null) {
                setVisible(target.getBoundingClientRect().bottom < 0);
            }
        };
        const onScroll = () => {
            if (frame === 0) {
                frame = window.requestAnimationFrame(update);
            }
        };

        update();
        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', onScroll);

        return () => {
            window.removeEventListener('scroll', onScroll);
            window.removeEventListener('resize', onScroll);

            if (frame !== 0) {
                window.cancelAnimationFrame(frame);
            }
        };
    }, [enabled, targetId]);

    if (!enabled || (!variant && !picker)) {
        return null;
    }

    const toOptions = () =>
        document
            .getElementById(targetId)
            ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    const tab = visible ? 0 : -1;

    return (
        <div
            data-sticky-purchase-bar={mode}
            className={cn(
                'fixed inset-x-0 z-40 border-t bg-background/95 px-3 py-2 shadow-[0_-4px_12px_rgba(0,0,0,0.08)] backdrop-blur transition-transform duration-200 motion-reduce:transition-none',
                mode === 'phones' && 'lg:hidden',
                visible
                    ? 'translate-y-0'
                    : 'pointer-events-none translate-y-[calc(100%+var(--store-sticky-offset,0px))]',
            )}
            style={{ bottom: 'var(--store-sticky-offset, 0px)' }}
            aria-hidden={!visible}
        >
            <div className="mx-auto flex max-w-6xl flex-col gap-2 lg:flex-row lg:items-center lg:gap-4">
                {picker && (
                    <div className="flex min-w-0 gap-2 lg:order-2 lg:flex-1 lg:justify-end">
                        {options.map((option) => (
                            <select
                                key={option.id}
                                aria-label={option.name}
                                tabIndex={tab}
                                value={selected?.[option.id] ?? ''}
                                onChange={(e) =>
                                    onOption(option.id, Number(e.target.value))
                                }
                                className="h-9 min-w-0 flex-1 rounded-md border border-input bg-background px-2 text-sm lg:max-w-48"
                            >
                                <option value="" disabled>
                                    {option.name}
                                </option>
                                {option.values.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.value}
                                    </option>
                                ))}
                            </select>
                        ))}
                    </div>
                )}
                <div className="flex min-w-0 items-center gap-3 lg:contents">
                    {image ? (
                        <img
                            src={image}
                            alt=""
                            className="size-11 shrink-0 rounded border object-cover lg:order-0"
                        />
                    ) : (
                        <span className="flex size-11 shrink-0 items-center justify-center rounded border text-muted-foreground lg:order-0">
                            <ImageOff className="size-4" />
                        </span>
                    )}
                    <div className="min-w-0 flex-1 lg:order-1 lg:flex-none lg:basis-64">
                        <p className="truncate text-sm font-medium">{name}</p>
                        {variant?.price && (
                            <p className="store-price text-sm tabular-nums">
                                {variant.price.formatted}
                                {variant.compare_at_price && (
                                    <s className="ml-1.5 text-xs text-muted-foreground">
                                        {variant.compare_at_price.formatted}
                                    </s>
                                )}
                            </p>
                        )}
                    </div>
                    <div className="flex shrink-0 gap-1.5 lg:order-3">
                        {variant?.purchasable ? (
                            <>
                                {cartButton && (
                                    <CartForm variantId={variant.id}>
                                        {(processing) => (
                                            <button
                                                type="submit"
                                                disabled={processing}
                                                tabIndex={tab}
                                                className="store-button inline-flex h-10 items-center gap-1.5 rounded-md bg-primary px-3 text-sm font-semibold text-primary-foreground disabled:opacity-60"
                                            >
                                                <ShoppingBag
                                                    className="size-4"
                                                    aria-hidden
                                                />
                                                {processing
                                                    ? t('Adding…')
                                                    : t('Add to cart')}
                                            </button>
                                        )}
                                    </CartForm>
                                )}
                                {buyButton && (
                                    <CartForm variantId={variant.id} buyNow>
                                        {(processing) => (
                                            <button
                                                type="submit"
                                                disabled={processing}
                                                tabIndex={tab}
                                                aria-label={t('Buy now')}
                                                title={t('Buy now')}
                                                className={cn(
                                                    'inline-flex h-10 items-center justify-center gap-1.5 rounded-md text-sm font-semibold disabled:opacity-60',
                                                    cartButton
                                                        ? 'w-10 border border-foreground text-foreground lg:w-auto lg:px-3'
                                                        : 'store-button bg-primary px-3 text-primary-foreground',
                                                )}
                                            >
                                                <Zap
                                                    className="size-4"
                                                    aria-hidden
                                                />
                                                <span
                                                    className={cn(
                                                        cartButton &&
                                                            'hidden lg:inline',
                                                    )}
                                                >
                                                    {t('Buy now')}
                                                </span>
                                            </button>
                                        )}
                                    </CartForm>
                                )}
                            </>
                        ) : picker ? (
                            <span className="text-sm text-muted-foreground">
                                {variant
                                    ? t('Unavailable')
                                    : t('Choose options')}
                            </span>
                        ) : !hasOptions ? (
                            <span className="text-sm text-muted-foreground">
                                {t('Unavailable')}
                            </span>
                        ) : (
                            <button
                                type="button"
                                onClick={toOptions}
                                tabIndex={tab}
                                className="h-10 rounded-md border px-3 text-sm font-semibold"
                            >
                                {t('Choose options')}
                            </button>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}

/** Adds one of the variant (and with buyNow, goes to checkout). */
function CartForm({
    variantId,
    buyNow = false,
    children,
}: {
    variantId: number;
    buyNow?: boolean;
    children: (processing: boolean) => ReactNode;
}) {
    return (
        <Form
            {...CartController.store.form()}
            options={{ preserveScroll: true }}
        >
            {({ processing }) => (
                <>
                    <input type="hidden" name="variant_id" value={variantId} />
                    <input type="hidden" name="quantity" value="1" />
                    {buyNow && <input type="hidden" name="buy_now" value="1" />}
                    {children(processing)}
                </>
            )}
        </Form>
    );
}
