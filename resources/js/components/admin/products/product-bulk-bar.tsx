import { router } from '@inertiajs/react';
import { Download, X } from 'lucide-react';
import { useState } from 'react';
import { NativeSelect } from '@/components/admin/form-field';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { bulk } from '@/routes/admin/products';
import type { CategoryOption } from '@/types';

type Action =
    | 'publish'
    | 'draft'
    | 'archive'
    | 'add_category'
    | 'remove_category'
    | 'set_brand'
    | 'add_tags'
    | 'remove_tags'
    | 'feature'
    | 'unfeature'
    | 'adjust_prices'
    | 'set_stock'
    | 'delete'
    | 'export';

const GROUPS: { label: string; actions: [Action, string][] }[] = [
    {
        label: 'Status',
        actions: [
            ['publish', 'Publish'],
            ['draft', 'Set to draft'],
            ['archive', 'Archive'],
        ],
    },
    {
        label: 'Organise',
        actions: [
            ['add_category', 'Add to category'],
            ['remove_category', 'Remove from category'],
            ['set_brand', 'Set brand'],
            ['add_tags', 'Add tags'],
            ['remove_tags', 'Remove tags'],
            ['feature', 'Mark as featured'],
            ['unfeature', 'Remove featured'],
        ],
    },
    {
        label: 'Prices & stock',
        actions: [
            ['adjust_prices', 'Change prices'],
            ['set_stock', 'Set stock quantity'],
        ],
    },
    {
        label: 'Delete & export',
        actions: [
            ['export', 'Export CSV'],
            ['delete', 'Delete'],
        ],
    },
];

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

/**
 * Product list: actions on the selected products. Products that cannot be
 * changed are skipped and listed in the result message.
 */
export function ProductBulkBar({
    selected,
    onClear,
    categories,
    brands,
    canUpdate,
    canDelete,
}: {
    selected: number[];
    onClear: () => void;
    categories: CategoryOption[];
    brands: { id: number; name: string }[];
    canUpdate: boolean;
    canDelete: boolean;
}) {
    const [action, setAction] = useState<Action | ''>('');
    const [fields, setFields] = useState<Record<string, string>>({});
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);

    if (selected.length === 0) {
        return null;
    }

    const set = (key: string, value: string) =>
        setFields((f) => ({ ...f, [key]: value }));

    const allowed = (a: Action) =>
        a === 'export' ? true : a === 'delete' ? canDelete : canUpdate;

    const payload = (): Record<string, unknown> => {
        const data: Record<string, unknown> = { action, ids: selected };

        if (action === 'add_category' || action === 'remove_category') {
            data.category_id = fields.category_id ?? '';
        } else if (action === 'set_brand') {
            data.brand_id = fields.brand_id || null;
        } else if (action === 'add_tags' || action === 'remove_tags') {
            data.tags = (fields.tags ?? '')
                .split(',')
                .map((t) => t.trim())
                .filter(Boolean);
        } else if (action === 'adjust_prices') {
            data.direction = fields.direction ?? 'increase';
            data.mode = fields.mode ?? 'percent';
            data.value = fields.value ?? '';
        } else if (action === 'set_stock') {
            data.quantity = fields.quantity ?? '';
        }

        return data;
    };

    const exportCsv = async () => {
        setBusy(true);

        try {
            const response = await fetch(bulk.url(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'text/csv',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                body: JSON.stringify(payload()),
            });

            if (!response.ok) {
                setErrors({ action: 'The export failed. Please try again.' });

                return;
            }

            const url = URL.createObjectURL(await response.blob());
            const link = document.createElement('a');
            link.href = url;
            link.download = `products-${selected.length}.csv`;
            link.click();
            URL.revokeObjectURL(url);
        } finally {
            setBusy(false);
        }
    };

    const apply = () => {
        if (action === '') {
            return;
        }

        if (action === 'export') {
            void exportCsv();

            return;
        }

        if (
            action === 'delete' &&
            !window.confirm(
                `Delete ${selected.length} product(s)? They are removed from the store; past orders keep their details.`,
            )
        ) {
            return;
        }

        router.post(bulk.url(), payload() as never, {
            preserveScroll: true,
            onStart: () => setBusy(true),
            onFinish: () => setBusy(false),
            onError: (e) => setErrors(e),
            onSuccess: () => {
                setErrors({});
                setAction('');
                setFields({});
                onClear();
            },
        });
    };

    const error = Object.values(errors)[0];

    return (
        <div className="sticky bottom-4 z-20 mt-4 rounded-lg border bg-background/95 p-3 shadow-lg backdrop-blur">
            <div className="flex flex-wrap items-end gap-3">
                <span className="self-center text-sm font-medium">
                    {selected.length} selected
                </span>
                <NativeSelect
                    aria-label="Bulk action"
                    value={action}
                    onChange={(e) => {
                        setAction(e.target.value as Action | '');
                        setErrors({});
                    }}
                    className="w-56"
                >
                    <option value="">Choose an action…</option>
                    {GROUPS.map((group) => (
                        <optgroup key={group.label} label={group.label}>
                            {group.actions
                                .filter(([a]) => allowed(a))
                                .map(([a, label]) => (
                                    <option key={a} value={a}>
                                        {label}
                                    </option>
                                ))}
                        </optgroup>
                    ))}
                </NativeSelect>

                {(action === 'add_category' ||
                    action === 'remove_category') && (
                    <NativeSelect
                        aria-label="Category"
                        value={fields.category_id ?? ''}
                        onChange={(e) => set('category_id', e.target.value)}
                        className="w-56"
                    >
                        <option value="">Choose a category…</option>
                        {categories.map((c) => (
                            <option key={c.id} value={c.id}>
                                {'— '.repeat(c.depth)}
                                {c.name}
                            </option>
                        ))}
                    </NativeSelect>
                )}

                {action === 'set_brand' && (
                    <NativeSelect
                        aria-label="Brand"
                        value={fields.brand_id ?? ''}
                        onChange={(e) => set('brand_id', e.target.value)}
                        className="w-48"
                    >
                        <option value="">No brand</option>
                        {brands.map((b) => (
                            <option key={b.id} value={b.id}>
                                {b.name}
                            </option>
                        ))}
                    </NativeSelect>
                )}

                {(action === 'add_tags' || action === 'remove_tags') && (
                    <Input
                        aria-label="Tags"
                        placeholder="summer, gift ideas"
                        value={fields.tags ?? ''}
                        onChange={(e) => set('tags', e.target.value)}
                        className="w-56"
                    />
                )}

                {action === 'adjust_prices' && (
                    <>
                        <NativeSelect
                            aria-label="Direction"
                            value={fields.direction ?? 'increase'}
                            onChange={(e) => set('direction', e.target.value)}
                            className="w-32"
                        >
                            <option value="increase">Increase</option>
                            <option value="decrease">Decrease</option>
                        </NativeSelect>
                        <Input
                            aria-label="Amount"
                            inputMode="decimal"
                            placeholder={
                                (fields.mode ?? 'percent') === 'percent'
                                    ? '10'
                                    : '5.00'
                            }
                            value={fields.value ?? ''}
                            onChange={(e) => set('value', e.target.value)}
                            className="w-24"
                        />
                        <NativeSelect
                            aria-label="Percent or fixed amount"
                            value={fields.mode ?? 'percent'}
                            onChange={(e) => set('mode', e.target.value)}
                            className="w-36"
                        >
                            <option value="percent">%</option>
                            <option value="fixed">Fixed amount</option>
                        </NativeSelect>
                    </>
                )}

                {action === 'set_stock' && (
                    <Input
                        aria-label="Quantity on hand"
                        type="number"
                        min={0}
                        placeholder="Quantity"
                        value={fields.quantity ?? ''}
                        onChange={(e) => set('quantity', e.target.value)}
                        className="w-28"
                    />
                )}

                <Button
                    type="button"
                    onClick={apply}
                    disabled={busy || action === ''}
                    variant={action === 'delete' ? 'destructive' : 'default'}
                >
                    {action === 'export' && <Download className="size-4" />}
                    Apply
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    onClick={onClear}
                    aria-label="Clear selection"
                >
                    <X className="size-4" />
                </Button>
            </div>
            <InputError message={error} className="mt-2" />
        </div>
    );
}
