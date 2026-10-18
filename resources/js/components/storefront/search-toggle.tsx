import { router } from '@inertiajs/react';
import { Search, X } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import type { FormEvent, KeyboardEvent, ReactNode } from 'react';
import { useT } from '@/lib/i18n';
import { search } from '@/routes/storefront';

/**
 * A header search icon that opens a search field across the top of the
 * page, instead of leaving for the catalog. Enter searches; Escape, Cancel
 * or a tap outside closes it and returns focus to the icon.
 */
export function SearchToggle({
    className,
    children,
}: {
    className?: string;
    /** The icon; a search glass when left out. */
    children?: ReactNode;
}) {
    const t = useT();
    const [open, setOpen] = useState(false);
    const panelId = useId();
    const trigger = useRef<HTMLButtonElement>(null);
    const input = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (open) {
            input.current?.focus();
        }
    }, [open]);

    const close = () => {
        setOpen(false);
        trigger.current?.focus();
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const query = input.current?.value.trim() ?? '';
        setOpen(false);
        router.get(search.url(), query === '' ? {} : { q: query });
    };

    const onKeyDown = (event: KeyboardEvent) => {
        if (event.key === 'Escape') {
            event.stopPropagation();
            close();
        }
    };

    return (
        <>
            <button
                ref={trigger}
                type="button"
                aria-label={t('Search products')}
                aria-expanded={open}
                aria-controls={panelId}
                className={className}
                onClick={() => (open ? close() : setOpen(true))}
            >
                {children ?? <Search className="size-4" aria-hidden />}
            </button>
            {open && (
                <div
                    className="store-search-panel fixed inset-0 z-[70]"
                    role="dialog"
                    aria-modal="true"
                    aria-label={t('Search products')}
                    onKeyDown={onKeyDown}
                >
                    <div
                        className="absolute inset-0 bg-black/40"
                        aria-hidden
                        onClick={close}
                    />
                    <form
                        id={panelId}
                        role="search"
                        onSubmit={submit}
                        className="relative flex items-center gap-2 border-b bg-background px-3 py-3 text-foreground shadow-lg sm:px-6"
                    >
                        <Search
                            className="size-5 shrink-0 text-muted-foreground"
                            aria-hidden
                        />
                        <input
                            ref={input}
                            type="search"
                            name="q"
                            enterKeyHint="search"
                            aria-label={t('Search products')}
                            placeholder={t('Search products')}
                            className="h-11 min-w-0 flex-1 bg-transparent text-base outline-none"
                        />
                        <button
                            type="submit"
                            className="store-button h-10 shrink-0 rounded-[var(--radius)] bg-primary px-4 text-sm font-medium text-primary-foreground"
                        >
                            {t('Search')}
                        </button>
                        <button
                            type="button"
                            onClick={close}
                            aria-label={t('Cancel')}
                            className="grid size-10 shrink-0 place-items-center rounded-md hover:bg-accent"
                        >
                            <X className="size-5" aria-hidden />
                        </button>
                    </form>
                </div>
            )}
        </>
    );
}
