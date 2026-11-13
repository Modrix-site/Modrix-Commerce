/**
 * Stylesheets of prebuilt modules (Modrix Cloud installs).
 *
 * A module's CSS carries its own copy of every Tailwind class it uses. Added
 * after the store's CSS, a plain duplicate such as `.hidden` would beat the
 * store's responsive classes (`md:flex`) and hide parts of the admin, like
 * the sidebar. So rules the store already has are dropped and only the
 * module's own classes are added, after the store's.
 */

type Grouping = CSSGroupingRule & { name?: string; conditionText?: string };

function context(rule: CSSRule): string | null {
    if (rule instanceof CSSStyleRule) {
        return null;
    }

    const group = rule as Grouping;

    if (!('cssRules' in group)) {
        return null;
    }

    return `${rule.constructor.name}(${group.name ?? group.conditionText ?? ''})`;
}

/** Keys of every style rule, e.g. "CSSLayerBlockRule(utilities)>CSSMediaRule(width >= 48rem)>.md\:flex". */
function collect(rules: CSSRuleList, prefix: string, keys: Set<string>): void {
    for (const rule of Array.from(rules)) {
        if (rule instanceof CSSStyleRule) {
            keys.add(`${prefix}>${rule.selectorText}`);
            continue;
        }

        const inner = context(rule);

        if (inner !== null) {
            collect(
                (rule as CSSGroupingRule).cssRules,
                `${prefix}>${inner}`,
                keys,
            );
        }
    }
}

/** Removes rules the store already defines; returns true when the group ends up empty. */
function prune(
    group: CSSStyleSheet | CSSGroupingRule,
    prefix: string,
    store: Set<string>,
): boolean {
    for (let i = group.cssRules.length - 1; i >= 0; i--) {
        const rule = group.cssRules[i];
        let drop = false;

        if (rule instanceof CSSStyleRule) {
            drop = store.has(`${prefix}>${rule.selectorText}`);
        } else {
            const inner = context(rule);
            drop =
                inner !== null &&
                prune(rule as CSSGroupingRule, `${prefix}>${inner}`, store);
        }

        if (drop) {
            group.deleteRule(i);
        }
    }

    return group.cssRules.length === 0;
}

let storeKeys: Set<string> | null = null;

async function storeRules(): Promise<Set<string>> {
    if (storeKeys) {
        return storeKeys;
    }

    const keys = new Set<string>();

    for (const sheet of Array.from(document.styleSheets)) {
        try {
            collect(sheet.cssRules, '', keys);
        } catch {
            // A sheet from another origin (a CDN, the Vite dev server) cannot
            // be read directly: fetch and parse its text instead.
            if (!sheet.href) {
                continue;
            }

            try {
                const copy = new CSSStyleSheet();
                const response = await fetch(sheet.href, {
                    headers: { Accept: 'text/css' },
                });
                copy.replaceSync(await response.text());
                collect(copy.cssRules, '', keys);
            } catch {
                // Not readable at all: it cannot be compared.
            }
        }
    }

    // Only cache once the store's CSS is in (a page can resolve before it loads).
    if (keys.size > 0) {
        storeKeys = keys;
    }

    return keys;
}

/** Adds a module stylesheet without overriding the store's own classes. */
export async function addModuleStylesheet(href: string): Promise<void> {
    try {
        const response = await fetch(href, { credentials: 'same-origin' });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const sheet = new CSSStyleSheet();
        sheet.replaceSync(await response.text());
        prune(sheet, '', await storeRules());
        document.adoptedStyleSheets = [...document.adoptedStyleSheets, sheet];
    } catch {
        // Older browsers or an unreadable file: add it as it is.
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = href;
        document.head.appendChild(link);
    }
}
