import { useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import ExtensionBlocksController from '@/actions/App/Http/Controllers/Admin/ExtensionBlocksController';
import { NativeSelect } from '@/components/admin/form-field';
import { CUSTOMIZER_TABS } from '@/components/admin/themes/customizer-tabs';
import type { CustomizerTab } from '@/components/admin/themes/customizer-tabs';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';

export type ExtensionBlock = {
    id: string;
    module: string;
    label: string;
    places: { key: string; label: string; tab: string }[];
    default: string;
    place: string;
    order: number;
    enabled: boolean;
};

type Placement = { place: string; order: number; enabled: boolean };

const tabLabel = (tab: string) =>
    CUSTOMIZER_TABS.find((t) => t.key === tab)?.label ?? tab;

/**
 * "Extension blocks" on a customizer tab: the blocks that can show on this
 * tab's page, with on/off, place and order. Placement is the same for every
 * theme.
 */
export function ExtensionBlocksCard({
    blocks,
    tab,
}: {
    blocks: ExtensionBlock[];
    tab: CustomizerTab;
}) {
    const here = useMemo(
        () => blocks.filter((b) => b.places.some((p) => p.tab === tab)),
        [blocks, tab],
    );
    const form = useForm<{ blocks: Record<string, Placement> }>({
        blocks: Object.fromEntries(
            blocks.map((b) => [
                b.id,
                { place: b.place, order: b.order, enabled: b.enabled },
            ]),
        ),
    });
    const [sequence, setSequence] = useState<string[]>(() =>
        [...here].sort((a, b) => a.order - b.order).map((block) => block.id),
    );
    // Moves are announced, and focus stays on the moved block's arrow (or
    // its other arrow once it reaches the end).
    const [announcement, setAnnouncement] = useState('');
    const [focusAfterMove, setFocusAfterMove] = useState<{
        id: string;
        step: -1 | 1;
    } | null>(null);
    const arrows = useRef(new Map<string, HTMLButtonElement | null>());

    useEffect(() => {
        if (!focusAfterMove) {
            return;
        }

        const { id, step } = focusAfterMove;
        const same = arrows.current.get(`${id}:${step}`);
        const other = arrows.current.get(`${id}:${-step}`);
        (same && !same.disabled ? same : other)?.focus();
    }, [focusAfterMove, sequence]);

    if (here.length === 0) {
        return null;
    }

    const set = (id: string, change: Partial<Placement>) =>
        form.setData('blocks', {
            ...form.data.blocks,
            [id]: { ...form.data.blocks[id], ...change },
        });

    const move = (index: number, step: -1 | 1) => {
        const target = index + step;

        if (target < 0 || target >= sequence.length) {
            return;
        }

        const next = [...sequence];
        [next[index], next[target]] = [next[target], next[index]];
        const label = here.find((b) => b.id === next[target])?.label ?? '';
        setSequence(next);
        setFocusAfterMove({ id: next[target], step });
        setAnnouncement(
            `${label} moved to position ${target + 1} of ${next.length}.`,
        );
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        // The order on this tab follows the list; other blocks keep theirs.
        // Only this tab's blocks are sent. Order is renumbered only for blocks
        // now placed on this tab's page, so other pages keep their order.
        const onThisTab = (id: string) =>
            here
                .find((b) => b.id === id)
                ?.places.find((p) => p.key === form.data.blocks[id].place)
                ?.tab === tab;
        let position = 0;
        form.transform((data) => ({
            blocks: Object.fromEntries(
                sequence.map((id) => [
                    id,
                    onThisTab(id)
                        ? { ...data.blocks[id], order: ++position * 10 }
                        : data.blocks[id],
                ]),
            ),
        }));
        form.put(ExtensionBlocksController.update.url(), {
            preserveScroll: true,
        });
    };

    const errors = Object.values(form.errors);

    return (
        <Card>
            <CardHeader>
                <CardTitle>Extension blocks</CardTitle>
                <CardDescription>
                    What your extensions show on this page, where, and in which
                    order. The same for every theme.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="grid gap-4">
                    <ul className="grid divide-y rounded-md border">
                        {sequence.map((id, index) => {
                            const block = here.find((b) => b.id === id);

                            if (!block) {
                                return null;
                            }

                            const value = form.data.blocks[id];
                            const checkboxId = `block-${id}`;

                            return (
                                <li
                                    key={id}
                                    className="grid gap-3 p-3 sm:grid-cols-[1fr_14rem_auto] sm:items-center"
                                >
                                    <div className="flex items-start gap-3">
                                        <Checkbox
                                            id={checkboxId}
                                            aria-label={`Show ${block.label}`}
                                            checked={value.enabled}
                                            onCheckedChange={(checked) =>
                                                set(id, {
                                                    enabled: checked === true,
                                                })
                                            }
                                            className="mt-0.5"
                                        />
                                        <label
                                            htmlFor={checkboxId}
                                            className="grid"
                                        >
                                            <span className="font-medium">
                                                {block.label}
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                {block.module}
                                            </span>
                                        </label>
                                    </div>
                                    <NativeSelect
                                        aria-label={`Where ${block.label} shows`}
                                        value={value.place}
                                        disabled={block.places.length < 2}
                                        onChange={(e) =>
                                            set(id, { place: e.target.value })
                                        }
                                    >
                                        {block.places.map((place) => (
                                            <option
                                                key={place.key}
                                                value={place.key}
                                            >
                                                {place.label}
                                                {place.tab !== tab
                                                    ? ` (${tabLabel(place.tab)})`
                                                    : ''}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                    <div className="flex gap-1">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            aria-label={`Move ${block.label} up`}
                                            ref={(el) => {
                                                arrows.current.set(
                                                    `${id}:-1`,
                                                    el,
                                                );
                                            }}
                                            disabled={index === 0}
                                            onClick={() => move(index, -1)}
                                        >
                                            <ArrowUp />
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            aria-label={`Move ${block.label} down`}
                                            ref={(el) => {
                                                arrows.current.set(
                                                    `${id}:1`,
                                                    el,
                                                );
                                            }}
                                            disabled={
                                                index === sequence.length - 1
                                            }
                                            onClick={() => move(index, 1)}
                                        >
                                            <ArrowDown />
                                        </Button>
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                    <p className="sr-only" aria-live="polite">
                        {announcement}
                    </p>
                    {errors.map((message, i) => (
                        <InputError key={i} message={message} />
                    ))}
                    <div>
                        <Button type="submit" disabled={form.processing}>
                            Save blocks
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}
