import type { ComponentType } from 'react';
import { installModuleSdk } from '@/lib/module-sdk';

/**
 * Prebuilt theme screens, for themes installed from Modrix Cloud or a ZIP
 * after the store's assets were built.
 *
 * A theme package's `dist/` (made by `ecomodular:module:build`) holds one ES
 * module per source file under src/ (header, footer, layout, pages, sections…)
 * and one stylesheet. Before a storefront page or the theme customizer
 * renders, `ensureTheme()` loads every file of the active theme, so the
 * lookups below can answer synchronously, exactly like the bundled themes
 * found by `import.meta.glob`.
 */
type Entry = { default: ComponentType<never> } & Record<string, unknown>;

/** dist/theme-ui.json: source path under the package (e.g. "src/header.tsx") → built file. */
type PrebuiltTheme = {
    sdk: number;
    entries: Record<string, string>;
    css: string[];
};

const NAME = /^[a-z0-9-]+\/[a-z0-9-]+$/;

/** Themes that were in the repository when the store's assets were built. */
const bundled = new Set(
    Object.keys(
        import.meta.glob('../../../themes/*/*/theme.json', {
            eager: true,
            import: 'name',
        }),
    ).map((path) => path.split('/').slice(4, 6).join('/')),
);

export function isBundledTheme(theme: string | null | undefined): boolean {
    return !!theme && bundled.has(theme);
}
const FILE = /^[A-Za-z0-9_\-/.]+\.(js|css)$/;

const loaded = new Map<string, Record<string, Entry>>();
const pending = new Map<string, Promise<void>>();

function assetUrl(theme: string, file: string): string {
    return `/theme-build/${theme}/${file}`;
}

function activateStyles(theme: string, css: string[]): void {
    for (const link of document.querySelectorAll<HTMLLinkElement>(
        'link[data-prebuilt-theme]',
    )) {
        link.disabled = link.dataset.prebuiltTheme !== theme;
    }

    for (const file of css) {
        if (!FILE.test(file) || file.includes('..')) {
            continue;
        }

        const href = assetUrl(theme, file);

        if (document.querySelector(`link[href="${href}"]`)) {
            continue;
        }

        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = href;
        link.dataset.prebuiltTheme = theme;
        document.head.appendChild(link);
    }
}

async function load(theme: string): Promise<void> {
    const response = await fetch(assetUrl(theme, 'theme-ui.json'), {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    }).catch(() => null);

    if (!response?.ok) {
        loaded.set(theme, {});

        return;
    }

    const manifest = (await response.json()) as PrebuiltTheme;
    const entries: Record<string, Entry> = {};

    installModuleSdk();
    activateStyles(theme, manifest.css ?? []);

    await Promise.all(
        Object.entries(manifest.entries ?? {}).map(async ([source, file]) => {
            if (!FILE.test(file) || file.includes('..')) {
                return;
            }

            try {
                entries[source] = (await import(
                    /* @vite-ignore */ assetUrl(theme, file)
                )) as Entry;
            } catch (error) {
                console.error(
                    `Theme ${theme}: ${source} failed to load`,
                    error,
                );
            }
        }),
    );

    loaded.set(theme, entries);
}

/**
 * Loads a theme's prebuilt files once. Themes compiled with the store and
 * themes without screens resolve at once.
 */
export function ensureTheme(theme: string | null | undefined): Promise<void> {
    if (typeof window === 'undefined' || !theme || !NAME.test(theme)) {
        return Promise.resolve();
    }

    if (isBundledTheme(theme)) {
        return Promise.resolve();
    }

    const existing = loaded.get(theme);

    if (existing) {
        activateStyles(theme, []);

        return Promise.resolve();
    }

    let promise = pending.get(theme);

    if (!promise) {
        promise = load(theme).finally(() => pending.delete(theme));
        pending.set(theme, promise);
    }

    return promise;
}

/** A loaded prebuilt file of a theme, by its source path ("src/header.tsx"). */
export function prebuiltThemeEntry<T = Entry>(
    theme: string | null | undefined,
    source: string,
): T | undefined {
    return theme ? (loaded.get(theme)?.[source] as T | undefined) : undefined;
}
