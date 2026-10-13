import type { MoneyValue, OrderDetail } from '@/types';

export type OrderStep = {
    key: 'placed' | 'paid' | 'packed' | 'shipped' | 'delivered';
    label: string;
    done: boolean;
    at: string | null;
};

export type TimelineEntry = {
    id: number;
    kind: string;
    message: string;
    customer_visible: boolean;
    actor: string | null;
    at: string;
};

export type ReceiptLink = {
    id: number;
    label: string;
    amount: MoneyValue;
    paid_at: string | null;
    url: string;
};

export type AdminOrder = Omit<OrderDetail, 'documents'> & {
    id: number;
    phone: string | null;
    ip_address: string | null;
    customer: { id: number; name: string; registered: boolean } | null;
    paid_total: MoneyValue;
    refunded_total: MoneyValue;
    refundable: MoneyValue;
    payments: {
        id: number;
        method: string;
        provider: string;
        reference: string | null;
        amount: MoneyValue;
        status: string;
        failure_reason: string | null;
        paid_at: string | null;
        created_at: string | null;
    }[];
    refunds: {
        id: number;
        amount: MoneyValue;
        reason: string;
        status: string;
        reference: string | null;
        failure_reason: string | null;
        created_at: string | null;
    }[];
    timeline: TimelineEntry[];
    steps: OrderStep[];
    documents: {
        invoice: { number: string; issued_at: string; url: string } | null;
        receipts: ReceiptLink[];
        packing_slip_url: string;
    };
};
