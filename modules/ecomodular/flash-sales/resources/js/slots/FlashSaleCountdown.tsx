import { Zap } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useT } from '@/lib/i18n';

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

/** Ticks every second; returns the current time in ms. */
export function useNow(): number {
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        const id = window.setInterval(() => setNow(Date.now()), 1000);

        return () => window.clearInterval(id);
    }, []);

    return now;
}

/**
 * Product page: "Weekend Flash Sale — ends in 02:14:09". Hidden once over.
 */
export default function FlashSaleCountdown({
    name,
    endsAt,
}: {
    name: string;
    endsAt: string;
}) {
    const t = useT();
    const left = remaining(endsAt, useNow());

    if (left === null) {
        return null;
    }

    return (
        <div
            className="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-lg border border-orange-300 bg-orange-50 px-4 py-3 text-sm text-orange-900 dark:border-orange-900 dark:bg-orange-950 dark:text-orange-100"
            role="status"
        >
            <Zap className="size-4 shrink-0" aria-hidden />
            <span className="font-semibold">{name}</span>
            <span>
                {t('Ends in')}{' '}
                <span className="font-mono font-semibold tabular-nums">
                    {left}
                </span>
            </span>
        </div>
    );
}
