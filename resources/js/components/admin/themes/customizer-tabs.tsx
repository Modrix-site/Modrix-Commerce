import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { KeyboardEvent } from 'react';
import { cn } from '@/lib/utils';
import type { ThemeField } from '@/types';

/** Customizer tabs, in order (keys match App\Core\Themes\CustomizerTabs). */
export const CUSTOMIZER_TABS = [
    { key: 'general', label: 'General' },
    { key: 'header', label: 'Header' },
    { key: 'home', label: 'Homepage' },
    { key: 'catalog', label: 'Catalog' },
    { key: 'product', label: 'Product' },
    { key: 'cart', label: 'Cart' },
    { key: 'checkout', label: 'Checkout' },
    { key: 'footer', label: 'Footer' },
] as const;

export type CustomizerTab = (typeof CUSTOMIZER_TABS)[number]['key'];

const STYLE_GROUP = 'Fonts and buttons';

/** id of the element holding the active tab's content. */
export const CUSTOMIZER_PANEL_ID = 'customizer-panel';

function isTab(value: string | null): value is CustomizerTab {
    return CUSTOMIZER_TABS.some((t) => t.key === value);
}

export function tabOf(field: ThemeField): CustomizerTab {
    const tab = field.tab ?? 'general';

    return isTab(tab) ? tab : 'general';
}

/**
 * The tab's setting groups, in their usual order; on the page tabs the
 * "Fonts and buttons" group comes first.
 */
export function groupsForTab(
    fields: ThemeField[],
    tab: CustomizerTab,
): string[] {
    const groups = [
        ...new Set(
            fields
                .filter((f) => tabOf(f) === tab)
                .map((f) => f.group ?? 'Other'),
        ),
    ];

    return tab === 'general'
        ? groups
        : [
              ...groups.filter((g) => g === STYLE_GROUP),
              ...groups.filter((g) => g !== STYLE_GROUP),
          ];
}

/** The active tab, kept in the address (?tab=) so a save stays on it. */
export function useCustomizerTab(): [
    CustomizerTab,
    (tab: CustomizerTab) => void,
] {
    const [tab, setTab] = useState<CustomizerTab>(() => {
        const fromUrl =
            typeof window === 'undefined'
                ? null
                : new URLSearchParams(window.location.search).get('tab');

        return isTab(fromUrl) ? fromUrl : 'general';
    });

    useEffect(() => {
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        window.history.replaceState(window.history.state, '', url);
    }, [tab]);

    return [tab, setTab];
}

/**
 * Tab bar: hides tabs with nothing in them (Homepage always has its
 * sections), shows how many settings are customised and marks tabs with
 * errors from the last save.
 */
export function CustomizerTabs({
    fields,
    overridden,
    value,
    onChange,
}: {
    fields: ThemeField[];
    overridden: string[];
    value: CustomizerTab;
    onChange: (tab: CustomizerTab) => void;
}) {
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const errorKeys = Object.keys(errors ?? {}).map((k) =>
        k.replace(/^design\./, ''),
    );

    const tabs = CUSTOMIZER_TABS.map((t) => {
        const inTab = fields.filter((f) => tabOf(f) === t.key);

        return {
            ...t,
            visible:
                t.key === 'general' || t.key === 'home' || inTab.length > 0,
            customised: inTab.filter((f) => overridden.includes(f.key)).length,
            hasError:
                inTab.some((f) => errorKeys.includes(f.key)) ||
                // Homepage section errors are keyed by section ("0.heading").
                (t.key === 'home' &&
                    errorKeys.some((k) => /^(sections\.)?\d+\./.test(k))),
        };
    }).filter((t) => t.visible);

    // Arrow keys, Home and End move between tabs (WAI-ARIA tabs pattern).
    const onKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
        const index = tabs.findIndex((t) => t.key === value);
        const last = tabs.length - 1;
        const next =
            event.key === 'ArrowRight'
                ? (index + 1) % tabs.length
                : event.key === 'ArrowLeft'
                  ? (index - 1 + tabs.length) % tabs.length
                  : event.key === 'Home'
                    ? 0
                    : event.key === 'End'
                      ? last
                      : null;

        if (next === null) {
            return;
        }

        event.preventDefault();
        onChange(tabs[next].key);
        document.getElementById(`customizer-tab-${tabs[next].key}`)?.focus();
    };

    return (
        <div
            role="tablist"
            onKeyDown={onKeyDown}
            aria-label="Customizer sections"
            className="sticky top-0 z-10 -mx-1 flex gap-1 overflow-x-auto border-b bg-background px-1"
        >
            {tabs.map((t) => (
                <button
                    key={t.key}
                    type="button"
                    role="tab"
                    id={`customizer-tab-${t.key}`}
                    aria-selected={value === t.key}
                    aria-controls={CUSTOMIZER_PANEL_ID}
                    tabIndex={value === t.key ? 0 : -1}
                    onClick={() => onChange(t.key)}
                    className={cn(
                        '-mb-px flex items-center gap-1.5 border-b-2 px-3 py-2 text-sm font-medium whitespace-nowrap',
                        value === t.key
                            ? 'border-primary text-foreground'
                            : 'border-transparent text-muted-foreground hover:text-foreground',
                    )}
                >
                    {t.label}
                    {t.customised > 0 && (
                        <span className="rounded-full bg-muted px-1.5 text-xs tabular-nums">
                            {t.customised}
                        </span>
                    )}
                    {t.hasError && (
                        <span
                            className="size-2 rounded-full bg-destructive"
                            aria-label="Has errors"
                        />
                    )}
                </button>
            ))}
        </div>
    );
}
