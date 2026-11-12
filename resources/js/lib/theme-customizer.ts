import { lazy } from 'react';
import type { ComponentType } from 'react';
import type {
    ThemeCustomizerControls,
    ThemeCustomizerPageProps,
} from '@/pages/admin/themes/customize';

export type ThemeCustomizerProps = ThemeCustomizerPageProps & {
    DefaultCustomizer: ComponentType<ThemeCustomizerPageProps>;
    controls: ThemeCustomizerControls;
};

// Admin-only discovery. The storefront runtime never imports theme editor code.
const entries = import.meta.glob<{
    default: ComponentType<ThemeCustomizerProps>;
}>('../../../themes/*/*/src/customizer.tsx');
const components = Object.fromEntries(
    Object.entries(entries).map(([path, loader]) => [path, lazy(loader)]),
);

export function themeCustomizer(
    name: string,
    entry: string | null | undefined,
) {
    return entry === 'src/customizer.tsx'
        ? components[`../../../themes/${name}/${entry}`]
        : undefined;
}
