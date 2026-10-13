import { usePage } from '@inertiajs/react';

export type Replacements = Record<string, string | number>;

/**
 * Translates a storefront text. Keys are the English texts (the source
 * catalogue in lang/storefront/en.json); missing translations show English.
 * Placeholders look like `:name`.
 */
export function translate(
    messages: Record<string, string>,
    key: string,
    replace: Replacements = {},
): string {
    let text = messages[key] ?? key;

    for (const [name, value] of Object.entries(replace)) {
        text = text.split(`:${name}`).join(String(value));
    }

    return text;
}

/**
 * Marks a text for the catalogue where it is translated later (static
 * page layout titles, translated by the layout). Returns it unchanged.
 */
export function msg(key: string): string {
    return key;
}

/**
 * `const t = useT(); t('Add to cart')`. Use string literals so the
 * catalogue test can find every text.
 */
export function useT(): (key: string, replace?: Replacements) => string {
    const { i18n } = usePage().props;
    const messages = i18n?.messages ?? {};

    return (key, replace) => translate(messages, key, replace);
}
