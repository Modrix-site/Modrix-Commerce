import { useEffect, useRef } from 'react';
import { formatMoney } from '../lib/tamara';
import type { SnippetProps } from '../lib/tamara';

type Props = SnippetProps & {
    method: string;
    showCard: boolean;
    eligibilityUrl: string | null;
    /** Live values from the checkout page (slot "checkout.payment_method"). */
    checkout?: {
        selected: boolean;
        email: string;
        phone: string;
        reportAvailability: (reason: string | null) => void;
    };
};

const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/**
 * Under the Tamara payment method at checkout: the four-payment schedule while
 * Tamara is selected, and a background availability check once the shopper's
 * email and phone are known. A limit that rules the order out disables the
 * method; errors leave Tamara available (the order is checked again when it is
 * placed, and Tamara makes the final decision).
 */
export default function TamaraCheckout(props: Props) {
    const { checkout, eligibilityUrl, currency, lang, price, schedule } = props;
    const report = useRef(checkout?.reportAvailability);
    report.current = checkout?.reportAvailability;

    const email = checkout?.email.trim() ?? '';
    const phone = checkout?.phone.trim() ?? '';

    useEffect(() => {
        if (!eligibilityUrl || !EMAIL.test(email) || phone.length < 6) {
            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            checkEligibility(
                eligibilityUrl,
                { amount: price },
                controller.signal,
            )
                .then((result) =>
                    report.current?.(
                        result.eligible ? null : (result.message ?? null),
                    ),
                )
                .catch(() => undefined);
        }, 600);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [eligibilityUrl, email, phone, price]);

    if (!props.showCard || !checkout?.selected) {
        return null;
    }

    return (
        <div
            className="ecomodular-tamara-card grid gap-2 text-sm"
            dir={lang === 'ar' ? 'rtl' : undefined}
        >
            <ul className="grid gap-1">
                {schedule.map((part) => (
                    <li
                        key={part.label}
                        className="flex items-center justify-between gap-4"
                    >
                        <span className="text-muted-foreground">
                            {part.label}
                        </span>
                        <span className="tabular-nums">
                            {formatMoney(part.amount, currency, lang)}
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

async function checkEligibility(
    url: string,
    body: { amount: string },
    signal: AbortSignal,
): Promise<{ eligible: boolean; message?: string }> {
    const token = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
        ?.slice('XSRF-TOKEN='.length);
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        signal,
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            ...(token ? { 'X-XSRF-TOKEN': decodeURIComponent(token) } : {}),
        },
        body: JSON.stringify(body),
    });

    // Fail open: only an explicit rejection disables Tamara.
    if (!response.ok) {
        return { eligible: true };
    }

    return (await response.json()) as { eligible: boolean; message?: string };
}
