import { router } from '@inertiajs/react';
import { Zap } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';

type Membership = {
    discount_type: 'percent' | 'fixed' | null;
    discount_value: string | null;
};

type SaleOption = {
    id: number;
    name: string;
    state: 'scheduled' | 'live' | 'ended' | 'off';
    discount: string;
    starts_at: string;
    ends_at: string;
    /** This product's place in the sale, or null when it is not in it. */
    membership: Membership | null;
};

type Props = {
    productId: number;
    updateUrl: string;
    salesUrl: string;
    sales: SaleOption[];
};

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm dark:bg-input/30';

const stateLabel = (s: SaleOption) =>
    s.state === 'live' ? 'live' : s.state === 'scheduled' ? 'upcoming' : 'off';

/**
 * Product editor sidebar: include this product in upcoming or live flash
 * sales, one sale at a time, optionally with its own discount. Saves on its
 * own (not with the product form) and never changes the product's other sales.
 */
export default function ProductFlashSalePanel({
    updateUrl,
    salesUrl,
    sales,
}: Props) {
    const first = sales.find((s) => s.membership) ?? sales[0];
    const [saleId, setSaleId] = useState<number | ''>(first?.id ?? '');
    const selected = sales.find((s) => s.id === saleId);
    const membership = selected?.membership ?? null;

    const [on, setOn] = useState(membership !== null);
    const [own, setOwn] = useState(membership?.discount_type != null);
    const [type, setType] = useState<'percent' | 'fixed'>(
        membership?.discount_type ?? 'percent',
    );
    const [value, setValue] = useState(
        membership?.discount_value
            ? String(Number(membership.discount_value))
            : '',
    );
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);

    // Show the chosen sale's saved settings.
    const choose = (id: number) => {
        const m = sales.find((s) => s.id === id)?.membership ?? null;
        setSaleId(id);
        setOn(m !== null);
        setOwn(m?.discount_type != null);
        setType(m?.discount_type ?? 'percent');
        setValue(m?.discount_value ? String(Number(m.discount_value)) : '');
        setErrors({});
    };

    const save = (include: boolean) => {
        if (saleId === '') {
            return;
        }

        setSaving(true);
        router.put(
            updateUrl,
            {
                sale_id: saleId,
                include,
                discount_type: include && own ? type : null,
                discount_value: include && own ? value : null,
            },
            {
                preserveScroll: true,
                // Keep any unsaved edits in the product form around it.
                preserveState: true,
                onError: (e) => setErrors(e),
                onSuccess: () => setErrors({}),
                onFinish: () => setSaving(false),
            },
        );
    };

    const liveNow = sales.some((s) => s.membership && s.state === 'live');

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Zap className="size-4 text-orange-500" aria-hidden />
                    Flash sale
                    {liveNow && <Badge>Live</Badge>}
                </CardTitle>
            </CardHeader>
            <CardContent className="grid gap-3 text-sm">
                {sales.length === 0 ? (
                    <p className="text-muted-foreground">
                        No upcoming flash sales.{' '}
                        <a href={salesUrl} className="underline">
                            Create one
                        </a>{' '}
                        first.
                    </p>
                ) : (
                    <>
                        <select
                            aria-label="Flash sale"
                            className={selectClass}
                            value={saleId}
                            onChange={(e) => choose(Number(e.target.value))}
                        >
                            {sales.map((s) => (
                                <option key={s.id} value={s.id}>
                                    {s.membership ? '✓ ' : ''}
                                    {s.name} · {s.discount} · {stateLabel(s)}
                                </option>
                            ))}
                        </select>
                        <label className="flex items-center gap-2 font-medium">
                            <Checkbox
                                checked={on}
                                onCheckedChange={(c) => {
                                    const checked = c === true;
                                    setOn(checked);
                                    // Turning it off takes the product out of this sale straight away.
                                    if (!checked && membership) {
                                        save(false);
                                    }
                                }}
                            />
                            Include in this sale
                        </label>
                        {on && (
                            <>
                                <label className="flex items-center gap-2">
                                    <Checkbox
                                        checked={own}
                                        onCheckedChange={(c) =>
                                            setOwn(c === true)
                                        }
                                    />
                                    Own discount for this product
                                </label>
                                {own && (
                                    <div className="flex gap-2">
                                        <select
                                            aria-label="Discount type"
                                            className={`${selectClass} w-24`}
                                            value={type}
                                            onChange={(e) =>
                                                setType(
                                                    e.target.value as
                                                        | 'percent'
                                                        | 'fixed',
                                                )
                                            }
                                        >
                                            <option value="percent">%</option>
                                            <option value="fixed">
                                                Amount
                                            </option>
                                        </select>
                                        <Input
                                            aria-label="Discount value"
                                            inputMode="decimal"
                                            value={value}
                                            onChange={(e) =>
                                                setValue(e.target.value)
                                            }
                                            placeholder={
                                                type === 'percent'
                                                    ? '20'
                                                    : '5.00'
                                            }
                                        />
                                    </div>
                                )}
                                <InputError
                                    message={
                                        errors.discount_value ??
                                        errors.discount_type ??
                                        errors.sale_id
                                    }
                                />
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    disabled={saving || saleId === ''}
                                    onClick={() => save(true)}
                                >
                                    {membership
                                        ? 'Update flash sale'
                                        : 'Add to flash sale'}
                                </Button>
                            </>
                        )}
                        <a
                            href={salesUrl}
                            className="text-xs text-muted-foreground underline"
                        >
                            Manage flash sales
                        </a>
                    </>
                )}
            </CardContent>
        </Card>
    );
}
