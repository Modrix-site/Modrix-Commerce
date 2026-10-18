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
const indexes = import.meta.glob<{ default: ComponentType<HomeProps> }>(
    '../../../themes/*/*/src/index.tsx',
);
const catalogs = import.meta.glob<{ default: ComponentType<ListingProps> }>(
    '../../../themes/*/*/src/catalog.tsx',
);
const homePages = Object.fromEntries(
    Object.entries(indexes).map(([key, loader]) => [key, lazy(loader)]),
);
const catalogPages = Object.fromEntries(
    Object.entries(catalogs).map(([key, loader]) => [key, lazy(loader)]),
);
export function themeIndex(name: string | undefined) {
    return homePages[`../../../themes/${name}/src/index.tsx`];
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
);
const footers = import.meta.glob<{ default: ComponentType }>(
    '../../../themes/*/*/src/footer.tsx',
);
const products = import.meta.glob<{
    default: ComponentType<ThemeProductProps>;
}>('../../../themes/*/*/src/product.tsx');
const headerParts = Object.fromEntries(
    Object.entries(headers).map(([key, loader]) => [key, lazy(loader)]),
);
const footerParts = Object.fromEntries(
    Object.entries(footers).map(([key, loader]) => [key, lazy(loader)]),
);
const productParts = Object.fromEntries(
    Object.entries(products).map(([key, loader]) => [key, lazy(loader)]),
);
export function themeHeader(name: string | undefined) {
    return headerParts[`../../../themes/${name}/src/header.tsx`];
}
export function themeFooter(name: string | undefined) {
    return footerParts[`../../../themes/${name}/src/footer.tsx`];
}
export function themeProduct(name: string | undefined) {
    return productParts[`../../../themes/${name}/src/product.tsx`];
}
