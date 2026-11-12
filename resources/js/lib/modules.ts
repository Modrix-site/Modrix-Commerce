import type { ComponentType } from 'react';

/**
 * Module UI is compiled with the application: bundled modules ship React
 * pages and slot components inside their package, found here by path.
 * Adding a module with UI therefore needs an asset rebuild (`npm run build`).
 *
 * Page names:      module/<vendor>/<name>/<page>  → modules/<vendor>/<name>/resources/js/pages/<page>.tsx
 * Slot components: <vendor>/<name>/<Component>     → modules/<vendor>/<name>/resources/js/slots/<Component>.tsx
 */
type Module = { default: ComponentType<Record<string, unknown>> };

const pages = import.meta.glob<Module>(
    '../../../modules/*/*/resources/js/pages/**/*.tsx',
);
const slots = import.meta.glob<Module>(
    '../../../modules/*/*/resources/js/slots/*.tsx',
);

const SEGMENT = /^[a-z0-9-]+$/;

export function isModulePage(name: string): boolean {
    return name.startsWith('module/');
}

/** The page path inside the module, e.g. "storefront/reviews" (used for layouts). */
export function modulePagePath(name: string): string {
    return name.split('/').slice(3).join('/');
}

export function resolveModulePage(name: string): Promise<Module> {
    const [, vendor, pkg, ...rest] = name.split('/');

    if (!SEGMENT.test(vendor ?? '') || !SEGMENT.test(pkg ?? '')) {
        throw new Error(`Invalid module page name: ${name}`);
    }

    const loader =
        pages[
            `../../../modules/${vendor}/${pkg}/resources/js/pages/${rest.join('/')}.tsx`
        ];

    if (!loader) {
        throw new Error(
            `Module page not found: ${name}. Rebuild the assets after adding a module.`,
        );
    }

    return loader();
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

    return (
        slots[
            `../../../modules/${vendor}/${pkg}/resources/js/slots/${file}.tsx`
        ] ?? null
    );
}
