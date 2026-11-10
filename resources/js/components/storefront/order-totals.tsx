import { useT } from '@/lib/i18n';
import type { MoneyValue } from '@/types';

type Totals = {
    subtotal: MoneyValue;
    items_subtotal?: MoneyValue;
    addons_total?: MoneyValue;
    discount_total: MoneyValue;
    /** Carts split the discount into offer savings and the coupon. */
    offer_discount_total?: MoneyValue;
    coupon_discount_total?: MoneyValue;
    shipping_total: MoneyValue | null;
    tax_total: MoneyValue;
    payment_fee_total?: MoneyValue;
    grand_total: MoneyValue;
    prices_include_tax: boolean;
    coupon_code?: string | null;
};

/**
 * Totals table shared by cart, checkout, confirmation and order pages.
 * `shipping_total: null` means shipping is not chosen yet.
 */
export function OrderTotals({ totals }: { totals: Totals }) {
    const discount = Number(totals.discount_total.amount) > 0;
    const hasAddons =
        totals.addons_total !== undefined &&
        Number(totals.addons_total.amount) > 0 &&
        totals.items_subtotal !== undefined;
    const t = useT();

    return (
        <dl className="grid gap-2 text-sm">
            {hasAddons && totals.items_subtotal && totals.addons_total && (
                <>
                    <div className="flex justify-between text-muted-foreground">
                        <dt>{t('Items')}</dt>
                        <dd className="tabular-nums">
                            {totals.items_subtotal.formatted}
                        </dd>
                    </div>
                    <div className="flex justify-between text-muted-foreground">
                        <dt>{t('Add-ons & Warranties')}</dt>
                        <dd className="tabular-nums">
                            +{totals.addons_total.formatted}
                        </dd>
                    </div>
                </>
            )}
            <div className="flex justify-between">
                <dt>{t('Subtotal')}</dt>
                <dd className="tabular-nums">{totals.subtotal.formatted}</dd>
            </div>
            {discount && <DiscountRows totals={totals} />}
            <div className="flex justify-between">
                <dt>{t('Shipping')}</dt>
                <dd className="tabular-nums">
                    {totals.shipping_total ? (
                        totals.shipping_total.formatted
                    ) : (
                        <span className="text-muted-foreground">
                            {t('Choose a method')}
                        </span>
                    )}
                </dd>
            </div>
            <div className="flex justify-between">
                <dt>
                    {totals.prices_include_tax ? t('Tax (included)') : t('Tax')}
                </dt>
                <dd className="tabular-nums">{totals.tax_total.formatted}</dd>
            </div>
            {totals.payment_fee_total &&
                Number(totals.payment_fee_total.amount) > 0 && (
                    <div className="flex justify-between gap-3">
                        <dt>
                            {t('Cash on delivery fee')}
                            <span className="block text-xs text-muted-foreground">
                                {t('Collected by the courier')}
                            </span>
                        </dt>
                        <dd className="tabular-nums">
                            {totals.payment_fee_total.formatted}
                        </dd>
                    </div>
                )}
            <div className="flex justify-between border-t pt-2 text-base font-semibold">
                <dt>{t('Total')}</dt>
                <dd className="tabular-nums">{totals.grand_total.formatted}</dd>
            </div>
        </dl>
    );
}

/**
 * "Offer savings" and "Discount (CODE)" as separate rows when the cart says
 * how the discount splits; otherwise one "Discount" row (orders).
 */
export function DiscountRows({ totals }: { totals: Totals }) {
    const t = useT();
    const offer = totals.offer_discount_total;
    const coupon = totals.coupon_discount_total;

    if (!offer || !coupon || Number(offer.amount) <= 0) {
        return (
            <div className="flex justify-between">
                <dt>
                    {t('Discount')}
                    {totals.coupon_code ? ` (${totals.coupon_code})` : ''}
                </dt>
                <dd className="tabular-nums">
                    −{totals.discount_total.formatted}
                </dd>
            </div>
        );
    }

    return (
        <>
            <div className="flex justify-between">
                <dt>{t('Offer savings')}</dt>
                <dd className="tabular-nums">−{offer.formatted}</dd>
            </div>
            {Number(coupon.amount) > 0 && (
                <div className="flex justify-between">
                    <dt>
                        {t('Discount')}
                        {totals.coupon_code ? ` (${totals.coupon_code})` : ''}
                    </dt>
                    <dd className="tabular-nums">−{coupon.formatted}</dd>
                </div>
            )}
        </>
    );
}
