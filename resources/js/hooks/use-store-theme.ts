import { usePage } from '@inertiajs/react';
import type { StorefrontTheme } from '@/types';

const FALLBACK_LAYOUT: StorefrontTheme['layout'] = {
    header: 'classic',
    footer: 'simple',
    productCard: 'standard',
    gridColumns: 4,
    productPage: 'split',
    density: 'comfortable',
    container: 'default',
};

/**
 * The theme the current storefront page renders with. Values were validated
 * on the server; themes may provide their own browser components.
 */
export function useStoreTheme(): StorefrontTheme['layout'] & {
    theme: StorefrontTheme | null;
} {
    const { storefront } = usePage().props;
    const theme = storefront?.theme ?? null;

    return { ...(theme?.layout ?? FALLBACK_LAYOUT), theme };
}
