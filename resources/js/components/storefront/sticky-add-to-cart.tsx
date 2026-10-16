import { Form } from '@inertiajs/react';
import { ImageOff, ShoppingBag, Zap } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import CartController from '@/actions/App/Http/Controllers/Storefront/CartController';
import { useStoreTheme } from '@/hooks/use-store-theme';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { MoneyValue } from '@/types';

/**
 * Phones: once the page's own add-to-cart form scrolls out of view, a bar
 * at the bottom keeps the product, its price, Add to cart and (when shown)
 * Buy now in reach. Products whose options are not chosen yet scroll back
 * to the option pickers instead.
 *
 * Shown when the theme's `sticky_add_to_cart` setting is on (the default).
 */
export function StickyAddToCart({
    targetId,
    name,
    image,
    variant,
    hasOptions = false,
    showBuyButton,
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
}) {
    const { theme } = useStoreTheme();
    const t = useT();
    const [visible, setVisible] = useState(false);
    const settings =
        (theme as { settings?: Record<string, unknown> } | undefined)
            ?.settings ?? {};
    const enabled = settings.sticky_add_to_cart !== false;
    const buyNow =
        showBuyButton ??
        (settings.show_buy_button === undefined
            ? true
            : Boolean(settings.show_buy_button));

    useEffect(() => {
        if (!enabled) {
            return;
        }

        let frame = 0;
        // Visible once the form has scrolled up past the top of the screen.
        // The form is looked up each time: pages re-create it when the
        // variant changes.
        const update = () => {
            frame = 0;
            const target = document.getElementById(targetId);
            setVisible(
                target !== null && target.getBoundingClientRect().bottom < 0,
            );
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

    if (!enabled || !variant) {
        return null;
    }

    const toOptions = () =>
        document
            .getElementById(targetId)
            ?.scrollIntoView({ behavior: 'smooth', block: 'center' });

    return (
        <div
            className={cn(
                'fixed inset-x-0 bottom-0 z-40 border-t bg-background/95 px-3 py-2 shadow-[0_-4px_12px_rgba(0,0,0,0.08)] backdrop-blur transition-transform duration-200 motion-reduce:transition-none lg:hidden',
                visible
                    ? 'translate-y-0'
                    : 'pointer-events-none translate-y-full',
            )}
            aria-hidden={!visible}
        >
            <div className="flex items-center gap-3">
                {image ? (
                    <img
                        src={image}
                        alt=""
                        className="size-11 shrink-0 rounded border object-cover"
                    />
                ) : (
                    <span className="flex size-11 shrink-0 items-center justify-center rounded border text-muted-foreground">
                        <ImageOff className="size-4" />
                    </span>
                )}
                <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-medium">{name}</p>
                    {variant.price && (
                        <p className="text-sm tabular-nums">
                            {variant.price.formatted}
                            {variant.compare_at_price && (
                                <s className="ml-1.5 text-xs text-muted-foreground">
                                    {variant.compare_at_price.formatted}
                                </s>
                            )}
                        </p>
                    )}
                </div>
                {variant.purchasable ? (
                    <div className="flex shrink-0 gap-1.5">
                        <CartForm variantId={variant.id} visible={visible}>
                            {(processing) => (
                                <button
                                    type="submit"
                                    disabled={processing}
                                    tabIndex={visible ? 0 : -1}
                                    className="inline-flex h-10 items-center gap-1.5 rounded-md bg-primary px-3 text-sm font-semibold text-primary-foreground disabled:opacity-60"
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
                        {buyNow && (
                            <CartForm
                                variantId={variant.id}
                                visible={visible}
                                buyNow
                            >
                                {(processing) => (
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        tabIndex={visible ? 0 : -1}
                                        aria-label={t('Buy now')}
                                        title={t('Buy now')}
                                        className="inline-flex size-10 items-center justify-center rounded-md border border-foreground text-foreground disabled:opacity-60"
                                    >
                                        <Zap className="size-4" aria-hidden />
                                    </button>
                                )}
                            </CartForm>
                        )}
                    </div>
                ) : !hasOptions ? (
                    <span className="shrink-0 text-sm text-muted-foreground">
                        {t('Unavailable')}
                    </span>
                ) : (
                    <button
                        type="button"
                        onClick={toOptions}
                        tabIndex={visible ? 0 : -1}
                        className="h-10 shrink-0 rounded-md border px-3 text-sm font-semibold"
                    >
                        {t('Choose options')}
                    </button>
                )}
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
    visible: boolean;
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
