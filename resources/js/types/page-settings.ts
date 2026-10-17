/**
 * Page settings from the customizer (App\Core\Themes\PageSettingFields::resolve),
 * sent as `storefront.theme.pages`. null means the theme decides.
 */
export type PageSettings = {
    header: {
        logo_height: number | null;
        background: string | null;
        text: string | null;
        search: 'bar' | 'icon' | null;
        menu_align: 'left' | 'center' | 'right' | null;
        cart_icon: 'bag' | 'cart' | null;
        cart_count: boolean;
        account_link: boolean;
    };
    catalog: {
        columns_desktop: number | null;
        columns_mobile: number;
        per_page: number;
        default_sort: string;
        filters: boolean;
        image_ratio: 'square' | 'portrait' | 'landscape' | null;
        hover_image: boolean;
        quick_add: boolean;
        badges: boolean;
        show_brand: boolean;
    };
    product: {
        thumbnails: 'side' | 'bottom' | null;
        zoom: boolean;
        show_sku: boolean;
        stock_note: 'off' | 'low' | 'always';
        description: 'open' | 'tabs' | 'accordion';
        related: boolean;
        related_count: number;
        upsells: boolean;
        quantity: boolean;
    };
    cart: {
        free_shipping_bar: boolean;
        cross_sells: boolean;
        coupon: boolean;
        trust_badges: boolean;
        continue_link: boolean;
    };
    checkout: {
        summary_side: 'right' | 'left';
        sticky_summary: boolean;
        company: boolean;
        address_line2: boolean;
        phone: 'hidden' | 'optional' | 'required';
        order_note: boolean;
        terms: boolean;
        trust_badges: boolean;
        heading: string;
        button_label: string;
        summary_images: boolean;
        coupon: boolean;
    };
};
