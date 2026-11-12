import { router, usePage } from '@inertiajs/react';
import { Component, useEffect } from 'react';
import type { ComponentType, ErrorInfo, ReactNode } from 'react';

/** A section as the server renders it: settings already resolved for display. */
export type ThemeSectionData<S = Record<string, unknown>> = {
    id: string;
    type: string;
    settings: S;
    blocks: ThemeBlockData[];
};

export type ThemeBlockData<S = Record<string, unknown>> = {
    id: string;
    type: string;
    settings: S;
};

export type ThemeSectionsPayload = {
    template: string | null;
    header: ThemeSectionData[];
    footer: ThemeSectionData[];
    main: ThemeSectionData[];
};

/**
 * Props of a theme section component (`src/sections/<type>.tsx`). `page`
 * carries the page's own props (product, listing, cart…) on templates that
 * have them.
 */
export type ThemeSectionProps<S = Record<string, unknown>, P = unknown> = {
    section: ThemeSectionData<S>;
    page: P;
};

type SectionModule = {
    default: ComponentType<ThemeSectionProps<never, never>>;
};

// Eager, like theme headers and home pages: sections paint with the first render.
const modules = import.meta.glob<SectionModule>(
    '../../../../themes/*/*/src/sections/*.tsx',
    { eager: true },
);

function sectionComponent(
    theme: string,
    type: string,
): ComponentType<ThemeSectionProps<never, never>> | undefined {
    return modules[`../../../../themes/${theme}/src/sections/${type}.tsx`]
        ?.default;
}

/** Attributes a theme spreads on a block's root element so the editor can select it. */
export function blockAttributes(block: ThemeBlockData): Record<string, string> {
    return { 'data-theme-block': block.id };
}

export function useThemeSections(): ThemeSectionsPayload | null {
    const { storefront } = usePage().props;

    return (storefront?.themeSections as ThemeSectionsPayload | null) ?? null;
}

/**
 * Renders a list of sections with the active theme's components. In the
 * theme editor every section is wrapped so it can be outlined and selected.
 */
export function ThemeSectionList({
    sections,
    area,
    page,
}: {
    sections: ThemeSectionData[];
    area: 'header' | 'footer' | 'main';
    page?: unknown;
}) {
    const { storefront } = usePage().props;
    const theme = storefront?.theme?.name ?? '';

    return (
        <>
            {sections.map((section) => {
                const Section = sectionComponent(theme, section.type);

                return (
                    <div
                        key={section.id}
                        data-theme-section={section.id}
                        data-theme-area={area}
                        className="theme-section"
                    >
                        {Section ? (
                            <SectionBoundary type={section.type}>
                                <Section
                                    section={section as never}
                                    page={page as never}
                                />
                            </SectionBoundary>
                        ) : null}
                    </div>
                );
            })}
        </>
    );
}

/** The page template, when the active theme is built from sections. */
export function ThemeTemplate({ page }: { page?: unknown }) {
    const sections = useThemeSections();

    return sections ? (
        <ThemeSectionList sections={sections.main} area="main" page={page} />
    ) : null;
}

class SectionBoundary extends Component<
    { type: string; children: ReactNode },
    { failed: boolean }
> {
    state = { failed: false };

    static getDerivedStateFromError(): { failed: boolean } {
        return { failed: true };
    }

    componentDidCatch(error: Error, info: ErrorInfo): void {
        console.error(
            `Theme section ${this.props.type} failed`,
            error,
            info.componentStack,
        );
    }

    render(): ReactNode {
        return this.state.failed ? null : this.props.children;
    }
}

/**
 * Inside the theme editor's preview frame: keeps every visit in editor mode,
 * reloads section data when the editor changes something, outlines the
 * section under the pointer and tells the editor what was clicked.
 * Messages are accepted from, and sent to, this origin only.
 */
export function ThemeEditorBridge() {
    const { storefront } = usePage().props;
    const active = storefront?.themeEditor === true && window.parent !== window;

    useEffect(() => {
        if (!active) {
            return;
        }

        const origin = window.location.origin;
        const post = (message: Record<string, unknown>) =>
            window.parent.postMessage(
                { source: 'theme-editor-preview', ...message },
                origin,
            );

        const removeBefore = router.on('before', (event) => {
            const url = event.detail.visit.url;

            if (
                url.origin === origin &&
                url.searchParams.get('_editor') !== '1'
            ) {
                url.searchParams.set('_editor', '1');
            }
        });
        const removeNavigate = router.on('navigate', () =>
            post({
                type: 'navigated',
                url: window.location.pathname + window.location.search,
            }),
        );

        let outlined: HTMLElement | null = null;
        const outline = (element: HTMLElement | null) => {
            outlined?.removeAttribute('data-theme-editor-hover');
            outlined = element;
            outlined?.setAttribute('data-theme-editor-hover', '');
        };
        const onOver = (event: MouseEvent) =>
            outline(
                (event.target as HTMLElement).closest<HTMLElement>(
                    '[data-theme-section]',
                ),
            );
        const onClick = (event: MouseEvent) => {
            const target = event.target as HTMLElement;
            const section = target.closest<HTMLElement>('[data-theme-section]');

            if (!section) {
                return;
            }

            // Selecting in the editor, not following links or submitting forms.
            if (event.altKey === false) {
                event.preventDefault();
                event.stopPropagation();
            }

            post({
                type: 'select',
                area: section.dataset.themeArea,
                section: section.dataset.themeSection,
                block:
                    target.closest<HTMLElement>('[data-theme-block]')?.dataset
                        .themeBlock ?? null,
            });
        };
        const onMessage = (event: MessageEvent) => {
            const data = event.data as {
                source?: string;
                type?: string;
                section?: string;
                block?: string | null;
            };

            if (event.origin !== origin || data?.source !== 'theme-editor') {
                return;
            }

            if (data.type === 'refresh') {
                router.reload({ only: ['storefront'] });
            } else if (data.type === 'highlight') {
                document
                    .querySelectorAll('[data-theme-editor-selected]')
                    .forEach((el) =>
                        el.removeAttribute('data-theme-editor-selected'),
                    );
                const element = data.section
                    ? document.querySelector<HTMLElement>(
                          `[data-theme-section="${CSS.escape(data.section)}"]`,
                      )
                    : null;
                const block =
                    data.block && element
                        ? element.querySelector<HTMLElement>(
                              `[data-theme-block="${CSS.escape(data.block)}"]`,
                          )
                        : null;
                (block ?? element)?.setAttribute(
                    'data-theme-editor-selected',
                    '',
                );
                (block ?? element)?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest',
                });
            }
        };

        document.addEventListener('mouseover', onOver);
        document.addEventListener('click', onClick, true);
        window.addEventListener('message', onMessage);
        post({
            type: 'ready',
            url: window.location.pathname + window.location.search,
        });

        return () => {
            removeBefore();
            removeNavigate();
            document.removeEventListener('mouseover', onOver);
            document.removeEventListener('click', onClick, true);
            window.removeEventListener('message', onMessage);
        };
    }, [active]);

    return active ? (
        <style>{`
            [data-theme-section][data-theme-editor-hover] { outline: 2px solid #2c6ecb; outline-offset: -2px; cursor: pointer; }
            [data-theme-editor-selected] { outline: 2px solid #2c6ecb; outline-offset: -2px; }
        `}</style>
    ) : null;
}
