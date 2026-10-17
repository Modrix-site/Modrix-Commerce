import { useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp } from 'lucide-react';
import { useMemo, useState } from 'react';
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

    if (here.length === 0) {
        return null;
    }

    const set = (id: string, change: Partial<Placement>) =>
        form.setData('blocks', {
            ...form.data.blocks,
            [id]: { ...form.data.blocks[id], ...change },
        });

    const move = (index: number, step: -1 | 1) =>
        setSequence((ids) => {
            const next = [...ids];
            const target = index + step;

            if (target < 0 || target >= next.length) {
                return ids;
            }

            [next[index], next[target]] = [next[target], next[index]];

            return next;
        });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        // The order on this tab follows the list; other blocks keep theirs.
        form.transform((data) => ({
            blocks: {
                ...data.blocks,
                ...Object.fromEntries(
                    sequence.map((id, i) => [
                        id,
                        { ...data.blocks[id], order: (i + 1) * 10 },
                    ]),
                ),
            },
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
                    {errors.map((message) => (
                        <InputError key={message} message={message} />
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
