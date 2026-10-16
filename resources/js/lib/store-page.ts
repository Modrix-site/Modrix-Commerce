/**
 * The storefront area a page belongs to, for the customizer's per-page
 * fonts, prices and buttons (`data-page` on the storefront root).
 */
export type StorePage = 'home' | 'catalog' | 'product' | 'cart';

const PAGES: Record<string, StorePage> = {
    'storefront/home': 'home',
    'storefront/listing': 'catalog',
    'storefront/product': 'product',
    'storefront/cart': 'cart',
    'storefront/checkout': 'cart',
    'storefront/order-complete': 'cart',
};

export function storePageFor(component: string): StorePage | undefined {
    return PAGES[component];
}
