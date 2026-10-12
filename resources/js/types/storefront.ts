import type { MoneyValue } from './admin';

export type StorefrontShared = {
    storeName: string;
    currency: string;
    categories: { id: number; name: string; slug: string }[];
    cartCount: number;
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
