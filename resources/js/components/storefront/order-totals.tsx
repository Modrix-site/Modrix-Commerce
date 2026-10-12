import type { MoneyValue } from '@/types';

type Totals = {
    subtotal: MoneyValue;
    discount_total: MoneyValue;
    shipping_total: MoneyValue | null;
    tax_total: MoneyValue;
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

    return (
        <dl className="grid gap-2 text-sm">
            <div className="flex justify-between">
                <dt>Subtotal</dt>
                <dd className="tabular-nums">{totals.subtotal.formatted}</dd>
            </div>
            {discount && (
                <div className="flex justify-between">
                    <dt>
                        Discount
                        {totals.coupon_code ? ` (${totals.coupon_code})` : ''}
                    </dt>
                    <dd className="tabular-nums">
                        −{totals.discount_total.formatted}
                    </dd>
                </div>
            )}
            <div className="flex justify-between">
                <dt>Shipping</dt>
                <dd className="tabular-nums">
                    {totals.shipping_total ? (
                        totals.shipping_total.formatted
                    ) : (
                        <span className="text-muted-foreground">
                            Choose a method
                        </span>
                    )}
                </dd>
            </div>
            <div className="flex justify-between">
                <dt>Tax{totals.prices_include_tax ? ' (included)' : ''}</dt>
                <dd className="tabular-nums">{totals.tax_total.formatted}</dd>
            </div>
            <div className="flex justify-between border-t pt-2 text-base font-semibold">
                <dt>Total</dt>
                <dd className="tabular-nums">{totals.grand_total.formatted}</dd>
            </div>
        </dl>
    );
}
