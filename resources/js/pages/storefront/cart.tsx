import { Form, Head, Link } from '@inertiajs/react';
import { ImageOff, TriangleAlert } from 'lucide-react';
import CartController from '@/actions/App/Http/Controllers/Storefront/CartController';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useT } from '@/lib/i18n';
import { home } from '@/routes';
import { show as checkout } from '@/routes/checkout';
import { product as productRoute } from '@/routes/storefront';
import type { CartLine, PricedCart } from '@/types';

export default function CartPage({ cart }: { cart: PricedCart | null }) {
    const t = useT();

    if (!cart || cart.lines.length === 0) {
        return (
            <>
                <Head title={t('Cart')} />
                <div className="grid justify-items-center gap-4 py-16 text-center">
                    <h1 className="text-2xl font-semibold">
                        {t('Your cart is empty')}
                    </h1>
                    <Button asChild>
                        <Link href={home()}>{t('Continue shopping')}</Link>
                    </Button>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title={t('Cart')} />
            <h1 className="mb-6 text-2xl font-semibold tracking-tight">
                {t('Cart')}
            </h1>
            <div className="grid gap-8 lg:grid-cols-3">
                <ul className="grid content-start gap-4 lg:col-span-2">
                    {cart.lines.map((line) => (
                        <CartRow key={line.id} line={line} />
                    ))}
                </ul>

                <aside
                    className="grid content-start gap-4 rounded-lg border p-5"
                    aria-label={t('Order summary')}
                >
                    <h2 className="font-semibold">{t('Summary')}</h2>
                    <dl className="grid gap-2 text-sm">
                        <div className="flex justify-between">
                            <dt>{t('Subtotal')}</dt>
                            <dd className="tabular-nums">
                                {cart.subtotal.formatted}
                            </dd>
                        </div>
                        {Number(cart.discount_total.amount) > 0 && (
                            <div className="flex justify-between">
                                <dt>
                                    {t('Discount')} ({cart.coupon_code})
                                </dt>
                                <dd className="tabular-nums">
                                    −{cart.discount_total.formatted}
                                </dd>
                            </div>
                        )}
                        <div className="flex justify-between text-muted-foreground">
                            <dt>{t('Shipping and taxes')}</dt>
                            <dd>{t('Calculated at checkout')}</dd>
                        </div>
                    </dl>

                    <CouponForm cart={cart} />

                    {cart.issues.map((issue) => (
                        <Alert key={issue}>
                            <TriangleAlert />
                            <AlertDescription>{issue}</AlertDescription>
                        </Alert>
                    ))}
                    {cart.can_checkout ? (
                        <Button asChild>
                            <Link href={checkout()}>{t('Checkout')}</Link>
                        </Button>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            {t(
                                'Resolve the highlighted items before checking out.',
                            )}
                        </p>
                    )}
                </aside>
            </div>
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
