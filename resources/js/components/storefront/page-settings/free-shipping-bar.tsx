import { Truck } from 'lucide-react';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { MoneyValue } from '@/types';

export type FreeShipping = { threshold: MoneyValue; remaining: MoneyValue };

/** "You're $40.00 away from free shipping", with a progress bar. */
export function FreeShippingBar({
    data,
    className,
}: {
    data: FreeShipping | null | undefined;
    className?: string;
}) {
    const t = useT();

    if (!data) {
        return null;
    }

    const threshold = Number(data.threshold.amount);
    const remaining = Number(data.remaining.amount);
    const reached = remaining <= 0;
    const percent =
        threshold > 0
            ? Math.min(
                  100,
                  Math.round(((threshold - remaining) / threshold) * 100),
              )
            : 100;

    return (
        <div className={cn('store-free-shipping grid gap-2', className)}>
            <p className="flex items-center gap-2 text-sm">
                <Truck className="size-4 shrink-0" aria-hidden />
                {reached
                    ? t("You've got free shipping!")
                    : t("You're :amount away from free shipping", {
                          amount: data.remaining.formatted,
                      })}
            </p>
            <div
                role="progressbar"
                aria-label={t('Free-shipping progress')}
                aria-valuemin={0}
                aria-valuemax={100}
                aria-valuenow={percent}
                className="h-2 overflow-hidden rounded-full bg-muted"
            >
                <div
                    className="h-full rounded-full bg-primary transition-[width] motion-reduce:transition-none"
                    style={{ width: `${percent}%` }}
                />
            </div>
        </div>
    );
}
