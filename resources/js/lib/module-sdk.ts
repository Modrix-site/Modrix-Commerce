import * as Inertia from '@inertiajs/react';
import * as React from 'react';
import * as ReactDom from 'react-dom';
import * as JsxRuntime from 'react/jsx-runtime';

/**
 * The store's side of prebuilt module UI.
 *
 * A module installed from Modrix Cloud ships its screens already compiled
 * (`ecomodular:module:build`). Its code does not bundle React, Inertia or
 * the store's components: each such import becomes
 * `await __modrix.load('<specifier>')`, answered here with the store's own
 * copy, so a module shares React state, contexts, UI components and styles
 * with the page it renders in.
 *
 * Shared specifiers: react, react/jsx-runtime, react-dom, @inertiajs/react,
 * and the store's own files under @/components, @/hooks, @/lib, @/routes,
 * @/actions and @/pages.
 * Renaming or removing one of those files breaks modules that use it.
 */
type Namespace = Record<string, unknown>;

const SDK_VERSION = 1;

const shared: Record<string, Namespace> = {
    react: React,
    'react/jsx-runtime': JsxRuntime,
    'react-dom': ReactDom,
    '@inertiajs/react': Inertia,
};

// Root-relative patterns, so every key reads "/resources/js/<dir>/...".
// (Relative patterns key this file's own folder as "./..." not "../lib/...".)
const host = import.meta.glob<Namespace>([
    '/resources/js/components/**/*.{ts,tsx}',
    '/resources/js/hooks/*.{ts,tsx}',
    '/resources/js/lib/*.{ts,tsx}',
    '/resources/js/routes/**/*.ts',
    '/resources/js/actions/**/*.ts',
    '/resources/js/pages/**/*.tsx',
]);

const SPECIFIER =
    /^@\/(components|hooks|lib|routes|actions|pages)(\/[A-Za-z0-9_\-/.]+)?$/;

export function loadShared(specifier: string): Promise<Namespace> {
    const known = shared[specifier];

    if (known) {
        return Promise.resolve(known);
    }

    if (SPECIFIER.test(specifier) && !specifier.includes('..')) {
        const path = `/resources/js/${specifier.slice(2)}`;
        const loader =
            host[`${path}.tsx`] ??
            host[`${path}.ts`] ??
            host[`${path}/index.ts`] ??
            host[`${path}/index.tsx`];

        if (loader) {
            return loader();
        }
    }

    return Promise.reject(
        new Error(
            `Module asked for "${specifier}", which this store does not provide.`,
        ),
    );
}

/** Default import of a shared module (React and friends are CommonJS-shaped). */
function interopDefault(namespace: Namespace): unknown {
    return 'default' in namespace ? namespace.default : namespace;
}

declare global {
    interface Window {
        __modrix?: {
            version: number;
            load: typeof loadShared;
            def: typeof interopDefault;
        };
    }
}

export function installModuleSdk(): void {
    if (typeof window !== 'undefined' && !window.__modrix) {
        window.__modrix = {
            version: SDK_VERSION,
            load: loadShared,
            def: interopDefault,
        };
    }
}
