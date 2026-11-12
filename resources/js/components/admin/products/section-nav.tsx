import { cn } from '@/lib/utils';

export type Section = { id: string; label: string; hasError: boolean };

/**
 * Sticky list of the editor's sections. A dot marks sections with errors.
 */
export function SectionNav({ sections }: { sections: Section[] }) {
    return (
        <nav
            aria-label="Product sections"
            className="sticky top-0 z-10 -mx-4 flex gap-1 overflow-x-auto border-b bg-background/95 px-4 py-2 backdrop-blur md:-mx-6 md:px-6"
        >
            {sections.map((section) => (
                <a
                    key={section.id}
                    href={`#${section.id}`}
                    className={cn(
                        'flex shrink-0 items-center gap-1.5 rounded-md px-3 py-1.5 text-sm text-muted-foreground hover:bg-muted hover:text-foreground',
                        section.hasError && 'text-destructive',
                    )}
                >
                    {section.label}
                    {section.hasError && (
                        <span
                            className="size-1.5 rounded-full bg-destructive"
                            aria-label="has errors"
                        />
                    )}
                </a>
            ))}
        </nav>
    );
}
