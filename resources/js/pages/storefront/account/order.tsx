import { Head } from '@inertiajs/react';
import { Download, FileText, Receipt } from 'lucide-react';
import {
    ExtensionSlot,
    type SlotItem,
} from '@/components/extensions/extension-slot';
import { AccountLayout } from '@/components/storefront/account-nav';
import { DownloadList } from '@/components/storefront/download-list';
import { formatDate, OrderView } from '@/components/storefront/order-view';
import { useT } from '@/lib/i18n';
import { orders } from '@/routes/account';
import type { OrderDetail } from '@/types';

export default function AccountOrder({
    order,
    returnSlots = [],
}: {
    order: OrderDetail;
    returnSlots?: SlotItem[];
}) {
    const t = useT();

    return (
        <>
            <Head title={t('Order :number', { number: order.number })} />
            <AccountLayout
                back={{ href: orders(), label: t('All orders') }}
                title={t('Order :number', { number: order.number })}
                description={t('Placed :date', {
                    date: formatDate(order.placed_at),
                })}
            >
            <div className="grid gap-8">
            <DownloadList downloads={order.downloads} />
            <OrderView order={order} />
            <ExtensionSlot items={returnSlots} />
            {order.documents &&
                (order.documents.invoice ||
                    order.documents.receipts.length > 0) && (
                    <section className="grid gap-3">
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
            </div>
            </AccountLayout>
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
