import { useEffect, useId, useRef, useState } from 'react';
import type { KeyboardEvent, ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type TabItem = {
    id: string;
    label: ReactNode;
    content: ReactNode;
    /** Marks the tab (e.g. it contains a validation error). */
    hasError?: boolean;
};

/**
 * Accessible tabs (WAI-ARIA tablist with arrow-key navigation). Hidden panels
 * stay mounted, so a form spanning several tabs still submits every field.
 * The selected tab is kept in the URL hash so reloads and links reopen it.
 */
export function Tabs({
    items,
    label,
    focusErrors = true,
    onChange,
}: {
    items: TabItem[];
    label: string;
    focusErrors?: boolean;
    onChange?: (id: string) => void;
}) {
    const baseId = useId();
    const fromHash = () => {
        const hash =
            typeof window === 'undefined' ? '' : window.location.hash.slice(1);

        return items.some((i) => i.id === hash) ? hash : items[0]?.id;
    };
    const [active, setActive] = useState<string | undefined>(fromHash);
    const tabRefs = useRef<Record<string, HTMLButtonElement | null>>({});

    const select = (id: string, focus = false) => {
        setActive(id);
        window.history.replaceState(window.history.state, '', `#${id}`);

        if (focus) {
            tabRefs.current[id]?.focus();
        }
    };

    // After a failed save, open the first tab that needs attention.
    const firstError = items.find((i) => i.hasError)?.id;
    useEffect(() => {
        if (focusErrors && firstError && firstError !== active) {
            setActive(firstError);
        }
        // Only react to errors appearing, not to the user switching tabs.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [firstError]);

    useEffect(() => {
        if (active) {
            onChange?.(active);
        }
    }, [active, onChange]);

    const onKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
        const index = items.findIndex((i) => i.id === active);
        const next = {
            ArrowRight: (index + 1) % items.length,
            ArrowLeft: (index - 1 + items.length) % items.length,
            Home: 0,
            End: items.length - 1,
        }[event.key];

        if (next !== undefined) {
            event.preventDefault();
            select(items[next].id, true);
        }
    };

    return (
        <div className="grid gap-4">
            <div
                role="tablist"
                aria-label={label}
                onKeyDown={onKeyDown}
                className="flex w-fit max-w-full gap-1 overflow-x-auto overflow-y-hidden rounded-lg bg-muted p-1"
            >
                {items.map((item) => {
                    const selected = item.id === active;

                    return (
                        <button
                            key={item.id}
                            ref={(el) => {
                                tabRefs.current[item.id] = el;
                            }}
                            type="button"
                            role="tab"
                            id={`${baseId}-tab-${item.id}`}
                            aria-selected={selected}
                            aria-controls={`${baseId}-panel-${item.id}`}
                            tabIndex={selected ? 0 : -1}
                            onClick={() => select(item.id)}
                            className={cn(
                                'flex shrink-0 items-center gap-2 rounded-md px-3 py-1.5 text-xs font-medium whitespace-nowrap transition-colors',
                                selected
                                    ? 'bg-card text-foreground shadow-xs'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {item.label}
                            {item.hasError && (
                                <>
                                    <span
                                        className="size-1.5 rounded-full bg-destructive"
                                        aria-hidden
                                    />
                                    <span className="sr-only">
                                        (needs attention)
                                    </span>
                                </>
                            )}
                        </button>
                    );
                })}
            </div>
            {items.map((item) => (
                <div
                    key={item.id}
                    role="tabpanel"
                    id={`${baseId}-panel-${item.id}`}
                    aria-labelledby={`${baseId}-tab-${item.id}`}
                    hidden={item.id !== active}
                    tabIndex={0}
                    className="outline-none"
                >
                    {item.content}
                </div>
            ))}
        </div>
    );
}
