import { useState } from "react";
import { ArrowLeft, ArrowRight } from "lucide-react";
import type { CSSProperties } from "react";
import { StoreMenuLink as Link } from "@/components/storefront/store-menu-link";
import { blockAttributes, type ThemeSectionProps } from "@/components/storefront/theme-sections";
import type { ListingProps } from "@/pages/storefront/listing";
import { str } from "../utils";
export default function CollectionBanner({
    section,
    page,
}: ThemeSectionProps<Record<string, unknown>, ListingProps>) {
    const [index, setIndex] = useState(0);
    const s = section.settings;
    const blocks = section.blocks;
    const visible =
        blocks.length > 2
            ? [blocks[index % blocks.length], blocks[(index + 1) % blocks.length]]
            : blocks;
    return (
        <section
            className="sa-collection-campaign"
            style={{ "--sa-shade": Number(s.shade) / 100 } as CSSProperties}
        >
            {str(s.image) && (
                <img
                    className="sa-collection-landscape"
                    src={str(s.image)}
                    alt=""
                    fetchPriority="high"
                />
            )}
            <div className="sa-collection-campaign-inner">
                <div className="sa-collection-intro">
                    <h1>{str(s.heading) || page?.title + " collection"}</h1>
                    <p>{str(s.body)}</p>
                    {str(s.button_label) &&
                        (str(s.link) ? (
                            <Link className="sa-campaign-shop" href={str(s.link)}>
                                {str(s.button_label)}
                            </Link>
                        ) : (
                            <a className="sa-campaign-shop" href="#collection-products">
                                {str(s.button_label)}
                            </a>
                        ))}
                    {blocks.length > 2 && (
                        <div className="sa-campaign-arrows">
                            <button
                                type="button"
                                aria-label="Previous featured collections"
                                onClick={() =>
                                    setIndex((index + blocks.length - 1) % blocks.length)
                                }
                            >
                                <ArrowLeft size={20} />
                            </button>
                            <button
                                type="button"
                                aria-label="Next featured collections"
                                onClick={() => setIndex((index + 1) % blocks.length)}
                            >
                                <ArrowRight size={20} />
                            </button>
                        </div>
                    )}
                </div>
                <div className="sa-collection-tiles">
                    {visible.map((b) => (
                        <Link
                            key={b.id}
                            {...blockAttributes(b)}
                            href={str(b.settings.link) || "/search"}
                            className="sa-collection-tile"
                        >
                            {str(b.settings.image) && (
                                <img src={str(b.settings.image)} alt={str(b.settings.title)} />
                            )}
                            <div>
                                <h2>{str(b.settings.title)}</h2>
                                <p>{str(b.settings.subtitle)}</p>
                            </div>
                        </Link>
                    ))}
                </div>
            </div>
        </section>
    );
}
