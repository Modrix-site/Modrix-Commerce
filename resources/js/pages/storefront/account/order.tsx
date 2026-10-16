import { Head, Link } from '@inertiajs/react';
import { Download, FileText, Receipt } from 'lucide-react';
import { DownloadList } from '@/components/storefront/download-list';
import { formatDate, OrderView } from '@/components/storefront/order-view';
import { useT } from '@/lib/i18n';
import { orders } from '@/routes/account';
import type { OrderDetail } from '@/types';

export default function AccountOrder({ order }: { order: OrderDetail }) {
    const t = useT();

    return (
        <>
            <Head title={t('Order :number', { number: order.number })} />
            <Link
                href={orders()}
                className="text-sm text-muted-foreground hover:text-foreground"
            >
                ← {t('All orders')}
            </Link>
            <div className="my-4 grid gap-1">
                <h1 className="text-2xl font-semibold tracking-tight">
                    {t('Order :number', { number: order.number })}
                </h1>
                <p className="text-sm text-muted-foreground">
                    {t('Placed :date', { date: formatDate(order.placed_at) })}
                </p>
            </div>
            <DownloadList downloads={order.downloads} />
            <OrderView order={order} />
            {order.documents &&
                (order.documents.invoice ||
                    order.documents.receipts.length > 0) && (
                    <section className="mt-8 grid gap-3">
                        <h2 className="text-lg font-semibold">
                            {t('Documents')}
                        </h2>
                        <ul className="grid divide-y rounded-lg border text-sm sm:max-w-md">
                            {order.documents.invoice && (
                                <li>
                                    <DocumentLink
                                        href={order.documents.invoice.url}
                                        icon={<FileText className="size-4" />}
                                        label={t('Invoice :number', {
                                            number: order.documents.invoice
                                                .number,
                                        })}
                                    />
                                </li>
                            )}
                            {order.documents.receipts.map((receipt) => (
                                <li key={receipt.id}>
                                    <DocumentLink
                                        href={receipt.url}
                                        icon={<Receipt className="size-4" />}
                                        label={t('Payment receipt (:amount)', {
                                            amount: receipt.amount.formatted,
                                        })}
                                        detail={
                                            receipt.paid_at
                                                ? formatDate(receipt.paid_at)
                                                : undefined
                                        }
                                    />
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
        </>
    );
}

function DocumentLink({
    href,
    icon,
    label,
    detail,
}: {
    href: string;
    icon: React.ReactNode;
    label: string;
    detail?: string;
}) {
    const t = useT();

    return (
        <a
            href={href}
            download
            className="flex items-center justify-between gap-3 p-3 hover:bg-muted"
        >
            <span className="flex items-center gap-3">
                <span className="text-muted-foreground" aria-hidden>
                    {icon}
                </span>
                <span>
                    {label}
                    {detail && (
                        <span className="block text-xs text-muted-foreground">
                            {detail}
                        </span>
                    )}
                </span>
            </span>
            <span className="flex items-center gap-1 text-xs text-muted-foreground">
                <Download className="size-4" aria-hidden />
                {t('PDF')}
            </span>
        </a>
    );
}
