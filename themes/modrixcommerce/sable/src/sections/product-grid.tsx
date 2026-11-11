import type { CSSProperties } from 'react';
import { StoreMenuLink as Link } from '@/components/storefront/store-menu-link';
import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
import type { ProductCard } from '@/types';
import { SableCard } from '../card';
import { str } from '../utils';
type Collection = { products: ProductCard[] };
export default function ProductGrid({ section }: ThemeSectionProps) {
    const s = section.settings;
    const products = (s.collection as Collection | null)?.products ?? [];
    return (
        <section className="sa-products sa-frame">
            <div className="sa-section-heading">
                <div>
                    {str(s.eyebrow) && (
                        <p className="sa-eyebrow">{str(s.eyebrow)}</p>
                    )}
                    {str(s.heading) && <h2>{str(s.heading)}</h2>}
                </div>
                {str(s.link) && str(s.view_label) && (
                    <Link href={str(s.link)}>
                        {str(s.view_label)} <span aria-hidden>↗</span>
                    </Link>
                )}
            </div>
            {products.length ? (
                <div
                    className="sa-product-grid"
                    style={
                        {
                            '--sa-columns': str(s.columns) || '4',
                        } as CSSProperties
                    }
                >
                    {products.slice(0, Number(s.limit) || 8).map((p) => (
                        <SableCard
                            key={p.id}
                            product={p}
                            quickAdd={s.quick_add !== false}
                            swatches={s.show_swatches !== false}
                            label={str(s.button_label)}
                        />
                    ))}
                </div>
            ) : (
                <p className="sa-empty">
                    Your collection will appear here when products are
                    published.
                </p>
            )}
        </section>
    );
}
