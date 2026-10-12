import type { MoneyValue } from './admin';

export type StorefrontShared = {
    storeName: string;
    currency: string;
    categories: { id: number; name: string; slug: string }[];
    cartCount: number;
    theme: StorefrontTheme;
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
    source: 'active' | 'preview' | 'default' | 'builtin';
    css: string;
    layout: {
        header: 'classic' | 'centered' | 'minimal';
        footer: 'simple' | 'columns';
        productCard: 'standard' | 'minimal' | 'framed';
        gridColumns: 3 | 4;
        productPage: 'split' | 'stacked';
        density: 'compact' | 'comfortable' | 'spacious';
        container: 'narrow' | 'default' | 'wide';
    };
    logo: string | null;
    favicon: string | null;
    announcement: string | null;
    footerText: string | null;
};

export type ProductCard = {
    id: number;
    name: string;
    slug: string;
    short_description: string | null;
    image: { url: string; alt: string | null } | null;
    price: MoneyValue | null;
    compare_at_price: MoneyValue | null;
    has_variants: boolean;
    in_stock: boolean;
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
    shipping: { code: string; label: string; amount: MoneyValue } | null;
    shipping_options: { code: string; label: string; amount: MoneyValue }[];
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
};
