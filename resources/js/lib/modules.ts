import type { ComponentType } from 'react';
import { moduleUnavailable } from '@/components/module-unavailable';
import { installModuleSdk } from '@/lib/module-sdk';

/**
 * Module UI comes from one of two places:
 *
 * 1. Compiled with the application, for modules that are in the repository
 *    when the assets are built (found here by path).
 * 2. Prebuilt in the module package (`dist/`, made by `ecomodular:module:build`)
 *    and loaded at runtime from /module-assets. This is how modules
 *    installed from Modrix Cloud or uploaded as a ZIP get their screens,
 *    with no asset rebuild on the server.
 *
 * Page names:      module/<vendor>/<name>/<page>  → modules/<vendor>/<name>/resources/js/pages/<page>.tsx
 * Slot components: <vendor>/<name>/<Component>     → modules/<vendor>/<name>/resources/js/slots/<Component>.tsx
 */
type Module = { default: ComponentType<Record<string, unknown>> };

/** dist/module-ui.json: the prebuilt files, relative to the module's dist folder. */
type PrebuiltManifest = {
    sdk: number;
    pages: Record<string, string>;
    slots: Record<string, string>;
    css: string[];
};

const pages = import.meta.glob<Module>(
    '../../../modules/*/*/resources/js/pages/**/*.tsx',
);
const slots = import.meta.glob<Module>(
    '../../../modules/*/*/resources/js/slots/*.tsx',
);

const SEGMENT = /^[a-z0-9-]+$/;
const FILE = /^[A-Za-z0-9_\-/.]+\.(js|css)$/;

installModuleSdk();

export function isModulePage(name: string): boolean {
    return name.startsWith('module/');
}

/** The page path inside the module, e.g. "storefront/reviews" (used for layouts). */
export function modulePagePath(name: string): string {
    return name.split('/').slice(3).join('/');
}

function assetUrl(vendor: string, pkg: string, file: string): string {
    return `/module-assets/${vendor}/${pkg}/${file}`;
}

const manifests = new Map<string, Promise<PrebuiltManifest | null>>();

function prebuilt(
    vendor: string,
    pkg: string,
): Promise<PrebuiltManifest | null> {
    const key = `${vendor}/${pkg}`;
    let manifest = manifests.get(key);

    if (!manifest) {
        manifest = fetch(assetUrl(vendor, pkg, 'module-ui.json'), {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        })
            .then((response) =>
                response.ok
                    ? (response.json() as Promise<PrebuiltManifest>)
                    : null,
            )
            .catch(() => null);
        manifests.set(key, manifest);
    }

    return manifest;
}

const stylesheets = new Set<string>();

function addStyles(vendor: string, pkg: string, css: string[]): void {
    for (const file of css) {
        const href = assetUrl(vendor, pkg, file);

        if (!FILE.test(file) || file.includes('..') || stylesheets.has(href)) {
            continue;
        }

        stylesheets.add(href);
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = href;
        document.head.appendChild(link);
    }
}

async function loadPrebuilt(
    vendor: string,
    pkg: string,
    kind: 'pages' | 'slots',
    entry: string,
): Promise<Module | null> {
    const manifest = await prebuilt(vendor, pkg);
    const file = manifest?.[kind]?.[entry];

    if (!manifest || !file || !FILE.test(file) || file.includes('..')) {
        return null;
    }

    addStyles(vendor, pkg, manifest.css ?? []);

    return (await import(
        /* @vite-ignore */ assetUrl(vendor, pkg, file)
    )) as Module;
}

export async function resolveModulePage(name: string): Promise<Module> {
    const [, vendor, pkg, ...rest] = name.split('/');

    if (!SEGMENT.test(vendor ?? '') || !SEGMENT.test(pkg ?? '')) {
        throw new Error(`Invalid module page name: ${name}`);
    }

    const page = rest.join('/');
    const bundled =
        pages[
            `../../../modules/${vendor}/${pkg}/resources/js/pages/${page}.tsx`
        ];

    if (bundled) {
        return bundled();
    }

    try {
        const loaded = await loadPrebuilt(vendor, pkg, 'pages', page);

        if (loaded) {
            return loaded;
        }
    } catch (error) {
        console.error(`Module page ${name} failed to load`, error);
    }

    return moduleUnavailable(name);
}

export function slotLoader(component: string): (() => Promise<Module>) | null {
    const [vendor, pkg, file] = component.split('/');

    if (
        !SEGMENT.test(vendor ?? '') ||
        !SEGMENT.test(pkg ?? '') ||
        !/^[A-Z][A-Za-z0-9]*$/.test(file ?? '')
    ) {
        return null;
    }

    const bundled =
        slots[
            `../../../modules/${vendor}/${pkg}/resources/js/slots/${file}.tsx`
        ];

    if (bundled) {
        return bundled;
    }

    // Prebuilt slot, or nothing (the slot then renders empty).
    return async () =>
        (await loadPrebuilt(vendor, pkg, 'slots', file)) ?? {
            default: () => null,
        };
}
