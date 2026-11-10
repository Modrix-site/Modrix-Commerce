/**
 * Loads Tabby's snippet scripts once per page. The store's Content Security
 * Policy allows checkout.tabby.ai because the module declares it.
 */
const loading = new Map<string, Promise<void>>();

export type SnippetProps = {
    source: 'product' | 'cart' | 'checkout';
    price: string;
    currency: string;
    lang: 'en' | 'ar';
    publicKey: string;
    merchantCode: string;
    inheritBackground: boolean;
    popupUrl: string;
};

type TabbyWidget = new (options: Record<string, unknown>) => unknown;

declare global {
    interface Window {
        TabbyPromo?: TabbyWidget;
        TabbyCard?: TabbyWidget;
    }
}

export function loadTabbyScript(
    name: 'tabby-promo' | 'tabby-card',
): Promise<void> {
    const src = `https://checkout.tabby.ai/${name}.js`;
    let promise = loading.get(src);

    if (!promise) {
        promise = new Promise<void>((resolve, reject) => {
            const script = document.createElement('script');
            script.src = src;
            script.async = true;
            script.onload = () => resolve();
            script.onerror = () => {
                loading.delete(src);
                script.remove();
                reject(new Error(`Could not load ${src}`));
            };
            document.head.appendChild(script);
        });
        loading.set(src, promise);
    }

    return promise;
}

/** Tabby's approved static description, used when its script cannot load. */
export function fallbackText(price: string, currency: string, lang: string) {
    // Tabby's formula for the static description: total × 1.015 / 4.
    const monthly = ((Number(price) * 1.015) / 4).toFixed(2);

    return lang === 'ar'
        ? {
              text: `4 دفعات بقيمة ${monthly} ${currency} شهريًا أو حتى 12 شهرًا.`,
              more: 'اعرف المزيد',
          }
        : {
              text: `4 payments of ${currency} ${monthly}/mo or up to 12 months.`,
              more: 'Learn more',
          };
}

let counter = 0;

/** A unique container id; never "TabbyPromo"/"TabbyCard", which would shadow Tabby's globals. */
export function snippetId(kind: string): string {
    counter += 1;

    return `ecomodular-${kind}-${counter}`;
}
