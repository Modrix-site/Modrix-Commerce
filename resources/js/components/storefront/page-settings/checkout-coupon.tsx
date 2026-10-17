import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import CartController from '@/actions/App/Http/Controllers/Storefront/CartController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useT } from '@/lib/i18n';

/**
 * Coupon field in the checkout summary (Checkout tab). It uses the cart's
 * coupon endpoints; it is not a nested form, as the summary sits inside
 * the checkout form.
 */
export function CheckoutCoupon({ code }: { code: string | null }) {
    const t = useT();
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const [value, setValue] = useState('');
    const [busy, setBusy] = useState(false);
    const options = {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => setBusy(false),
    } as const;

    if (code) {
        return (
            <div className="flex items-center justify-between gap-2 text-sm">
                <span>
                    {t('Coupon')} <strong>{code}</strong>
                </span>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    disabled={busy}
                    onClick={() => {
                        setBusy(true);
                        router.delete(
                            CartController.removeCoupon.url(),
                            options,
                        );
                    }}
                >
                    {t('Remove')}
                </Button>
            </div>
        );
    }

    const apply = () => {
        if (value.trim() === '') {
            return;
        }

        setBusy(true);
        router.post(
            CartController.applyCoupon.url(),
            { code: value.trim() },
            options,
        );
    };

    return (
        <div className="grid gap-2">
            <div className="flex gap-2">
                <Input
                    value={value}
                    onChange={(event) => setValue(event.target.value)}
                    onKeyDown={(event) => {
                        // Enter applies the coupon instead of placing the order.
                        if (event.key === 'Enter') {
                            event.preventDefault();
                            apply();
                        }
                    }}
                    placeholder={t('Coupon code')}
                    aria-label={t('Coupon code')}
                    autoComplete="off"
                />
                <Button
                    type="button"
                    variant="outline"
                    disabled={busy}
                    onClick={apply}
                >
                    {t('Apply')}
                </Button>
            </div>
            <InputError message={errors.code} />
        </div>
    );
}
