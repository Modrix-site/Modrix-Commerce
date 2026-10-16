import { ArrowDown, ArrowUp, ImageOff, Plus, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

export type PickerItem = {
    id: number;
    name: string;
    sku: string | null;
    image: string | null;
};

/**
 * Pick products in order (related products, upsells): search, add, reorder
 * with the arrows, remove.
 */
export function ProductPicker({
    id,
    label,
    value,
    onChange,
    searchUrl,
    max,
    excludeId,
}: {
    id: string;
    label: string;
    value: PickerItem[];
    onChange: (items: PickerItem[]) => void;
    searchUrl: string;
    max: number;
    excludeId?: number;
}) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<PickerItem[]>([]);

    useEffect(() => {
        if (query.trim() === '') {
            setResults([]);

            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            fetch(`${searchUrl}?q=${encodeURIComponent(query)}`, {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            })
                .then((r) => (r.ok ? r.json() : []))
                .then((items: PickerItem[]) => setResults(items))
                .catch(() => undefined);
        }, 250);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [query, searchUrl]);

    const chosen = new Set(value.map((item) => item.id));
    const options = results.filter(
        (item) => !chosen.has(item.id) && item.id !== excludeId,
    );
    const full = value.length >= max;

    const move = (index: number, by: number) => {
        const next = [...value];
        const [item] = next.splice(index, 1);
        next.splice(index + by, 0, item);
        onChange(next);
    };

    return (
        <div className="grid gap-2">
            <label htmlFor={id} className="text-sm font-medium">
                {label}{' '}
                <span className="font-normal text-muted-foreground">
                    ({value.length}/{max})
                </span>
            </label>
            {value.length > 0 && (
                <ul className="grid divide-y rounded-md border">
                    {value.map((item, index) => (
                        <li
                            key={item.id}
                            className="flex items-center gap-2 p-2 text-sm"
                        >
                            <Thumb item={item} />
                            <span className="min-w-0 flex-1 truncate">
                                {item.name}
                                {item.sku && (
                                    <span className="ml-2 text-xs text-muted-foreground">
                                        {item.sku}
                                    </span>
                                )}
                            </span>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                className="size-7"
                                disabled={index === 0}
                                onClick={() => move(index, -1)}
                                aria-label={`Move ${item.name} up`}
                            >
                                <ArrowUp className="size-4" />
                            </Button>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                className="size-7"
                                disabled={index === value.length - 1}
                                onClick={() => move(index, 1)}
                                aria-label={`Move ${item.name} down`}
                            >
                                <ArrowDown className="size-4" />
                            </Button>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                className="size-7"
                                onClick={() =>
                                    onChange(
                                        value.filter((v) => v.id !== item.id),
                                    )
                                }
                                aria-label={`Remove ${item.name}`}
                            >
                                <X className="size-4" />
                            </Button>
                        </li>
                    ))}
                </ul>
            )}
            <Input
                id={id}
                type="search"
                value={query}
                disabled={full}
                placeholder={
                    full
                        ? `At most ${max} products`
                        : 'Search products by name or SKU'
                }
                onChange={(e) => setQuery(e.target.value)}
            />
            {options.length > 0 && (
                <ul className="grid max-h-60 divide-y overflow-auto rounded-md border">
                    {options.map((item) => (
                        <li key={item.id}>
                            <button
                                type="button"
                                className="flex w-full items-center gap-2 p-2 text-left text-sm hover:bg-muted"
                                onClick={() => {
                                    onChange([...value, item]);
                                    setQuery('');
                                }}
                            >
                                <Thumb item={item} />
                                <span className="min-w-0 flex-1 truncate">
                                    {item.name}
                                </span>
                                <Plus className="size-4 text-muted-foreground" />
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

function Thumb({ item }: { item: PickerItem }) {
    return item.image ? (
        <img
            src={item.image}
            alt=""
            className="size-8 shrink-0 rounded border object-cover"
        />
    ) : (
        <span className="flex size-8 shrink-0 items-center justify-center rounded border text-muted-foreground">
            <ImageOff className="size-4" />
        </span>
    );
}
