import { usePlaceItems, PlaceSlot } from "@/components/extensions/place-slot";
import type { ThemeSectionProps } from "@/components/storefront/theme-sections";
import { str } from "../utils";
export default function Newsletter({ section }: ThemeSectionProps) {
    const items = usePlaceItems("storefront.footer");
    if (items.length === 0) return null;
    return (
        <section className="sa-newsletter sa-frame">
            <h2>{str(section.settings.heading)}</h2>
            <p>{str(section.settings.body)}</p>
            <PlaceSlot place="storefront.footer" />
        </section>
    );
}
