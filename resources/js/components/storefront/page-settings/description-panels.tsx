import { ChevronDown } from 'lucide-react';
import { useId, useRef, useState } from 'react';
import type { KeyboardEvent, ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type DescriptionSection = {
    id: string;
    title: string;
    content: ReactNode;
};

/**
 * The product description (and details) shown open, as tabs, or as an
 * accordion, as the Product tab sets.
 */
export function DescriptionPanels({
    mode,
    sections,
    className,
}: {
    mode: 'open' | 'tabs' | 'accordion';
    sections: DescriptionSection[];
    className?: string;
}) {
    const baseId = useId();
    const [active, setActive] = useState(0);
    const tabs = useRef<(HTMLButtonElement | null)[]>([]);

    if (sections.length === 0) {
        return null;
    }

    if (mode === 'tabs' && sections.length > 1) {
        const onKeyDown = (event: KeyboardEvent) => {
            const step = { ArrowRight: 1, ArrowLeft: -1 }[event.key];
            const edge = { Home: 0, End: sections.length - 1 }[event.key];

            if (step === undefined && edge === undefined) {
                return;
            }

            event.preventDefault();
            const next =
                edge ??
                (active + (step ?? 0) + sections.length) % sections.length;
            setActive(next);
            tabs.current[next]?.focus();
        };

        return (
            <section
                className={cn(
                    'store-description grid gap-3 border-t pt-5',
                    className,
                )}
            >
                <div
                    role="tablist"
                    className="flex gap-4 border-b"
                    onKeyDown={onKeyDown}
                >
                    {sections.map((section, i) => (
                        <button
                            key={section.id}
                            ref={(el) => {
                                tabs.current[i] = el;
                            }}
                            type="button"
                            role="tab"
                            id={`${baseId}-tab-${i}`}
                            aria-selected={i === active}
                            aria-controls={`${baseId}-panel-${i}`}
                            tabIndex={i === active ? 0 : -1}
                            onClick={() => setActive(i)}
                            className={cn(
                                '-mb-px border-b-2 pb-2 text-sm font-semibold',
                                i === active
                                    ? 'border-foreground'
                                    : 'border-transparent text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {section.title}
                        </button>
                    ))}
                </div>
                {sections.map((section, i) => (
                    <div
                        key={section.id}
                        role="tabpanel"
                        id={`${baseId}-panel-${i}`}
                        aria-labelledby={`${baseId}-tab-${i}`}
                        hidden={i !== active}
                        className="text-muted-foreground"
                    >
                        {section.content}
                    </div>
                ))}
            </section>
        );
    }

    if (mode === 'accordion') {
        return (
            <div
                className={cn('store-description divide-y border-y', className)}
            >
                {sections.map((section, i) => (
                    <details
                        key={section.id}
                        open={i === 0}
                        className="group py-3"
                    >
                        <summary className="flex cursor-pointer list-none items-center justify-between font-semibold [&::-webkit-details-marker]:hidden">
                            {section.title}
                            <ChevronDown
                                className="size-4 transition-transform group-open:rotate-180 motion-reduce:transition-none"
                                aria-hidden
                            />
                        </summary>
                        <div className="pt-2 text-muted-foreground">
                            {section.content}
                        </div>
                    </details>
                ))}
            </div>
        );
    }

    return (
        <div className={cn('store-description grid gap-5', className)}>
            {sections.map((section) => (
                <section key={section.id} className="grid gap-2 border-t pt-5">
                    <h2 className="font-semibold">{section.title}</h2>
                    <div className="text-muted-foreground">
                        {section.content}
                    </div>
                </section>
            ))}
        </div>
    );
}
