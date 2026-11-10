import { useEffect, useRef, useState } from "react";
import { router, Link } from "@inertiajs/react";
import { ChevronDown, X } from "lucide-react";
import { PlaceSlot } from "@/components/extensions/place-slot";
import type { ThemeSectionProps } from "@/components/storefront/theme-sections";
import type { ListingProps } from "@/pages/storefront/listing";
import { SableCard } from "../card";
import { str } from "../utils";
type Props = ThemeSectionProps<Record<string, unknown>, ListingProps>;
export default function Collection({ section, page }: Props) {
    return page?.products ? (
        <Catalog
            key={JSON.stringify([page.query, page.sort, page.filters])}
            section={section}
            page={page}
        />
    ) : null;
}
function Catalog({ section, page }: Props) {
    const { title, description, products, query, sort, filters, filterBrands, filterOptions } =
        page;
    const s = section.settings;
    const [panel, setPanel] = useState<string | null>(null);
    const root = useRef<HTMLDivElement>(null);
    useEffect(() => {
        if (!panel) return;
        const close = (e: MouseEvent) => {
            if (!root.current?.contains(e.target as Node)) setPanel(null);
        };
        const escape = (e: KeyboardEvent) => {
            if (e.key === "Escape") setPanel(null);
        };
        document.addEventListener("click", close);
        document.addEventListener("keydown", escape);
        return () => {
            document.removeEventListener("click", close);
            document.removeEventListener("keydown", escape);
        };
    }, [panel]);
    const visit = (updates: Record<string, string | null>) => {
        const u = new URL(window.location.href);
        for (const [k, v] of Object.entries(updates)) {
            if (v === null || v === "") u.searchParams.delete(k);
            else u.searchParams.set(k, v);
        }
        u.searchParams.delete("page");
        setPanel(null);
        router.get(u.pathname + u.search, {}, { preserveScroll: true });
    };
    const clear = () => {
        const u = new URL(window.location.href);
        for (const k of new Set(u.searchParams.keys()))
            if (!["q", "sort", "_editor", "currency", "lang"].includes(k)) u.searchParams.delete(k);
        setPanel(null);
        router.get(u.pathname + u.search, {}, { preserveScroll: true });
    };
    const filtered = Object.values(filters ?? {}).some((v) =>
        Array.isArray(v)
            ? v.length > 0
            : v !== undefined && v !== null && v !== "" && v !== false && v !== 0 && v !== "0",
    );
    return (
        <section className="sa-commerce-frame sa-catalog" id="collection-products">
            {s.show_heading !== false && (
                <div className="sa-catalog-heading">
                    <h1>
                        {query || page.breadcrumbs.length ? title : str(s.shop_heading) || title}
                    </h1>
                    {description && <p>{description}</p>}
                </div>
            )}
            <div className="sa-catalog-toolbar" ref={root}>
                <div className="sa-filter-controls">
                    <span>{str(s.filter_label)}</span>
                    <div className="sa-filter-control">
                        <button
                            type="button"
                            aria-expanded={panel === "availability"}
                            aria-controls="sa-availability-panel"
                            onClick={() =>
                                setPanel(panel === "availability" ? null : "availability")
                            }
                        >
                            {str(s.availability_label)}
                            <ChevronDown size={13} />
                        </button>
                        {panel === "availability" && (
                            <div className="sa-filter-popover" id="sa-availability-panel">
                                <div>
                                    <span>Availability</span>
                                    <button
                                        type="button"
                                        onClick={() => setPanel(null)}
                                        aria-label="Close availability filter"
                                    >
                                        <X size={15} />
                                    </button>
                                </div>
                                <label className="sa-check">
                                    <input
                                        type="checkbox"
                                        checked={
                                            Boolean(filters?.in_stock) && filters?.in_stock !== "0"
                                        }
                                        onChange={(e) =>
                                            visit({ in_stock: e.target.checked ? "1" : null })
                                        }
                                    />
                                    In stock only
                                </label>
                            </div>
                        )}
                    </div>
                    <div className="sa-filter-control">
                        <button
                            type="button"
                            aria-expanded={panel === "price"}
                            aria-controls="sa-price-panel"
                            onClick={() => setPanel(panel === "price" ? null : "price")}
                        >
                            {str(s.price_label)}
                            <ChevronDown size={13} />
                        </button>
                        {panel === "price" && (
                            <form
                                id="sa-price-panel"
                                className="sa-filter-popover"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    const data = new FormData(e.currentTarget);
                                    visit({
                                        min_price: String(data.get("min_price") ?? ""),
                                        max_price: String(data.get("max_price") ?? ""),
                                    });
                                }}
                            >
                                <div>
                                    <span>Price range</span>
                                    <button
                                        type="button"
                                        onClick={() => setPanel(null)}
                                        aria-label="Close price filter"
                                    >
                                        <X size={15} />
                                    </button>
                                </div>
                                <div className="sa-price-fields">
                                    <label>
                                        From
                                        <input
                                            type="number"
                                            name="min_price"
                                            min="0"
                                            step="0.01"
                                            defaultValue={filters?.min_price}
                                        />
                                    </label>
                                    <label>
                                        To
                                        <input
                                            type="number"
                                            name="max_price"
                                            min="0"
                                            step="0.01"
                                            defaultValue={filters?.max_price}
                                        />
                                    </label>
                                </div>
                                <button className="sa-filter-apply" type="submit">
                                    Apply
                                </button>
                            </form>
                        )}
                    </div>
                    {Boolean(filterBrands?.length || filterOptions?.length) && (
                        <div className="sa-filter-control">
                            <button
                                type="button"
                                aria-expanded={panel === "more"}
                                onClick={() => setPanel(panel === "more" ? null : "more")}
                            >
                                More filters
                                <ChevronDown size={13} />
                            </button>
                            {panel === "more" && (
                                <form
                                    className="sa-filter-popover"
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        const data = new FormData(e.currentTarget);
                                        const u = new URL(window.location.href);
                                        u.searchParams.delete("page");
                                        u.searchParams.delete("brand_id");
                                        for (const k of new Set(u.searchParams.keys()))
                                            if (k.startsWith("options")) u.searchParams.delete(k);
                                        for (const [k, v] of data.entries())
                                            if (String(v)) u.searchParams.append(k, String(v));
                                        setPanel(null);
                                        router.get(
                                            u.pathname + u.search,
                                            {},
                                            { preserveScroll: true },
                                        );
                                    }}
                                >
                                    {!!filterBrands?.length && (
                                        <label>
                                            Brand
                                            <select
                                                name="brand_id"
                                                defaultValue={filters?.brand_id ?? ""}
                                            >
                                                <option value="">All brands</option>
                                                {filterBrands.map((b) => (
                                                    <option key={b.id} value={b.id}>
                                                        {b.name}
                                                    </option>
                                                ))}
                                            </select>
                                        </label>
                                    )}
                                    {filterOptions?.map((o) => (
                                        <fieldset key={o.id}>
                                            <legend>{o.name}</legend>
                                            {o.values.map((v) => (
                                                <label key={v.id} className="sa-check">
                                                    <input
                                                        type="checkbox"
                                                        name="options[]"
                                                        value={v.id}
                                                        defaultChecked={filters?.options?.includes(
                                                            v.id,
                                                        )}
                                                    />
                                                    {v.value}
                                                </label>
                                            ))}
                                        </fieldset>
                                    ))}
                                    <button type="submit" className="sa-filter-apply">
                                        Apply
                                    </button>
                                </form>
                            )}
                        </div>
                    )}
                </div>
                <div className="sa-sort-controls">
                    <label>
                        {str(s.sort_label)}
                        <select
                            aria-label="Sort products"
                            value={sort}
                            onChange={(e) => visit({ sort: e.target.value })}
                        >
                            <option value="newest">Newest</option>
                            <option value="price_asc">Price, low to high</option>
                            <option value="price_desc">Price, high to low</option>
                            <option value="name">Alphabetically, A–Z</option>
                        </select>
                    </label>
                    <span>{products.total} products</span>
                </div>
            </div>
            {filtered && (
                <div className="sa-active-filters">
                    <button type="button" onClick={clear}>
                        Remove all filters <X size={13} />
                    </button>
                </div>
            )}
            <PlaceSlot place="catalog.before_products" />
            {products.data.length ? (
                <div className="sa-product-grid sa-catalog-grid">
                    {products.data.map((p) => (
                        <SableCard
                            key={p.id}
                            product={p}
                            swatches={s.show_swatches !== false}
                            label={str(s.add_label)}
                        />
                    ))}
                </div>
            ) : (
                <p className="sa-empty">
                    No products match your selection. Try another search or clear your filters.
                </p>
            )}
            {products.last_page > 1 && (
                <nav className="sa-pagination" aria-label="Pagination">
                    {products.prev_page_url && (
                        <Link href={products.prev_page_url} aria-label="Previous page">
                            ←
                        </Link>
                    )}
                    {Array.from({ length: products.last_page }, (_, i) => i + 1)
                        .filter(
                            (n) =>
                                n === 1 ||
                                n === products.last_page ||
                                Math.abs(n - products.current_page) < 3,
                        )
                        .map((n, i, list) => (
                            <span key={n}>
                                {i > 0 && n - list[i - 1] > 1 && (
                                    <span className="sa-pagination-gap">…</span>
                                )}
                                {n === products.current_page ? (
                                    <span aria-current="page">{n}</span>
                                ) : (
                                    <Link
                                        href={(() => {
                                            const base =
                                                products.next_page_url ??
                                                products.prev_page_url ??
                                                "";
                                            const [path, hash = ""] = base.split("#");
                                            const [pathname, search = ""] = path.split("?");
                                            const params = new URLSearchParams(search);
                                            params.set("page", String(n));
                                            return (
                                                pathname + "?" + params + (hash ? "#" + hash : "")
                                            );
                                        })()}
                                    >
                                        {n}
                                    </Link>
                                )}
                            </span>
                        ))}
                    {products.next_page_url && (
                        <Link href={products.next_page_url} aria-label="Next page">
                            →
                        </Link>
                    )}
                </nav>
            )}
        </section>
    );
}
