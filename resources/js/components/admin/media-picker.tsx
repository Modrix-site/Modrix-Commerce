import { router } from '@inertiajs/react';
import { Check, Images } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { picker } from '@/routes/admin/media';

type PickerItem = {
    id: number;
    url: string;
    filename: string;
    alt: string | null;
};

/**
 * Chooses images from the media library and posts their ids to `action`.
 */
export function MediaPicker({
    action,
    label = 'Add from library',
}: {
    action: string;
    label?: string;
}) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const [items, setItems] = useState<PickerItem[]>([]);
    const [loading, setLoading] = useState(false);
    const [selected, setSelected] = useState<number[]>([]);

    useEffect(() => {
        if (!open) {
            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            setLoading(true);
            fetch(picker.url({ query: search ? { search } : {} }), {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            })
                .then((r) => r.json() as Promise<{ media: PickerItem[] }>)
                .then((data) => setItems(data.media))
                .catch(() => undefined)
                .finally(() => setLoading(false));
        }, 250);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [open, search]);

    const toggle = (id: number) =>
        setSelected((current) =>
            current.includes(id)
                ? current.filter((i) => i !== id)
                : [...current, id],
        );

    const submit = () => {
        router.post(
            action,
            { media: selected },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSelected([]);
                    setOpen(false);
                },
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button type="button" variant="outline">
                    <Images /> {label}
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-3xl">
                <DialogTitle>Media library</DialogTitle>
                <DialogDescription>
                    Choose images. They are shared with the library, not copied.
                </DialogDescription>
                <Input
                    type="search"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Search by file name or description"
                    aria-label="Search the media library"
                />
                <div className="grid max-h-[50vh] grid-cols-3 gap-2 overflow-y-auto sm:grid-cols-5">
                    {items.map((item) => {
                        const isSelected = selected.includes(item.id);

                        return (
                            <button
                                key={item.id}
                                type="button"
                                onClick={() => toggle(item.id)}
                                aria-pressed={isSelected}
                                aria-label={item.alt ?? item.filename}
                                className={cn(
                                    'relative overflow-hidden rounded-md border-2 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                                    isSelected
                                        ? 'border-primary'
                                        : 'border-transparent',
                                )}
                            >
                                <img
                                    src={item.url}
                                    alt=""
                                    className="aspect-square w-full object-cover"
                                />
                                {isSelected && (
                                    <span className="absolute top-1 right-1 rounded-full bg-primary p-0.5 text-primary-foreground">
                                        <Check className="size-3" />
                                    </span>
                                )}
                            </button>
                        );
                    })}
                    {!loading && items.length === 0 && (
                        <p className="col-span-full text-sm text-muted-foreground">
                            No images found. Upload them under Catalog → Media
                            library.
                        </p>
                    )}
                </div>
                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary" type="button">
                            Cancel
                        </Button>
                    </DialogClose>
                    <Button
                        type="button"
                        onClick={submit}
                        disabled={selected.length === 0}
                    >
                        Add {selected.length > 0 ? selected.length : ''}{' '}
                        {selected.length === 1 ? 'image' : 'images'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
