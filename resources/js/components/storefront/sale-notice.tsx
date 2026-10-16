import { Zap } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { SaleInfo } from '@/types';

/** Remaining time as "2d 03:14:09" or "03:14:09"; null once over. */
export function remaining(endsAt: string, now: number): string | null {
    const ms = new Date(endsAt).getTime() - now;

    if (ms <= 0) {
        return null;
    }

    const total = Math.floor(ms / 1000);
    const days = Math.floor(total / 86400);
    const pad = (n: number) => String(n).padStart(2, '0');
    const clock = `${pad(Math.floor((total % 86400) / 3600))}:${pad(Math.floor((total % 3600) / 60))}:${pad(total % 60)}`;

    return days > 0 ? `${days}d ${clock}` : clock;
}

/** Ticks every second while enabled; returns the current time in ms. */
export function useNow(enabled = true): number {
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        if (!enabled) {
            return;
        }

        const id = window.setInterval(() => setNow(Date.now()), 1000);

        return () => window.clearInterval(id);
    }, [enabled]);

    return now;
}

/** Whether a sale is still running (sales without an end never expire here). */
function useSaleLeft(sale: SaleInfo | null | undefined) {
    const now = useNow(Boolean(sale?.ends_at));
    const left = sale?.ends_at ? remaining(sale.ends_at, now) : null;

    return { over: Boolean(sale?.ends_at) && left === null, left };
}

/**
 * Product card: a small "⚡ Flash sale −20%" tag next to the price. Uses the
 * theme's primary colour so it fits every theme.
 */
export function SaleTag({
    sale,
    className,
}: {
    sale: SaleInfo | null | undefined;
    className?: string;
}) {
    const { over } = useSaleLeft(sale);

    if (!sale || over) {
        return null;
    }

    return (
        <span
            className={cn(
                'inline-flex w-fit items-center gap-1 rounded-full bg-primary px-2 py-0.5 text-xs leading-5 font-semibold text-primary-foreground',
                className,
            )}
            title={sale.name}
        >
            <Zap className="size-3" aria-hidden />
            {sale.label} {sale.badge}
        </span>
    );
}

/**
 * Product page, under the price: "⚡ Weekend Flash Sale · 20% off ·
 * Ends in 02:14:09". Hidden once the sale is over.
 */
export function SaleNotice({
    sale,
    className,
}: {
    sale: SaleInfo | null | undefined;
    className?: string;
}) {
    const t = useT();
    const { over, left } = useSaleLeft(sale);

    if (!sale || over) {
        return null;
    }

    return (
        <div
            className={cn(
                'flex flex-wrap items-center gap-x-3 gap-y-1 rounded-lg border border-primary/30 bg-primary/5 px-3 py-2 text-sm text-foreground',
                className,
            )}
            role="status"
        >
            <span className="inline-flex items-center gap-1 rounded-full bg-primary px-2 py-0.5 text-xs font-semibold text-primary-foreground">
                <Zap className="size-3" aria-hidden />
                {sale.badge}
            </span>
            <span className="font-semibold">{sale.name}</span>
            {sale.countdown && left !== null && (
                <span>
                    {t('Ends in')}{' '}
                    <span className="font-mono font-semibold tabular-nums">
                        {left}
                    </span>
                </span>
            )}
        </div>
    );
}
