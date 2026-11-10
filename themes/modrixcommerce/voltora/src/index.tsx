import { Head } from "@inertiajs/react";
import { useState } from "react";
import { ChevronRight } from "lucide-react";
import { StoreMenuLink as Link } from "@/components/storefront/footer-menus";
import { ExtensionSlot } from "@/components/extensions/extension-slot";
import { HomeSections } from "@/components/storefront/home-sections";
import type { HomeProps } from "@/pages/storefront/home";
import { useT } from "@/lib/i18n";
import { VoltoraRail, useVoltora } from "./components";
import "./style.css";

export default function VoltoraIndex({ sections, slots }: HomeProps) {
    const { storefront, text, show } = useVoltora();
    const t = useT();
    const [slide, setSlide] = useState(1);
    const [tab, setTab] = useState(1);
    const rows = sections.filter(
        (section) => section.type === "featured_products",
    );
    // Avoid printing identical content twice when an extension offers the same widget in the home and footer slots.
    const homeSlots = slots.filter(
        (item) =>
            !storefront?.footerSlots?.some(
                (footer) =>
                    footer.component === item.component &&
                    JSON.stringify(footer.props) === JSON.stringify(item.props),
            ),
    );
    return (
        <>
            <Head
                title={
                    storefront?.seoTitle || storefront?.storeName || "Voltora"
                }
            />
            <div className="voltora-wrap voltora-home">
                {show("show_hero") && (
                    <section
                        aria-label="Featured collections"
                        className="voltora-hero"
                        style={{
                            height: `${text("hero_height", "310")}px`,
                            backgroundImage: `linear-gradient(#132129${Math.round(
                                Number(text("hero_overlay", "40")) * 2.55,
                            )
                                .toString(16)
                                .padStart(
                                    2,
                                    "0",
                                )},#13212966),url("${text(`hero_${slide}_image`)}")`,
                        }}
                    >
                        <div>
                            <small>{text(`hero_${slide}_eyebrow`)}</small>
                            <h1>{text(`hero_${slide}_title`)}</h1>
                            <p>{text(`hero_${slide}_text`)}</p>
                            <Link
                                className="voltora-primary"
                                href={text(`hero_${slide}_link`, "/search")}
                            >
                                {text(`hero_${slide}_button`)}
                            </Link>
                        </div>
                        <button
                            className="voltora-next"
                            aria-label="Next hero slide"
                            onClick={() => setSlide(slide === 1 ? 2 : 1)}
                        >
                            <ChevronRight size={20} />
                        </button>
                    </section>
                )}
                {show("show_hero") && (
                    <div className="voltora-dots">
                        {[1, 2].map((i) => (
                            <button
                                key={i}
                                aria-label={`Show hero slide ${i}`}
                                aria-pressed={slide === i}
                                onClick={() => setSlide(i)}
                            >
                                {slide === i ? "━" : "◦"}
                            </button>
                        ))}
                    </div>
                )}
                {show("show_benefits") && (
                    <div className="voltora-benefits">
                        {[1, 2, 3, 4].map((i) => (
                            <span key={i}>✦ {text(`benefit_${i}`)}</span>
                        ))}
                    </div>
                )}
                {show("show_promotions") && (
                    <div className="voltora-tiles">
                        {[1, 2, 3, 4].map((i) => (
                            <Link
                                key={i}
                                href={text(`promo_${i}_link`, "/search")}
                                className="voltora-tile"
                            >
                                <img
                                    src={text(`promo_${i}_image`)}
                                    alt=""
                                    loading="lazy"
                                />
                                <div>
                                    <h2>{text(`promo_${i}_title`)}</h2>
                                    <small>
                                        {t("Discover the collection")} →
                                    </small>
                                </div>
                            </Link>
                        ))}
                    </div>
                )}
                {show("show_spotlight") && (
                    <section className="voltora-spotlight">
                        <h2>{text("spotlight_heading")}</h2>
                        <div
                            className="voltora-tabs"
                            role="tablist"
                            aria-label={text("spotlight_heading")}
                        >
                            {[1, 2, 3].map((i) => (
                                <button
                                    key={i}
                                    id={`voltora-tab-${i}`}
                                    role="tab"
                                    aria-selected={tab === i}
                                    aria-controls="voltora-spot-panel"
                                    onClick={() => setTab(i)}
                                >
                                    {text(`spot_${i}_label`)}
                                </button>
                            ))}
                        </div>
                        <div
                            className="voltora-split"
                            id="voltora-spot-panel"
                            role="tabpanel"
                            aria-labelledby={`voltora-tab-${tab}`}
                        >
                            <img
                                src={text(`spot_${tab}_image`)}
                                alt={text(`spot_${tab}_title`)}
                                loading="lazy"
                            />
                            <div>
                                <small>{t("MAKE ROOM FOR MORE")}</small>
                                <h2>{text(`spot_${tab}_title`)}</h2>
                                <p>{text(`spot_${tab}_text`)}</p>
                                <Link
                                    className="voltora-primary"
                                    href={text(`spot_${tab}_link`, "/search")}
                                >
                                    {text(`spot_${tab}_button`)}
                                </Link>
                            </div>
                        </div>
                    </section>
                )}
                {show("show_finance") && (
                    <Link
                        href={text("finance_link", "/cart")}
                        className="voltora-finance"
                    >
                        <strong>{text("finance_heading")}</strong>{" "}
                        {text("finance_text")}
                    </Link>
                )}
                {rows[0] && (
                    <VoltoraRail
                        title={String(rows[0].settings.title)}
                        products={rows[0].data.products ?? []}
                    />
                )}
                {show("show_lifestyle") && (
                    <section>
                        <h2>{text("lifestyle_heading")}</h2>
                        <div className="voltora-tiles voltora-lifestyle">
                            {[1, 2, 3, 4, 5, 6].map((i) => (
                                <Link
                                    className="voltora-tile"
                                    key={i}
                                    href={text(`life_${i}_link`, "/search")}
                                >
                                    <img
                                        src={text(`life_${i}_image`)}
                                        alt=""
                                        loading="lazy"
                                    />
                                    <div>
                                        <h3>{text(`life_${i}_title`)}</h3>
                                        <p>{t("Shop now")} →</p>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    </section>
                )}
                {rows.slice(1).map((section, i) => (
                    <VoltoraRail
                        key={i}
                        title={String(section.settings.title)}
                        products={section.data.products ?? []}
                    />
                ))}
                {show("show_brands") && (
                    <div className="voltora-brands">
                        {text("brand_names")
                            .split("|")
                            .filter(Boolean)
                            .map((name) => (
                                <Link
                                    key={name}
                                    href={`/search?q=${encodeURIComponent(name.trim())}`}
                                >
                                    {name.trim()}
                                </Link>
                            ))}
                    </div>
                )}
                <HomeSections
                    sections={sections.filter(
                        (section) => section.type !== "featured_products",
                    )}
                />
                <ExtensionSlot
                    items={homeSlots}
                    className="voltora-home-extensions"
                />
            </div>
        </>
    );
}
