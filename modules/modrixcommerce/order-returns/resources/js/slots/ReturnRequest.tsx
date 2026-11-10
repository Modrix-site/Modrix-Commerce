import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useT } from '@/lib/i18n';

type Item = {
    id: number;
    name: string;
    quantity: number;
    returnable: number;
};

type RequestRow = {
    id: number;
    status: string;
    customer_comment: string;
    staff_comment: string | null;
    created_at: string | null;
    items: { name: string; quantity: number }[];
};

type Props = {
    canRequest: boolean;
    reason: string | null;
    windowDays: number;
    commentMin: number;
    submitUrl: string;
    items: Item[];
    requests: RequestRow[];
};

export default function ReturnRequest({
    canRequest,
    reason,
    windowDays,
    commentMin,
    submitUrl,
    items,
    requests,
}: Props) {
    const t = useT();

    return (
        <section className="grid gap-4">
            <div className="grid gap-1">
                <h2 className="text-lg font-semibold">{t('Returns')}</h2>
                <p className="text-sm text-muted-foreground">
                    {t(
                        'You can request a return for :days days. The store reviews the request and writes back with a comment.',
                        { days: windowDays },
                    )}
                </p>
            </div>

            {requests.length > 0 && (
                <ul className="grid gap-3">
                    {requests.map((request) => (
                        <li
                            key={request.id}
                            className="grid gap-2 rounded-lg border p-3 text-sm"
                        >
                            <span className="font-medium capitalize">
                                {t(request.status)}
                            </span>
                            <p>{request.customer_comment}</p>
                            <ul className="text-muted-foreground">
                                {request.items.map((item) => (
                                    <li key={`${request.id}-${item.name}`}>
                                        {item.name} × {item.quantity}
                                    </li>
                                ))}
                            </ul>
                            {request.staff_comment && (
                                <p>
                                    <span className="font-medium">
                                        {t('Store')}:{' '}
                                    </span>
                                    {request.staff_comment}
                                </p>
                            )}
                        </li>
                    ))}
                </ul>
            )}

            {canRequest ? (
                <Form
                    action={submitUrl}
                    method="post"
                    className="grid gap-4 rounded-lg border p-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <fieldset className="grid gap-3">
                                <legend className="text-sm font-medium">
                                    {t('Items')}
                                </legend>
                                {items
                                    .filter((item) => item.returnable > 0)
                                    .map((item, index) => (
                                        <label
                                            key={item.id}
                                            className="flex items-center justify-between gap-3 text-sm"
                                        >
                                            <span>
                                                {item.name}
                                                <span className="block text-xs text-muted-foreground">
                                                    {t(':count can be returned', {
                                                        count: item.returnable,
                                                    })}
                                                </span>
                                            </span>
                                            <input
                                                type="hidden"
                                                name={`items[${index}][order_item_id]`}
                                                value={item.id}
                                            />
                                            <input
                                                type="number"
                                                name={`items[${index}][quantity]`}
                                                min={0}
                                                max={item.returnable}
                                                defaultValue={item.returnable}
                                                className="h-9 w-20 rounded-md border bg-transparent px-2"
                                                aria-label={t(
                                                    'Quantity to return for :name',
                                                    { name: item.name },
                                                )}
                                            />
                                        </label>
                                    ))}
                                <InputError message={errors.items} />
                            </fieldset>
                            <div className="grid gap-2">
                                <Label htmlFor="return-comment">
                                    {t('Why are you returning these items?')}
                                </Label>
                                <Textarea
                                    id="return-comment"
                                    name="comment"
                                    required
                                    minLength={commentMin}
                                    rows={4}
                                />
                                <InputError message={errors.comment} />
                                <InputError message={errors.order} />
                            </div>
                            <div>
                                <Button type="submit" disabled={processing}>
                                    {t('Request return')}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            ) : (
                reason && (
                    <p className="text-sm text-muted-foreground">{t(reason)}</p>
                )
            )}
        </section>
    );
}
