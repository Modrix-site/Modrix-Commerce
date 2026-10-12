import { Component, lazy, Suspense, useMemo } from 'react';
import type { ErrorInfo, ReactNode } from 'react';
import { slotLoader } from '@/lib/modules';

export type SlotItem = {
    key: string;
    component: string;
    props: Record<string, unknown>;
};

/**
 * Renders the module components the server placed in a storefront slot or
 * on the dashboard. A component that is missing (assets not rebuilt) or that
 * crashes is skipped; the page around it keeps working.
 */
export function ExtensionSlot({
    items,
    className,
}: {
    items: SlotItem[] | undefined;
    className?: string;
}) {
    if (!items || items.length === 0) {
        return null;
    }

    return (
        <div className={className}>
            {items.map((item) => (
                <SlotBoundary key={item.key} component={item.component}>
                    <SlotComponent item={item} />
                </SlotBoundary>
            ))}
        </div>
    );
}

export function SlotComponent({ item }: { item: SlotItem }) {
    const Loaded = useMemo(() => {
        const loader = slotLoader(item.component);

        return loader ? lazy(loader) : null;
    }, [item.component]);

    if (!Loaded) {
        return null;
    }

    return (
        <Suspense fallback={null}>
            <Loaded {...item.props} />
        </Suspense>
    );
}

class SlotBoundary extends Component<
    { component: string; children: ReactNode },
    { failed: boolean }
> {
    state = { failed: false };

    static getDerivedStateFromError(): { failed: boolean } {
        return { failed: true };
    }

    componentDidCatch(error: Error, info: ErrorInfo): void {
        console.error(
            `Module component ${this.props.component} failed`,
            error,
            info.componentStack,
        );
    }

    render(): ReactNode {
        return this.state.failed ? null : this.props.children;
    }
}
