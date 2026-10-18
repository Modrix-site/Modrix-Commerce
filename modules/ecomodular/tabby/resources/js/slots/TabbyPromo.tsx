import { useEffect, useRef, useState } from 'react';
import { fallbackText, loadTabbyScript, snippetId } from '../lib/tabby';
import type { SnippetProps } from '../lib/tabby';

/**
 * Tabby's split-payment message on product and cart pages. Uses Tabby's own
 * TabbyPromo widget; if its script cannot load, shows Tabby's approved
 * static text with the official "Learn more" pop-up link.
 */
export default function TabbyPromo({
    source,
    price,
    currency,
    lang,
    publicKey,
    merchantCode,
    inheritBackground,
    popupUrl,
}: SnippetProps) {
    const [id] = useState(() => snippetId('tabby-promo'));
    const [failed, setFailed] = useState(false);
    const container = useRef<HTMLDivElement>(null);

    useEffect(() => {
        let cancelled = false;

        loadTabbyScript('tabby-promo')
            .then(() => {
                if (cancelled || !window.TabbyPromo || !container.current) {
                    return;
                }

                // Re-initialised whenever the price changes.
                container.current.innerHTML = '';
                new window.TabbyPromo({
                    selector: `#${id}`,
                    currency,
                    price,
                    lang,
                    source: source === 'cart' ? 'cart' : 'product',
                    shouldInheritBg: inheritBackground,
                    publicKey,
                    merchantCode,
                });
            })
            .catch(() => !cancelled && setFailed(true));

        return () => {
            cancelled = true;
        };
    }, [
        id,
        source,
        price,
        currency,
        lang,
        publicKey,
        merchantCode,
        inheritBackground,
    ]);

    if (failed) {
        const copy = fallbackText(price, currency, lang);

        return (
            <p
                className="text-sm text-muted-foreground"
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
            className="ecomodular-tabby-promo min-h-[3.5rem] max-w-full"
            data-tabby-source={source}
        />
    );
}
