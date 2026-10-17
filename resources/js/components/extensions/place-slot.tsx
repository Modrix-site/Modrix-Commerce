import { usePage } from '@inertiajs/react';
import { ExtensionSlot } from '@/components/extensions/extension-slot';
import type { SlotItem } from '@/components/extensions/extension-slot';
import { cn } from '@/lib/utils';

type Places = Record<string, SlotItem[]> | undefined;

/**
 * The extension blocks the merchant placed here (customizer → Extension
 * blocks). Page places come with the page (`placeSlots`); the header and
 * product-card places are shared by every page (`storefront.placeSlots`).
 * Renders nothing when no block is placed here.
 */
export function PlaceSlot({
    place,
    className,
    extraProps,
}: {
    place: string;
    className?: string;
    /** Live values for every block here, e.g. the card's product. */
    extraProps?: Record<string, unknown>;
}) {
    const props = usePage<{
        placeSlots?: Places;
        storefront?: { placeSlots?: Places };
    }>().props;
    const items =
        props.placeSlots?.[place] ?? props.storefront?.placeSlots?.[place];

    if (!items || items.length === 0) {
        return null;
    }

    // Live values (e.g. the card's product) are added to each block's props.
    const withExtra = extraProps
        ? items.map((item) => ({
              ...item,
              props: { ...item.props, ...extraProps },
          }))
        : items;

    return (
        <ExtensionSlot
            items={withExtra}
            className={cn(
                'store-place grid gap-3',
                `store-place-${place.replace('.', '-')}`,
                className,
            )}
        />
    );
}
