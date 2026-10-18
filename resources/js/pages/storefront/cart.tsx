import { Form, Head, Link } from '@inertiajs/react';
import { Suspense } from 'react';
import { useStoreTheme } from '@/hooks/use-store-theme';
import { themeCart } from '@/lib/theme-pages';
import { ImageOff, TriangleAlert } from 'lucide-react';
import CartController from '@/actions/App/Http/Controllers/Storefront/CartController';
import { ExtensionSlot } from '@/components/extensions/extension-slot';
import type { SlotItem } from '@/components/extensions/extension-slot';
import { PlaceSlot } from '@/components/extensions/place-slot';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { CartCrossSells } from '@/components/storefront/page-settings/cart-cross-sells';
import { DiscountRows } from '@/components/storefront/order-totals';
import { FreeShippingBar } from '@/components/storefront/page-settings/free-shipping-bar';
import type { FreeShipping } from '@/components/storefront/page-settings/free-shipping-bar';
import { TrustBadges } from '@/components/storefront/page-settings/trust-badges';
import { useStoreSettings } from '@/hooks/use-store-settings';
import { useT } from '@/lib/i18n';
import { home } from '@/routes';
import { show as checkout } from '@/routes/checkout';
import { product as productRoute } from '@/routes/storefront';
import type { CartLine, PricedCart, ProductCard } from '@/types';

export type CartProps = {
    cart: PricedCart | null;
    slots?: SlotItem[];
    /** Cart tab: free-shipping progress (null when off or no threshold). */
    freeShipping?: FreeShipping | null;
    /** Cart tab: related products of the cart's items. */
    crossSells?: ProductCard[];
};
export default function CartPage(props: CartProps) {
    const { theme } = useStoreTheme();
    const CustomCart = themeCart(theme?.name);
    return CustomCart ? (
        <Suspense fallback={<p role="status">Loading cart…</p>}>
            <CustomCart {...props} />
        </Suspense>
    ) : (
        <PlatformCartPage {...props} />
    );
}
export function PlatformCartPage({
    cart,
    slots,
    freeShipping,
    crossSells,
}: CartProps) {
    const t = useT();
    // Cart tab settings (customizer).
    const pageSettings = useStoreSettings().cart;
    const { theme } = useStoreTheme();
    const settings = theme?.settings ?? {};

    if (!cart || cart.lines.length === 0) {
        return (
            <>
                <Head title={t('Cart')} />
                <div className="grid justify-items-center gap-4 py-16 text-center">
                    <h1 className="text-2xl font-semibold">
                        {String(
                            settings.cart_empty_heading ??
                                t('Your cart is empty'),
                        )}
                    </h1>
                    <Button asChild>
                        <Link href={home()}>
                            {String(
                                settings.cart_continue_label ??
                                    t('Continue shopping'),
                            )}
                        </Link>
                    </Button>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title={t('Cart')} />
            <h1 className="mb-6 text-2xl font-semibold tracking-tight">
                {String(settings.cart_heading ?? t('Cart'))}
            </h1>
            <div className="grid gap-8 lg:grid-cols-3">
                <ul className="grid content-start gap-4 lg:col-span-2">
                    {cart.lines.map((line) => (
                        <CartRow key={line.id} line={line} />
                    ))}
                    <PlaceSlot place="cart.after_lines" listItem />
                </ul>

                <aside
                    className="grid content-start gap-4 rounded-lg border p-5"
                    aria-label={t('Order summary')}
                >
                    <h2 className="font-semibold">
                        {String(settings.cart_summary_heading ?? t('Summary'))}
                    </h2>
                    <dl className="grid gap-2 text-sm">
                        <div className="flex justify-between">
                            <dt>{t('Subtotal')}</dt>
                            <dd className="tabular-nums">
                                {cart.subtotal.formatted}
                            </dd>
                        </div>
                        {Number(cart.discount_total.amount) > 0 && (
                            <DiscountRows totals={cart} />
                        )}
                        <div className="flex justify-between text-muted-foreground">
                            <dt>{t('Shipping and taxes')}</dt>
                            <dd>{t('Calculated at checkout')}</dd>
                        </div>
                    </dl>

                    <FreeShippingBar data={freeShipping} />

                    <ExtensionSlot items={slots} className="grid gap-3" />

                    {pageSettings.coupon &&
                        settings.cart_show_coupon !== false && (
                            <CouponForm cart={cart} />
                        )}

                    {cart.issues.map((issue) => (
                        <Alert key={issue}>
                            <TriangleAlert />
                            <AlertDescription>{issue}</AlertDescription>
                        </Alert>
                    ))}
                    {cart.can_checkout ? (
                        <Button className="store-button" asChild>
                            <Link href={checkout()}>
                                {String(
                                    settings.cart_checkout_label ??
                                        t('Checkout'),
                                )}
                            </Link>
                        </Button>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            {t(
                                'Resolve the highlighted items before checking out.',
                            )}
                        </p>
                    )}
                    {pageSettings.trust_badges && <TrustBadges />}
                    {pageSettings.continue_link && (
                        <Link
                            href={home()}
                            className="text-center text-sm underline underline-offset-4"
                        >
                            {t('Continue shopping')}
                        </Link>
                    )}
                </aside>
            </div>
            <CartCrossSells products={crossSells} className="mt-12" />
        </>
    );
}

function CouponForm({ cart }: { cart: PricedCart }) {
    const t = useT();

    if (cart.coupon_code) {
        return (
            <div className="flex items-center justify-between gap-2 text-sm">
                <span>
                    {t('Coupon')} <strong>{cart.coupon_code}</strong>
                    {cart.coupon_error && (
                        <span className="block text-destructive">
                            {cart.coupon_error}
                        </span>
                    )}
                </span>
                <Form
                    {...CartController.removeCoupon.form()}
                    options={{ preserveScroll: true }}
                >
                    <Button type="submit" variant="ghost" size="sm">
                        {t('Remove')}
                    </Button>
                </Form>
            </div>
        );
    }

    return (
        <Form
            {...CartController.applyCoupon.form()}
            options={{ preserveScroll: true }}
            className="grid gap-2"
        >
            {({ processing, errors }) => (
                <>
                    <div className="flex gap-2">
                        <Input
                            name="code"
                            placeholder={t('Coupon code')}
                            aria-label={t('Coupon code')}
                            autoComplete="off"
                        />
                        <Button
                            type="submit"
                            variant="outline"
                            disabled={processing}
                        >
                            {t('Apply')}
                        </Button>
                    </div>
                    <InputError message={errors.code} />
                </>
            )}
        </Form>
    );
}

function CartRow({ line }: { line: CartLine }) {
    const t = useT();

    return (
        <li className="grid grid-cols-[4rem_1fr] gap-4 rounded-lg border p-4 sm:grid-cols-[5rem_1fr_auto]">
            <div className="size-16 overflow-hidden rounded border bg-muted sm:size-20">
                {line.image ? (
                    <img
                        src={line.image.url}
                        alt={line.image.alt ?? ''}
                        className="size-full object-cover"
                    />
                ) : (
                    <div className="flex size-full items-center justify-center text-muted-foreground">
                        <ImageOff className="size-5" aria-hidden />
                    </div>
                )}
            </div>
            <div className="grid content-start gap-1">
                <Link
                    href={productRoute(line.product_slug)}
                    className="font-medium hover:underline"
                >
                    {line.product_name}
                </Link>
                {line.variant_name && (
                    <span className="text-sm text-muted-foreground">
                        {line.variant_name}
                    </span>
                )}
                <span className="text-sm tabular-nums">
                    {t(':price each', {
                        price: line.unit_price
                            ? line.unit_price.formatted
                            : '—',
                    })}
                </span>
                {line.offer_label &&
                    line.offer_discount &&
                    Number(line.offer_discount.amount) > 0 && (
                        <span className="text-sm text-primary">
                            {t(':label: −:amount', {
                                label: line.offer_label,
                                amount: line.offer_discount.formatted,
                            })}
                        </span>
                    )}
                {line.notices.map((notice) => (
                    <p key={notice} className="text-sm text-destructive">
                        {notice}
                    </p>
                ))}
            </div>
            <div className="col-span-2 flex items-center gap-2 sm:col-span-1 sm:flex-col sm:items-end">
                <Form
                    {...CartController.update.form(line.id)}
                    options={{ preserveScroll: true }}
                    className="flex items-center gap-2"
                >
                    {({ processing, errors }) => (
                        <>
                            <Input
                                name="quantity"
                                type="number"
                                min={0}
                                max={line.available ?? 999}
                                defaultValue={line.quantity}
                                aria-label={t('Quantity of :product', {
                                    product: line.product_name,
                                })}
                                className="w-20"
                            />
                            <Button
                                type="submit"
                                variant="outline"
                                size="sm"
                                disabled={processing}
                            >
                                {t('Update')}
                            </Button>
                            <InputError message={errors.quantity} />
                        </>
                    )}
                </Form>
                <Form
                    {...CartController.destroy.form(line.id)}
                    options={{ preserveScroll: true }}
                >
                    <Button type="submit" variant="ghost" size="sm">
                        {t('Remove')}
                    </Button>
                </Form>
                <span className="ml-auto font-medium tabular-nums sm:ml-0">
                    {line.subtotal.formatted}
                </span>
            </div>
        </li>
    );
}
