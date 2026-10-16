import type { PickerItem } from '@/components/admin/products/product-picker';
import type { CategoryOption, Option } from '@/types';

export type AttributeOption = {
    id: number;
    name: string;
    values: { id: number; value: string }[];
};

export type VariantData = {
    id?: number;
    sku: string;
    name: string | null;
    price: string;
    compare_at_price: string;
    cost: string;
    weight: string | null;
    length: string | null;
    width: string | null;
    height: string | null;
    barcode: string | null;
    track_inventory: boolean;
    allow_backorder: boolean;
    is_active: boolean;
    low_stock_threshold: number | null;
    option_value_ids: number[];
    on_hand?: number | null;
};

export type ProductData = {
    id: number;
    type: string;
    name: string;
    slug: string;
    status: string;
    published_at: string | null;
    short_description: string | null;
    description: string | null;
    meta_title: string | null;
    meta_description: string | null;
    category_ids: number[];
    brand_id: number | null;
    is_featured: boolean;
    tags: string[];
    related?: PickerItem[];
    upsells?: PickerItem[];
    view_url: string | null;
    option_attribute_ids: number[];
    variants: VariantData[];
    images: { id: number; url: string; alt: string | null }[];
};

export type ProductFormProps = {
    product: ProductData | null;
    currency: string;
    weightUnit: string;
    dimensionUnit: string;
    categories: CategoryOption[];
    brands: { id: number; name: string }[];
    tagSuggestions: string[];
    statuses: Option[];
    types: Option[];
    attributes: AttributeOption[];
};

export type VariantRow = {
    id: number | null;
    sku: string;
    name: string;
    price: string;
    compare_at_price: string;
    cost: string;
    weight: string;
    length: string;
    width: string;
    height: string;
    barcode: string;
    track_inventory: boolean;
    allow_backorder: boolean;
    is_active: boolean;
    low_stock_threshold: string;
    option_value_ids: number[];
    initial_stock: string;
    on_hand: number | null;
};

export type ProductFormData = {
    type: string;
    name: string;
    slug: string;
    status: string;
    published_at: string;
    short_description: string;
    description: string;
    meta_title: string;
    meta_description: string;
    category_ids: number[];
    brand_id: string;
    is_featured: boolean;
    tags: string[];
    related_ids: number[];
    upsell_ids: number[];
    option_attribute_ids: number[];
    variants: VariantRow[];
};

export type Errors = Record<string, string | undefined>;

export const emptyVariant = (): VariantRow => ({
    id: null,
    sku: '',
    name: '',
    price: '',
    compare_at_price: '',
    cost: '',
    weight: '',
    length: '',
    width: '',
    height: '',
    barcode: '',
    track_inventory: true,
    allow_backorder: false,
    is_active: true,
    low_stock_threshold: '',
    option_value_ids: [],
    initial_stock: '0',
    on_hand: null,
});

export function toRow(v: VariantData): VariantRow {
    return {
        id: v.id ?? null,
        sku: v.sku,
        name: v.name ?? '',
        price: v.price,
        compare_at_price: v.compare_at_price ?? '',
        cost: v.cost ?? '',
        weight: v.weight ?? '',
        length: v.length ?? '',
        width: v.width ?? '',
        height: v.height ?? '',
        barcode: v.barcode ?? '',
        track_inventory: v.track_inventory,
        allow_backorder: v.allow_backorder,
        is_active: v.is_active,
        low_stock_threshold:
            v.low_stock_threshold === null ? '' : String(v.low_stock_threshold),
        option_value_ids: v.option_value_ids,
        initial_stock: '0',
        on_hand: v.on_hand ?? null,
    };
}

/** Margin in percent, or null when it cannot be worked out. */
export function margin(price: string, cost: string): number | null {
    const p = Number(price);
    const c = Number(cost);

    if (price === '' || cost === '' || !Number.isFinite(p) || p <= 0) {
        return null;
    }

    return Math.round(((p - c) / p) * 1000) / 10;
}
