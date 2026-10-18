import { learnMoreLabel, promoText } from '../lib/tamara';
import type { SnippetProps } from '../lib/tamara';

/**
 * Tamara's split-payment message on product and cart pages. Rendered from the
 * schedule the server computed: no third-party script and no credential in the
 * browser. Styled with theme tokens so it sits inside any storefront theme.
 */
export default function TamaraPromo({
    source,
    lang,
    learnMoreUrl,
    inheritBackground,
    ...props
}: SnippetProps) {
    return (
        <p
            className="ecomodular-tamara-promo text-sm text-muted-foreground"
            dir={lang === 'ar' ? 'rtl' : undefined}
            data-tamara-source={source}
            data-tamara-bg={inheritBackground ? 'inherit' : 'plain'}
        >
            {promoText({
                source,
                lang,
                learnMoreUrl,
                inheritBackground,
                ...props,
            })}{' '}
            <a
                href={learnMoreUrl}
                target="_blank"
                rel="noopener noreferrer"
                className="underline underline-offset-4"
            >
                {learnMoreLabel(lang)}
            </a>
        </p>
    );
}
