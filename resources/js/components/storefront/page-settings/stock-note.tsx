import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/** How many low stock counts as "low". */
export const LOW_STOCK = 5;

/**
 * Stock under the buy button: "low" shows "Only 3 left" at 5 or fewer,
 * "always" also shows "In stock". Nothing when the count is unknown.
 */
export function StockNote({
    mode,
    quantity,
    className,
}: {
    mode: 'off' | 'low' | 'always';
    quantity: number | null | undefined;
    className?: string;
}) {
    const t = useT();

    if (
        mode === 'off' ||
        quantity === null ||
        quantity === undefined ||
        quantity <= 0
    ) {
        return null;
    }

    if (quantity <= LOW_STOCK) {
        return (
            <p className={cn('text-sm text-muted-foreground', className)}>
                {t('Only :count left.', { count: quantity })}
            </p>
        );
    }

    return mode === 'always' ? (
        <p className={cn('text-sm text-muted-foreground', className)}>
            {t('In stock')}
        </p>
    ) : null;
}
