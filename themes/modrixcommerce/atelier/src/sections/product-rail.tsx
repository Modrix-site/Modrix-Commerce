import type { CSSProperties } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
import type { ProductCard } from '@/types';
import { str, collection, Card } from '../components';

export default function ProductRail({ section }: ThemeSectionProps) {
    const s = section.settings;
    const pageProps = usePage().props as Record<string, unknown>;
    const c = collection(s.category ?? s.collection);

    // Get products from collection or fall back to any products in page sections
    let products: ProductCard[] = Array.isArray(c?.products)
        ? c.products
        : ((c?.products as { data?: ProductCard[] } | undefined)?.data ?? []);
    if (!products.length && Array.isArray(pageProps.sections)) {
        for (const sec of pageProps.sections as Array<{
            type: string;
            settings: Record<string, unknown>;
        }>) {
            if (sec?.settings?.products) {
                const sp = sec.settings.products;
                if (Array.isArray(sp)) {
                    products = sp as ProductCard[];
                    break;
                } else if (
                    sp &&
                    typeof sp === 'object' &&
                    'data' in sp &&
                    Array.isArray((sp as { data?: ProductCard[] }).data)
                ) {
                    products = (sp as { data: ProductCard[] }).data;
                    break;
                }
            }
        }
    }

    const limit = Number(s.limit) || 8;
    const cols = Number(s.columns) || 4;
    const displayed = products.slice(0, limit);

    return (
        <section
            className="at-section"
            style={{ backgroundColor: str(s.background, '#f7f6f3') }}
        >
            <div className="at-frame">
                <div className="at-section-head">
                    <h2>{str(s.heading, 'Considered essentials')}</h2>
                    <Link
                        href={str(s.link_url, '/search')}
                        className="at-text-link"
                    >
                        <span>{str(s.link_label, 'Explore all')}</span>
                        <ArrowRight size={14} />
                    </Link>
                </div>

                {displayed.length > 0 ? (
                    <div
                        className="at-product-grid"
                        style={{ '--at-cols': cols } as CSSProperties}
                    >
                        {displayed.map((p) => (
                            <Card key={p.id} product={p} quickAdd />
                        ))}
                    </div>
                ) : (
                    <div className="py-12 text-center text-sm font-light text-neutral-500">
                        No products found in this collection.
                    </div>
                )}
            </div>
        </section>
    );
}
