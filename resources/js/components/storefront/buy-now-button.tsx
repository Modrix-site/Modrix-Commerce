import { Zap } from 'lucide-react';
import { useRef } from 'react';
import { useStoreTheme } from '@/hooks/use-store-theme';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/**
 * Product page: "Buy now" next to "Add to cart". Place it inside the page's
 * add-to-cart form; it submits that form (same variant and quantity) with
 * `buy_now`, so the shopper goes straight to checkout.
 *
 * Shown when the product's own setting says so, or (when the product follows
 * the theme) when the theme's "Show Buy button" setting is on.
 */
export function BuyNowButton({
    showBuyButton,
    disabled = false,
    className,
    label,
}: {
    /** The product's override: true, false, or null to follow the theme. */
    showBuyButton?: boolean | null;
    disabled?: boolean;
    className?: string;
    /** Text for themes that use their own wording (e.g. "BUY NOW"). */
    label?: string;
}) {
    const { theme } = useStoreTheme();
    const t = useT();
    const flag = useRef<HTMLInputElement>(null);

    const themeDefault = theme?.settings?.show_buy_button;
    const enabled =
        showBuyButton ??
        (themeDefault === undefined ? true : Boolean(themeDefault));

    if (!enabled) {
        return null;
    }

    return (
        <>
            <input ref={flag} type="hidden" name="buy_now" defaultValue="" />
            <button
                type="button"
                disabled={disabled}
                className={cn(
                    'inline-flex h-11 w-full items-center justify-center gap-2 rounded-md border border-foreground bg-background px-4 text-sm font-semibold tracking-wide text-foreground transition hover:bg-foreground hover:text-background disabled:pointer-events-none disabled:opacity-50',
                    className,
                )}
                onClick={(e) => {
                    const form = e.currentTarget.form;

                    if (!form || !flag.current) {
                        return;
                    }

                    // The form data is read when the form submits; reset the
                    // flag right after so "Add to cart" stays a plain add.
                    flag.current.value = '1';
                    form.requestSubmit();
                    window.setTimeout(() => {
                        if (flag.current) {
                            flag.current.value = '';
                        }
                    }, 0);
                }}
            >
                <Zap className="size-4" aria-hidden />
                {label ?? t('Buy now')}
            </button>
        </>
    );
}
