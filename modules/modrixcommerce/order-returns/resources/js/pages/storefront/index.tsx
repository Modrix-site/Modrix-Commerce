import { Head, Link } from '@inertiajs/react';
import { MessageSquare, RotateCcw } from 'lucide-react';
import { StatusBadge } from '@/components/admin/status-badge';
import { AccountEmpty, AccountLayout } from '@/components/storefront/account-nav';
import { formatDate } from '@/components/storefront/order-view';
import { useT } from '@/lib/i18n';

type ReturnRow = {
    id: number;
    status: string;
    order_number: string | null;
    order_url: string | null;
    customer_comment: string;
    staff_comment: string | null;
    created_at: string | null;
    items: { name: string; quantity: number }[];
};

export default function CustomerReturns({ returns }: { returns: ReturnRow[] }) {
    const t = useT();

    return (
        <>
            <Head title={t('Returns')} />
            <AccountLayout
                title={t('Returns')}
                description={t('The store reviews each request and replies with a comment.')}
            >
                {returns.length === 0 ? (
                    <AccountEmpty icon={RotateCcw} title={t('You have not requested a return yet.')} />
                ) : (
                    <ul className="grid gap-3">
                        {returns.map((row) => (
                            <li key={row.id} className="grid gap-4 rounded-xl border bg-card p-5">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <span className="grid">
                                        {row.order_url && row.order_number ? (
                                            <Link href={row.order_url} className="font-medium hover:underline">
                                                {t('Order :number', { number: row.order_number })}
                                            </Link>
                                        ) : (
                                            <span className="font-medium">{t('Returns')}</span>
                                        )}
                                        {row.created_at && (
                                            <span className="text-sm text-muted-foreground">{formatDate(row.created_at)}</span>
                                        )}
                                    </span>
                                    <StatusBadge status={row.status} />
                                </div>
                                <ul className="flex flex-wrap gap-2 text-sm">
                                    {row.items.map((item) => (
                                        <li key={`${row.id}-${item.name}`} className="rounded-full bg-muted px-3 py-1">
                                            {item.name} × {item.quantity}
                                        </li>
                                    ))}
                                </ul>
                                <p className="text-sm">{row.customer_comment}</p>
                                {row.staff_comment && (
                                    <div className="flex gap-2 rounded-lg bg-muted p-3 text-sm">
                                        <MessageSquare className="mt-0.5 size-4 shrink-0 text-muted-foreground" aria-hidden />
                                        <p>
                                            <span className="font-medium">{t('Store')}: </span>
                                            {row.staff_comment}
                                        </p>
                                    </div>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </AccountLayout>
        </>
    );
}
