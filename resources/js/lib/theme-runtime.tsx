import { usePage } from '@inertiajs/react';
import { lazy, Suspense } from 'react';
import type { ComponentType, ReactNode } from 'react';
import type { StorefrontTheme } from '@/types/storefront';

export type ThemePageProps = Record<string, unknown> & {
    /** Render the platform page with the original props to retain its behavior. */
    CorePage: ComponentType<Record<string, unknown>>;
};

export type ThemeLayoutProps = {
    children: ReactNode;
    DefaultLayout: ComponentType<{ children: ReactNode }>;
};

const pages = import.meta.glob<{ default: ComponentType<ThemePageProps> }>(
    '../../../themes/*/*/src/pages/**/*.tsx',
);
const layouts = import.meta.glob<{ default: ComponentType<ThemeLayoutProps> }>(
    '../../../themes/*/*/src/layout.tsx',
    { eager: true },
);
const styles = import.meta.glob<string>('../../../themes/*/*/src/style.css', {
    query: '?inline',
    import: 'default',
    eager: true,
});

const pageComponents = Object.fromEntries(
    Object.entries(pages).map(([path, loader]) => [path, lazy(loader)]),
);

// Inertia resolves the page again on partial reloads (including checkout quotes).
// A fresh wrapper would remount the page and discard its local form state even
// though the visit preserves state. Cache by both core component and page name.
const wrappedPages = new WeakMap<
    ComponentType<Record<string, unknown>>,
    Map<string, ComponentType<Record<string, unknown>>>
>();

function packagePath(
    theme: StorefrontTheme | undefined,
    path: string | undefined,
) {
    return theme && path ? `../../../themes/${theme.name}/${path}` : '';
}

/** Only the server-resolved active/preview theme may supply overrides. */
export function themeLayout(theme: StorefrontTheme | undefined) {
    return layouts[packagePath(theme, theme?.frontend?.layout)]?.default;
}

export function ThemeStyles({ theme }: { theme: StorefrontTheme | undefined }) {
    const css = styles[packagePath(theme, theme?.frontend?.stylesheet)];
    return css ? <style>{css}</style> : null;
}

/** Apply overrides to storefront pages without changing controllers or routes. */
export function withThemePage(
    name: string,
    CorePage: ComponentType<Record<string, unknown>>,
) {
    if (!name.startsWith('storefront/')) return CorePage;

    let wrappers = wrappedPages.get(CorePage);
    if (!wrappers) {
        wrappers = new Map();
        wrappedPages.set(CorePage, wrappers);
    }
    const cached = wrappers.get(name);
    if (cached) return cached;

    function ThemePage(props: Record<string, unknown>) {
        const { storefront } = usePage().props;
        const theme = storefront?.theme;
        const path = theme?.frontend?.pages?.[name];
        const Page = pageComponents[packagePath(theme, path)];

        return Page ? (
            <Suspense fallback={<CorePage {...props} />}>
                <Page {...props} CorePage={CorePage} />
            </Suspense>
        ) : (
            <CorePage {...props} />
        );
    }

    wrappers.set(name, ThemePage);
    return ThemePage;
}
