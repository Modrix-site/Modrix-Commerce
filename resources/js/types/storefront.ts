import type { MoneyValue } from './admin';
export type StoreMenu = {
    title: string;
    links: {
        label: string;
        url: string;
        type?: 'link' | 'page' | 'category';
        page_id?: number | null;
        category_id?: number | null;
    }[];
};
export type StoreMenus = { header: StoreMenu; footer: StoreMenu[] };

export type StorefrontShared = {
    seoTitle?: string | null;
    seoDescription?: string | null;
    direction?: 'rtl' | 'ltr';
    displayCurrency?: string;
    canSwitchCurrency?: boolean;
    baseCurrency?: string;
    currencies?: { code: string; name: string }[];
    currencyNotice?: string | null;
    storeName: string;
    navigation?: StoreMenus;
    currency: string;
    locale: string;
    locales: { code: string; name: string }[];
    categories: { id: number; name: string; slug: string }[];
    cartCount: number;
    theme: StorefrontTheme;
    /** Global overlays (marketing popups, cookie bars) rendered on every page. */
    overlays?: {
        key: string;
        component: string;
        props: Record<string, unknown>;
    }[];
    footerSlots?: import('@/components/extensions/extension-slot').SlotItem[];
    themePreview: {
        name: string;
        displayName: string;
        exitUrl: string;
        manageUrl: string;
    } | null;
};

export type StorefrontTheme = {
    name: string;
    displayName: string;
    version: string;
    frontend?: Partial<
        Record<
            | 'index'
            | 'catalog'
            | 'cart'
            | 'header'
            | 'footer'
            | 'product'
            | 'stylesheet',
            string
        >
    >;
    source: 'active' | 'preview' | 'default' | 'builtin';
    settings?: Record<string, string | number | boolean | null>;
    css: string;
    layout: {
        header: 'classic' | 'centered' | 'minimal' | 'electronics' | 'atelier';
        footer: 'simple' | 'columns' | 'electronics' | 'atelier';
        productCard:
            | 'standard'
            | 'minimal'
            | 'framed'
            | 'electronics'
            | 'atelier';
        gridColumns: 3 | 4;
        productPage: 'split' | 'stacked' | 'electronics' | 'atelier';
        density: 'compact' | 'comfortable' | 'spacious';
        container: 'narrow' | 'default' | 'reference' | 'wide';
    };
    logo: string | null;
    favicon: string | null;
    announcement: string | null;
    footerText: string | null;
    content?: Record<string, string>;
};

export type ProductCard = {
    variant_id?: number | null;
    id: number;
    name: string;
    slug: string;
    short_description: string | null;
    image: { url: string; alt: string | null } | null;
    price: MoneyValue | null;
    compare_at_price: MoneyValue | null;
    has_variants: boolean;
    in_stock: boolean;
    /** A module sale (e.g. a flash sale) on this product. */
    sale?: SaleInfo | null;
    labels?: ProductLabel[];
    /** Per-product Buy button override: null follows the theme setting. */
    show_buy_button?: boolean | null;
    /** The second image, shown on hover when the Catalog tab turns it on. */
    hover_image?: { url: string; alt: string | null } | null;
    brand?: { name: string; slug: string } | null;
};

/** A sale on a product (e.g. a flash sale), for the tag and notice. */
export type SaleInfo = {
    /** "−20%" or "−$5.00". */
    badge: string;
    /** The sale's name, e.g. "Weekend Flash Sale". */
    name: string;
    /** Tag text, e.g. "Flash sale". */
    label: string;
    ends_at: string | null;
    /** Whether to show a countdown to ends_at. */
    countdown: boolean;
};

/** A badge contributed by a module (e.g. Product Label) for a product card. */
export type ProductLabel = {
    key: string;
    text: string;
    tone: 'new' | 'sale' | 'featured' | 'stock' | 'info' | 'warning' | 'danger';
    position: 'top-left' | 'top-right' | 'bottom-left' | 'bottom-right';
    style: 'solid' | 'soft' | 'outline';
};

export type CartLine = {
    id: number;
    variant_id: number;
    product_name: string;
    product_slug: string;
    variant_name: string | null;
    sku: string;
    image: { url: string; alt: string | null } | null;
    quantity: number;
    unit_price: MoneyValue | null;
    subtotal: MoneyValue;
    discount: MoneyValue;
    purchasable: boolean;
    available: number | null;
    notices: string[];
};

export type PricedCart = {
    currency: string;
    lines: CartLine[];
    item_count: number;
    subtotal: MoneyValue;
    discount_total: MoneyValue;
    shipping_total: MoneyValue;
    tax_total: MoneyValue;
    grand_total: MoneyValue;
    prices_include_tax: boolean;
    coupon_code: string | null;
    coupon_error: string | null;
    shipping: {
        code: string;
        label: string;
        amount: MoneyValue;
        description?: string | null;
    } | null;
    /** False when every item is digital (nothing to ship). */
    requires_shipping?: boolean;
    shipping_options: {
        code: string;
        label: string;
        amount: MoneyValue;
        description?: string | null;
    }[];
    issues: string[];
    can_checkout: boolean;
};

export type Address = {
    id: number;
    first_name: string;
    last_name: string;
    company: string | null;
    line1: string;
    line2: string | null;
    city: string;
    region: string | null;
    postal_code: string | null;
    country_code: string;
    country_name: string;
    phone: string | null;
    is_default_shipping: boolean;
    is_default_billing: boolean;
};

export type Country = { code: string; name: string };

export type OrderSummary = {
    number: string;
    email: string;
    status: string;
    status_label: string;
    payment_status: string;
    fulfillment_status: string;
    display_status: string;
    display_label: string;
    grand_total: MoneyValue;
    placed_at: string;
};

export type OrderItemData = {
    id: number;
    sku: string;
    product_name: string;
    variant_name: string | null;
    quantity: number;
    restocked_quantity: number;
    unit_price: MoneyValue;
    subtotal: MoneyValue;
    discount: MoneyValue;
    tax: MoneyValue;
    total: MoneyValue;
};

export type OrderAddressData = Omit<
    Address,
    'id' | 'is_default_shipping' | 'is_default_billing'
> & {
    type: string;
};

/** A customer's link to a purchased file; url is null once it no longer works. */
export type DownloadLink = {
    id: number;
    file: string;
    product: string;
    url: string | null;
    downloads: number;
    max_downloads: number | null;
    expires_at: string | null;
    usable: boolean;
    last_downloaded_at: string | null;
};

export type OrderDetail = OrderSummary & {
    currency: string;
    subtotal: MoneyValue;
    discount_total: MoneyValue;
    shipping_total: MoneyValue;
    tax_total: MoneyValue;
    prices_include_tax: boolean;
    payment_method: string;
    shipping_method: string | null;
    coupon_code: string | null;
    customer_note: string | null;
    items: OrderItemData[];
    addresses: Record<string, OrderAddressData>;
    shipments: {
        carrier: string | null;
        tracking_number: string | null;
        tracking_url: string | null;
        shipped_at: string;
        delivered_at: string | null;
    }[];
    history: {
        id?: number;
        type?: string;
        message: string;
        customer_visible?: boolean;
        actor?: string | null;
        created_at: string;
    }[];
    /** Digital products: one link per purchased file. */
    downloads?: DownloadLink[];
    documents?: {
        invoice: { number: string; url: string } | null;
        receipts: {
            id: number;
            label: string;
            amount: MoneyValue;
            paid_at: string | null;
            url: string;
        }[];
    };
};
