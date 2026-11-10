import { ArrowDown, ArrowUp, ImageOff, Plus, Search, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { MoneyValue } from '@/types';

export type PickedProduct = {
    id: number;
    name: string;
    status: string;
    price: MoneyValue | null;
    image: string | null;
};

/**
 * Picks products by name or SKU, keeps them in order (moved with the arrows)
 * and submits them as `<name>[]`.
 */
export function ProductPicker({
    name,
    searchUrl,
    value,
    onChange,
    max,
}: {
    name: string;
    searchUrl: string;
    value: PickedProduct[];
    onChange: (products: PickedProduct[]) => void;
    max: number;
}) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<PickedProduct[]>([]);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            setLoading(true);
            fetch(`${searchUrl}?q=${encodeURIComponent(query)}`, {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            })
                .then((r) => (r.ok ? r.json() : { products: [] }))
                .then((data: { products: PickedProduct[] }) =>
                    setResults(data.products),
                )
                .catch(() => undefined)
                .finally(() => setLoading(false));
        }, 250);

        return () => {
            controller.abort();
            window.clearTimeout(timer);
        };
    }, [query, searchUrl]);

    const picked = new Set(value.map((p) => p.id));
    const move = (index: number, by: -1 | 1) => {
        const next = [...value];
        const [item] = next.splice(index, 1);
        next.splice(index + by, 0, item);
        onChange(next);
    };

    return (
        <div className="grid gap-3">
            {value.map((p) => (
                <input
                    key={p.id}
                    type="hidden"
                    name={`${name}[]`}
                    value={p.id}
                />
            ))}

            {value.length > 0 && (
                <ol className="grid gap-2">
                    {value.map((p, i) => (
                        <li
                            key={p.id}
                            className="flex items-center gap-3 rounded-md border p-2"
                        >
                            <Thumb image={p.image} />
                            <span className="min-w-0 flex-1 truncate text-sm font-medium">
                                {p.name}
                            </span>
                            {p.status !== 'published' && (
                                <Badge variant="outline">Not published</Badge>
                            )}
                            <span className="text-sm text-muted-foreground tabular-nums">
                                {p.price?.formatted ?? '—'}
                            </span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                disabled={i === 0}
                                onClick={() => move(i, -1)}
                                aria-label={`Move ${p.name} up`}
                            >
                                <ArrowUp />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                disabled={i === value.length - 1}
                                onClick={() => move(i, 1)}
                                aria-label={`Move ${p.name} down`}
                            >
                                <ArrowDown />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={() =>
                                    onChange(value.filter((x) => x.id !== p.id))
                                }
                                aria-label={`Remove ${p.name}`}
                            >
                                <X />
                            </Button>
                        </li>
                    ))}
                </ol>
            )}

            <div className="relative">
                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    type="search"
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    placeholder="Search products by name or SKU"
                    className="pl-9"
                    aria-label="Search products"
                />
            </div>

            <ul className="grid max-h-64 gap-1 overflow-y-auto">
                {results
                    .filter((p) => !picked.has(p.id))
                    .map((p) => (
                        <li
                            key={p.id}
                            className="flex items-center gap-3 rounded-md px-2 py-1.5 hover:bg-muted"
                        >
                            <Thumb image={p.image} />
                            <span className="min-w-0 flex-1 truncate text-sm">
                                {p.name}
                            </span>
                            <span className="text-sm text-muted-foreground tabular-nums">
                                {p.price?.formatted ?? '—'}
                            </span>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                disabled={value.length >= max}
                                onClick={() => onChange([...value, p])}
                            >
                                <Plus /> Add
                            </Button>
                        </li>
                    ))}
                {!loading && results.length === 0 && (
                    <li className="px-2 py-1.5 text-sm text-muted-foreground">
                        No products found.
                    </li>
                )}
            </ul>
            {value.length >= max && (
                <p className="text-xs text-muted-foreground">
                    Up to {max} products.
                </p>
            )}
        </div>
    );
}

function Thumb({ image }: { image: string | null }) {
    return image ? (
        <img src={image} alt="" className="size-9 rounded object-cover" />
    ) : (
        <span className="flex size-9 items-center justify-center rounded bg-muted text-muted-foreground">
            <ImageOff className="size-4" />
        </span>
    );
}
