import type { MoneyValue } from '@/types';

type Day = {
    date: string;
    orders: number;
    sales: MoneyValue;
    percent: number;
};

export default function SalesTrend({
    days,
    total,
    series,
}: {
    days: number;
    total: MoneyValue;
    series: Day[];
}) {
    return (
        <div className="grid gap-3">
            <p className="text-sm text-muted-foreground">
                {total.formatted} in the last {days} days
            </p>
            <ul className="grid gap-1.5" aria-label="Daily sales">
                {series.map((day) => (
                    <li
                        key={day.date}
                        className="grid grid-cols-[5.5rem_1fr_auto] items-center gap-2 text-xs"
                    >
                        <span className="text-muted-foreground tabular-nums">
                            {day.date}
                        </span>
                        <span className="h-2 rounded bg-muted">
                            <span
                                className="block h-2 rounded bg-primary"
                                style={{ width: `${day.percent}%` }}
                            />
                        </span>
                        <span className="tabular-nums">
                            {day.sales.formatted}
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}
