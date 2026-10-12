/**
 * Minimal accessible bar chart (no chart library). Values are display-only
 * numbers derived from server-formatted decimal strings.
 */
export function BarChart({
    data,
    label,
    formatValue,
}: {
    data: { label: string; value: number }[];
    label: string;
    formatValue: (value: number) => string;
}) {
    const max = Math.max(...data.map((d) => d.value), 0);
    const width = 100 / Math.max(data.length, 1);

    return (
        <figure className="grid gap-2">
            <svg
                viewBox="0 0 100 40"
                preserveAspectRatio="none"
                className="h-48 w-full"
                role="img"
                aria-label={label}
            >
                {data.map((d, i) => {
                    const height = max === 0 ? 0 : (d.value / max) * 38;

                    return (
                        <rect
                            key={d.label}
                            x={i * width + width * 0.15}
                            y={40 - height}
                            width={width * 0.7}
                            height={height}
                            className="fill-primary"
                        >
                            <title>{`${d.label}: ${formatValue(d.value)}`}</title>
                        </rect>
                    );
                })}
            </svg>
            <figcaption className="flex justify-between text-xs text-muted-foreground">
                <span>{data[0]?.label}</span>
                <span>{data[data.length - 1]?.label}</span>
            </figcaption>
        </figure>
    );
}
