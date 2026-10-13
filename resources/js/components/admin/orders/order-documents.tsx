import { Form } from '@inertiajs/react';
import { Download, FileText, Package, Receipt } from 'lucide-react';
import type { ReactNode } from 'react';
import OrderController from '@/actions/App/Http/Controllers/Admin/OrderController';
import { formatDate } from '@/components/storefront/order-view';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { AdminOrder } from './types';

/**
 * Invoice, payment receipts and packing slip as PDF downloads.
 */
export function OrderDocuments({
    order,
    canIssueInvoice,
}: {
    order: AdminOrder;
    canIssueInvoice: boolean;
}) {
    const { invoice, receipts, packing_slip_url } = order.documents;

    return (
        <Card>
            <CardHeader>
                <CardTitle>Documents</CardTitle>
            </CardHeader>
            <CardContent className="grid gap-1 text-sm">
                {invoice ? (
                    <DocumentLink
                        href={invoice.url}
                        icon={<FileText className="size-4" aria-hidden />}
                        title={`Invoice ${invoice.number}`}
                        detail={`Issued ${formatDate(invoice.issued_at)}`}
                    />
                ) : (
                    <div className="flex items-center justify-between gap-3 py-2">
                        <span className="flex items-center gap-3 text-muted-foreground">
                            <FileText className="size-4" aria-hidden />
                            <span>
                                No invoice yet
                                <span className="block text-xs">
                                    Issued automatically on payment.
                                </span>
                            </span>
                        </span>
                        {canIssueInvoice && (
                            <Form
                                {...OrderController.issueInvoice.form(
                                    order.number,
                                )}
                                options={{ preserveScroll: true }}
                            >
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        size="sm"
                                        variant="outline"
                                        disabled={processing}
                                    >
                                        Issue now
                                    </Button>
                                )}
                            </Form>
                        )}
                    </div>
                )}
                {receipts.map((receipt) => (
                    <DocumentLink
                        key={receipt.id}
                        href={receipt.url}
                        icon={<Receipt className="size-4" aria-hidden />}
                        title={`Receipt · ${receipt.amount.formatted}`}
                        detail={
                            receipt.paid_at
                                ? `Paid ${formatDate(receipt.paid_at)}`
                                : undefined
                        }
                    />
                ))}
                <DocumentLink
                    href={packing_slip_url}
                    icon={<Package className="size-4" aria-hidden />}
                    title="Packing slip"
                    detail="Items and shipping address, no prices"
                />
            </CardContent>
        </Card>
    );
}

function DocumentLink({
    href,
    icon,
    title,
    detail,
}: {
    href: string;
    icon: ReactNode;
    title: string;
    detail?: string;
}) {
    return (
        <a
            href={href}
            className="group -mx-2 flex items-center justify-between gap-3 rounded-md px-2 py-2 hover:bg-muted"
            download
        >
            <span className="flex items-center gap-3">
                <span className="text-muted-foreground">{icon}</span>
                <span>
                    <span className="font-medium">{title}</span>
                    {detail && (
                        <span className="block text-xs text-muted-foreground">
                            {detail}
                        </span>
                    )}
                </span>
            </span>
            <Download
                className="size-4 text-muted-foreground group-hover:text-foreground"
                aria-label="Download PDF"
            />
        </a>
    );
}
