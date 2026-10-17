import { usePage } from '@inertiajs/react';
import type { PageSettings } from '@/types/page-settings';

/** The values an untouched store uses (App\Core\Themes\PageSettingFields::DEFAULTS). */
export const DEFAULT_PAGE_SETTINGS: PageSettings = {
    header: {
        logo_height: null,
        background: null,
        text: null,
        search: null,
        menu_align: null,
        cart_icon: null,
        cart_count: true,
        account_link: true,
    },
    catalog: {
        columns_desktop: null,
        columns_mobile: 2,
        per_page: 24,
        default_sort: 'newest',
        filters: true,
        image_ratio: null,
        hover_image: false,
        quick_add: null,
        badges: true,
        show_brand: false,
    },
    product: {
        thumbnails: null,
        zoom: false,
        show_sku: true,
        stock_note: 'low',
        description: 'open',
        related: true,
        related_count: 4,
        upsells: true,
        quantity: true,
    },
    cart: {
        free_shipping_bar: false,
        cross_sells: false,
        coupon: true,
        trust_badges: false,
        continue_link: false,
    },
    checkout: {
        summary_side: 'right',
        sticky_summary: true,
        company: true,
        address_line2: true,
        phone: 'optional',
        order_note: true,
        terms: false,
        trust_badges: false,
        heading: 'Checkout',
        button_label: 'Place order',
        summary_images: true,
        coupon: true,
    },
};

/**
 * The merchant's page settings from the customizer (Header, Catalog,
 * Product, Cart and Checkout tabs). Every theme reads them here.
 */
export function useStoreSettings(): PageSettings {
    const { storefront } = usePage<{
        storefront?: { theme?: { pages?: PageSettings } };
    }>().props;

    return storefront?.theme?.pages ?? DEFAULT_PAGE_SETTINGS;
}
