import { ChevronDown } from 'lucide-react';
import { useEffect, useId, useMemo, useRef, useState } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    id?: string;
    name?: string;
    value: string;
    onChange: (value: string) => void;
    options: string[];
    placeholder?: string;
    required?: boolean;
    autoComplete?: string;
    invalid?: boolean;
};

/**
 * A type-to-filter region picker with autosuggest.
 *
 * Renders a text input and a dropdown of the matching regions for the selected
 * country. Shoppers can type to narrow the list, pick with the mouse or the
 * keyboard, or enter a value that is not in the list (some countries have no
 * fixed region list). Keyboard: Up/Down to move, Enter to pick, Escape to
 * close.
 */
export default function RegionCombobox({
    id,
    name,
    value,
    onChange,
    options,
    placeholder,
    required = false,
    autoComplete,
    invalid = false,
}: Props) {
    const generatedId = useId();
    const inputId = id ?? generatedId;
    const listId = `${inputId}-list`;
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState(value);
    const [active, setActive] = useState(-1);
    const container = useRef<HTMLDivElement>(null);

    // Keep the visible text in step when the value is set from outside
    // (a saved address, or a country change clearing the region).
    useEffect(() => {
        setQuery(value);
    }, [value]);

    const matches = useMemo(() => {
        const needle = query.trim().toLowerCase();

        if (needle === '' || needle === value.toLowerCase()) {
            return options;
        }

        return options.filter((option) =>
            option.toLowerCase().includes(needle),
        );
    }, [options, query, value]);

    useEffect(() => {
        if (!open) {
            return;
        }

        const onPointerDown = (event: MouseEvent) => {
            if (!container.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        };

        document.addEventListener('mousedown', onPointerDown);

        return () => document.removeEventListener('mousedown', onPointerDown);
    }, [open]);

    const commit = (next: string) => {
        setQuery(next);
        onChange(next);
        setOpen(false);
        setActive(-1);
    };

    const onKeyDown = (event: React.KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setOpen(true);
            setActive((i) => Math.min(i + 1, matches.length - 1));
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActive((i) => Math.max(i - 1, 0));
        } else if (event.key === 'Enter') {
            if (open && active >= 0 && matches[active] !== undefined) {
                event.preventDefault();
                commit(matches[active]);
            }
        } else if (event.key === 'Escape') {
            setOpen(false);
            setActive(-1);
        }
    };

    return (
        <div ref={container} className="relative">
            <input
                id={inputId}
                name={name}
                type="text"
                role="combobox"
                aria-expanded={open}
                aria-controls={listId}
                aria-autocomplete="list"
                aria-invalid={invalid || undefined}
                autoComplete={autoComplete}
                required={required}
                value={query}
                placeholder={placeholder}
                className={cn(
                    'h-9 w-full rounded-md border border-input bg-transparent px-3 pr-9 text-sm shadow-xs outline-none dark:bg-input/30',
                    'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
                    invalid && 'border-destructive',
                )}
                onChange={(event) => {
                    setQuery(event.target.value);
                    onChange(event.target.value);
                    setOpen(true);
                    setActive(-1);
                }}
                onFocus={() => setOpen(true)}
                onKeyDown={onKeyDown}
            />
            <button
                type="button"
                tabIndex={-1}
                aria-label={placeholder ?? 'Show options'}
                className="absolute inset-y-0 right-0 flex w-9 items-center justify-center text-muted-foreground"
                onMouseDown={(event) => {
                    event.preventDefault();
                    setOpen((o) => !o);
                }}
            >
                <ChevronDown className="size-4" />
            </button>

            {open && matches.length > 0 && (
                <ul
                    id={listId}
                    role="listbox"
                    className="absolute z-50 mt-1 max-h-64 w-full overflow-auto rounded-md border bg-popover py-1 text-sm shadow-md"
                >
                    {matches.map((option, index) => (
                        <li
                            key={option}
                            role="option"
                            aria-selected={option === value}
                            className={cn(
                                'cursor-pointer px-3 py-1.5',
                                index === active &&
                                    'bg-accent text-accent-foreground',
                                option === value && 'font-medium',
                            )}
                            onMouseEnter={() => setActive(index)}
                            onMouseDown={(event) => {
                                event.preventDefault();
                                commit(option);
                            }}
                        >
                            {option}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
