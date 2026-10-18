/**
 * Tamara's "Pay in 4" messaging. Tamara has no browser SDK for the storefront
 * snippets, so the store renders the schedule itself from the order total the
 * server computed; nothing here talks to Tamara or holds a credential.
 */
export type SnippetProps = {
    source: 'product' | 'cart' | 'checkout';
    price: string;
    currency: string;
    lang: 'en' | 'ar';
    instalments: number;
    schedule: { label: string; amount: string }[];
    learnMoreUrl: string;
    inheritBackground: boolean;
};

/** Formats an amount with the store currency, falling back to a plain string. */
export function formatMoney(
    amount: string,
    currency: string,
    lang: string,
): string {
    const value = Number(amount);

    if (!Number.isFinite(value)) {
        return `${currency} ${amount}`;
    }

    try {
        return new Intl.NumberFormat(lang === 'ar' ? 'ar' : 'en', {
            style: 'currency',
            currency,
            maximumFractionDigits: 2,
        }).format(value);
    } catch {
        return `${currency} ${value.toFixed(2)}`;
    }
}

/** The per-payment share from the schedule the server built. */
export function share(props: SnippetProps): string {
    return props.schedule[0]?.amount ?? props.price;
}

export function promoText(props: SnippetProps): string {
    const per = formatMoney(share(props), props.currency, props.lang);

    return props.lang === 'ar'
        ? `قسّمها على ${props.instalments} دفعات بدون فوائد، ${per} لكل دفعة.`
        : `Pay in ${props.instalments} interest-free payments of ${per}.`;
}

export function learnMoreLabel(lang: string): string {
    return lang === 'ar' ? 'اعرف المزيد' : 'Learn more';
}
