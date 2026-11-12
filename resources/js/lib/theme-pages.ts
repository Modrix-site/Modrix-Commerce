import { lazy } from 'react';
import type { ComponentType } from 'react';
import type { HomeProps } from '@/pages/storefront/home';
import type { ListingProps } from '@/pages/storefront/listing';
import type { CartProps } from '@/pages/storefront/cart';
const carts = import.meta.glob<{ default: ComponentType<CartProps> }>(
    '../../../themes/*/*/src/cart.tsx',
);
const cartPages = Object.fromEntries(
    Object.entries(carts).map(([key, loader]) => [key, lazy(loader)]),
);
export function themeCart(name: string | undefined) {
    return cartPages[`../../../themes/${name}/src/cart.tsx`];
}
// Home, header, and footer paint with the first render. A lazy chunk left
// the page on "Loading storefront…" while the rest of the shell appeared.
const indexes = import.meta.glob<{ default: ComponentType<HomeProps> }>(
    '../../../themes/*/*/src/index.tsx',
    { eager: true },
);
const catalogs = import.meta.glob<{ default: ComponentType<ListingProps> }>(
    '../../../themes/*/*/src/catalog.tsx',
);
const catalogPages = Object.fromEntries(
    Object.entries(catalogs).map(([key, loader]) => [key, lazy(loader)]),
);
export function themeIndex(name: string | undefined) {
    return indexes[`../../../themes/${name}/src/index.tsx`]?.default;
}
export function themeCatalog(name: string | undefined) {
    return catalogPages[`../../../themes/${name}/src/catalog.tsx`];
}

export type ThemeProductProps =
    import('@/pages/storefront/product').ProductPageProps & {
        variant: import('@/pages/storefront/product').Variant | undefined;
        imageIndex: number;
        onImage: (index: number) => void;
        selected: Record<number, number>;
        onOption: (attributeId: number, valueId: number) => void;
    };
const headers = import.meta.glob<{ default: ComponentType }>(
    '../../../themes/*/*/src/header.tsx',
    { eager: true },
);
const footers = import.meta.glob<{ default: ComponentType }>(
    '../../../themes/*/*/src/footer.tsx',
    { eager: true },
);
const products = import.meta.glob<{
    default: ComponentType<ThemeProductProps>;
}>('../../../themes/*/*/src/product.tsx');
const productParts = Object.fromEntries(
    Object.entries(products).map(([key, loader]) => [key, lazy(loader)]),
);
export function themeHeader(name: string | undefined) {
    return headers[`../../../themes/${name}/src/header.tsx`]?.default;
}
export function themeFooter(name: string | undefined) {
    return footers[`../../../themes/${name}/src/footer.tsx`]?.default;
}
export function themeProduct(name: string | undefined) {
    return productParts[`../../../themes/${name}/src/product.tsx`];
}
