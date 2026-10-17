import type { CSSProperties } from 'react';
import type { PageSettings } from '@/types/page-settings';

/**
 * Data attributes and CSS variables for the storefront root, so every
 * theme's CSS (resources/css/page-settings.css and the themes' own files)
 * can follow the visual page settings. Only values the merchant set appear.
 */
export function pageSettingAttributes(
    pages: PageSettings,
): Record<string, string | CSSProperties | undefined> {
    const { header, catalog, product } = pages;
    const style: Record<string, string> = {};

    if (header.logo_height !== null) {
        style['--header-logo-height'] = `${header.logo_height}px`;
    }
    if (header.background !== null) {
        style['--header-bg'] = header.background;
    }
    if (header.text !== null) {
        style['--header-fg'] = header.text;
    }

    return {
        'data-logo-height': header.logo_height !== null ? '' : undefined,
        'data-header-bg': header.background !== null ? '' : undefined,
        'data-header-fg': header.text !== null ? '' : undefined,
        'data-header-search': header.search ?? undefined,
        'data-menu-align': header.menu_align ?? undefined,
        'data-catalog-columns':
            catalog.columns_desktop !== null
                ? String(catalog.columns_desktop)
                : undefined,
        'data-catalog-columns-mobile': String(catalog.columns_mobile),
        'data-image-ratio': catalog.image_ratio ?? undefined,
        'data-hover-image': catalog.hover_image ? '' : undefined,
        // Themes with their own product gallery mark the image with .store-zoom.
        'data-product-zoom': product.zoom ? '' : undefined,
        style:
            Object.keys(style).length > 0
                ? (style as CSSProperties)
                : undefined,
    };
}
