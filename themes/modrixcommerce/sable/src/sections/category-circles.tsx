import type { CSSProperties } from "react";
import { StoreMenuLink as Link } from "@/components/storefront/store-menu-link";
import { blockAttributes, type ThemeSectionProps } from "@/components/storefront/theme-sections";
import { str } from "../utils";
export default function Circles({ section }: ThemeSectionProps) {
    return (
        <section
            className="sa-circles sa-frame"
            style={{ "--sa-circle": str(section.settings.size) + "px" } as CSSProperties}
        >
            {str(section.settings.heading) && <h2>{str(section.settings.heading)}</h2>}
            <div>
                {section.blocks.map((b) => (
                    <Link
                        key={b.id}
                        href={str(b.settings.link) || "/search"}
                        {...blockAttributes(b)}
                    >
                        {str(b.settings.image) && (
                            <img src={str(b.settings.image)} alt="" loading="lazy" />
                        )}
                        <span>{str(b.settings.label)}</span>
                    </Link>
                ))}
            </div>
        </section>
    );
}
