import { useEffect, useRef, useState } from 'react';
import { fallbackText, loadTabbyScript, snippetId } from '../lib/tabby';
import type { SnippetProps } from '../lib/tabby';

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
 * Under the Tabby payment method at checkout: Tabby's payment schedule card
 * while Tabby is selected, and a background eligibility check once the
 * shopper's email and phone are known. A rejected shopper sees Tabby's
 * message and the method is disabled; errors leave Tabby available (the
 * order is checked again when it is placed).
 */
export default function TabbyCheckout(props: Props) {
    const { checkout, eligibilityUrl, price, showCard } = props;
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
                { email, phone, amount: price },
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

    if (!showCard || !checkout?.selected) {
        return null;
    }

    return <TabbyCard {...props} />;
}

function TabbyCard({
    price,
    currency,
    lang,
    publicKey,
    merchantCode,
    inheritBackground,
    popupUrl,
}: SnippetProps) {
    const [id] = useState(() => snippetId('tabby-card'));
    const [failed, setFailed] = useState(false);
    const container = useRef<HTMLDivElement>(null);

    useEffect(() => {
        let cancelled = false;

        loadTabbyScript('tabby-card')
            .then(() => {
                if (cancelled || !window.TabbyCard || !container.current) {
                    return;
                }

                container.current.innerHTML = '';
                new window.TabbyCard({
                    selector: `#${id}`,
                    currency,
                    price,
                    lang,
                    shouldInheritBg: inheritBackground,
                    publicKey,
                    merchantCode,
                });
            })
            .catch(() => !cancelled && setFailed(true));

        return () => {
            cancelled = true;
        };
    }, [id, price, currency, lang, publicKey, merchantCode, inheritBackground]);

    if (failed) {
        const copy = fallbackText(price, currency, lang);

        return (
            <p
                className="text-muted-foreground"
                dir={lang === 'ar' ? 'rtl' : undefined}
            >
                {copy.text}{' '}
                <a
                    href={popupUrl}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="underline"
                >
                    {copy.more}
                </a>
            </p>
        );
    }

    return (
        <div
            id={id}
            ref={container}
            className="ecomodular-tabby-card max-w-full"
        />
    );
}

async function checkEligibility(
    url: string,
    body: { email: string; phone: string; amount: string },
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

    // Fail open: only an explicit rejection disables Tabby.
    if (!response.ok) {
        return { eligible: true };
    }

    return (await response.json()) as { eligible: boolean; message?: string };
}
