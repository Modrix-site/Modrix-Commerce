import { X } from 'lucide-react';
import { useId, useState } from 'react';
import type { KeyboardEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';

const normalise = (tag: string) =>
    tag.replace(/\s+/g, ' ').trim().toLowerCase();

/**
 * Free tags as chips. Enter or comma adds a tag; suggestions come from tags
 * already used in the catalog. Tags are stored lowercase.
 */
export function TagsInput({
    id,
    value,
    onChange,
    suggestions,
    max = 20,
}: {
    id: string;
    value: string[];
    onChange: (tags: string[]) => void;
    suggestions: string[];
    max?: number;
}) {
    const [draft, setDraft] = useState('');
    const listId = useId();

    const add = (raw: string) => {
        const tags = raw
            .split(',')
            .map(normalise)
            .filter((t) => t !== '' && t.length <= 60 && !value.includes(t));

        if (tags.length > 0) {
            onChange([...value, ...tags].slice(0, max));
        }
        setDraft('');
    };

    const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'Enter' || event.key === ',') {
            event.preventDefault();
            add(draft);
        } else if (event.key === 'Backspace' && draft === '' && value.length) {
            onChange(value.slice(0, -1));
        }
    };

    return (
        <div className="grid gap-2">
            {value.length > 0 && (
                <ul className="flex flex-wrap gap-1.5" aria-label="Tags">
                    {value.map((tag) => (
                        <li key={tag}>
                            <Badge variant="secondary" className="gap-1 pr-1">
                                {tag}
                                <button
                                    type="button"
                                    onClick={() =>
                                        onChange(value.filter((t) => t !== tag))
                                    }
                                    className="rounded-sm p-0.5 hover:bg-background/60"
                                    aria-label={`Remove tag ${tag}`}
                                >
                                    <X className="size-3" />
                                </button>
                            </Badge>
                        </li>
                    ))}
                </ul>
            )}
            <Input
                id={id}
                list={listId}
                value={draft}
                disabled={value.length >= max}
                placeholder={
                    value.length >= max
                        ? `Up to ${max} tags`
                        : 'Type a tag and press Enter'
                }
                onChange={(e) => setDraft(e.target.value)}
                onKeyDown={onKeyDown}
                onBlur={() => draft.trim() !== '' && add(draft)}
            />
            <datalist id={listId}>
                {suggestions
                    .filter((s) => !value.includes(s))
                    .map((s) => (
                        <option key={s} value={s} />
                    ))}
            </datalist>
        </div>
    );
}
