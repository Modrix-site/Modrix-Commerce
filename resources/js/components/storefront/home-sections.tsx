import { Link } from '@inertiajs/react';
import { ProductCard, ProductGrid } from '@/components/storefront/product-card';
import { ElectronicsCard } from '@/components/storefront/electronics-card';
import { useStoreTheme } from '@/hooks/use-store-theme';
import { Button } from '@/components/ui/button';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { category } from '@/routes/storefront';
import type { ProductCard as ProductCardData } from '@/types';

/**
 * Core renderers for theme sections. Themes choose which sections appear,
 * in what order and with which settings; every value arrives validated and
 * is rendered as plain text (never as HTML).
 */
export type HomeSection = {
    key: string;
    type:
        | 'hero'
        | 'featured_products'
        | 'category_grid'
        | 'campaign'
        | 'promotion_grid'
        | 'image_with_text'
        | 'rich_text'
        | 'promo_banner';
    // Campaign and tile content is validated by the theme schema.
    settings: Record<string, string | number | boolean | null>;
    data: {
        image?: string | null;
        tiles?: {
            title: string;
            text: string;
            image: string | null;
            link: string;
        }[];
        products?: ProductCardData[];
        categories?: {
            id: number;
            name: string;
            slug: string;
            description: string | null;
            image_url?: string | null;
        }[];
    };
};

export function HomeSections({ sections }: { sections: HomeSection[] }) {
    const { header } = useStoreTheme();
    return (
        <div
            className={header === 'electronics' ? 'electronics-home' : 'grid'}
            style={
                header === 'electronics'
                    ? undefined
                    : { gap: 'var(--store-section-gap, 3rem)' }
            }
        >
            {sections.map((section) => (
                <Section key={section.key} section={section} />
            ))}
        </div>
    );
}

function Section({ section }: { section: HomeSection }) {
    switch (section.type) {
        case 'campaign':
            return <Campaign section={section} />;
        case 'promotion_grid':
            return <PromotionalGrid section={section} />;
        case 'hero':
            return <Hero section={section} />;
        case 'featured_products':
            return <FeaturedProducts section={section} />;
        case 'category_grid':
            return <CategoryGrid section={section} />;
        case 'image_with_text':
            return <ImageWithText section={section} />;
        case 'rich_text':
            return <RichText section={section} />;
        case 'promo_banner':
            return <PromoBanner section={section} />;
        default:
            return null;
    }
}

function Campaign({ section }: { section: HomeSection }) {
    const settings = section.settings;
    return (
        <section
            className="electronics-campaign"
            data-height={text(settings.height)}
            style={{
                backgroundColor: text(settings.background),
                color: text(settings.foreground),
            }}
        >
            <div className="electronics-campaign-copy">
                <p>{text(settings.eyebrow)}</p>
                <h1>{text(settings.heading)}</h1>
                <p>{text(settings.body)}</p>
                {text(settings.button_label) && (
                    <a href={text(settings.button_link) || '/search'}>
                        {text(settings.button_label)}
                    </a>
                )}
            </div>
            {section.data.image && <img src={section.data.image} alt="" />}
        </section>
    );
}

function PromotionalGrid({ section }: { section: HomeSection }) {
    return (
        <section>
            {text(section.settings.title) && (
                <h2>{text(section.settings.title)}</h2>
            )}
            <div
                className="electronics-promotions"
                data-style={text(section.settings.style)}
                style={
                    {
                        '--tile-columns': Number(section.settings.columns) || 4,
                    } as import('react').CSSProperties
                }
            >
                {section.data.tiles?.map((tile, i) => (
                    <a
                        className="electronics-promo"
                        href={tile.link || '/search'}
                        key={i}
                    >
                        <div>
                            <h3>{tile.title}</h3>
                            {tile.text && <p>{tile.text}</p>}
                            <small>EXPLORE</small>
                        </div>
                        {!tile.image && section.settings.style === 'circle' && (
                            <span className="electronics-brand-circle">
                                {tile.title}
                            </span>
                        )}
                        {tile.image && (
                            <img src={tile.image} alt="" loading="lazy" />
                        )}
                    </a>
                ))}
            </div>
        </section>
    );
}

function text(value: unknown): string {
    return typeof value === 'string' ? value : '';
}

/** Internal paths navigate with Inertia; https links are ordinary links. */
function SectionLink({
    href,
    label,
    variant = 'default',
}: {
    href: string;
    label: string;
    variant?: 'default' | 'secondary' | 'outline';
}) {
    if (!href || !label) {
        return null;
    }

    return (
        <Button variant={variant} asChild>
            {href.startsWith('/') ? (
                <Link href={href}>{label}</Link>
            ) : (
                <a href={href} rel="noopener noreferrer">
                    {label}
                </a>
            )}
        </Button>
    );
}

function Hero({ section }: { section: HomeSection }) {
    const s = section.settings;
    const image = section.data.image;
    const centered = s.alignment === 'center';
    const { header, theme } = useStoreTheme();
    const settings = theme?.settings;
    const eyebrow = text(settings?.hero_eyebrow);
    const heroPoints = text(settings?.hero_points)
        .split('·')
        .map((point) => point.trim())
        .filter(Boolean);

    if (header === 'atelier') {
        return (
            <section className="ecom-hero">
                <div className="ecom-hero-copy">
                    {eyebrow && <p className="ecom-eyebrow">{eyebrow}</p>}
                    <h1>{text(s.heading)}</h1>
                    {text(s.subheading) && (
                        <p className="ecom-hero-description">
                            {text(s.subheading)}
                        </p>
                    )}
                    {text(s.button_label) && (
                        <a
                            href={text(s.button_link) || '/search'}
                            className="ecom-button ecom-primary"
                        >
                            {text(s.button_label)}
                        </a>
                    )}
                    {heroPoints.length > 0 && (
                        <div className="ecom-hero-points">
                            {heroPoints.map((point) => (
                                <span key={point}>{point}</span>
                            ))}
                        </div>
                    )}
                </div>
                {image && (
                    <div className="ecom-hero-photo">
                        <img src={image} alt="" />
                    </div>
                )}
            </section>
        );
    }

    return (
        <section
            className={cn(
                'relative flex overflow-hidden rounded-[var(--radius)]',
                s.height === 'small' && 'min-h-56',
                s.height === 'large' && 'min-h-[32rem]',
                (s.height === 'medium' || !s.height) && 'min-h-80',
                image ? 'text-white' : 'bg-accent text-accent-foreground',
            )}
        >
            {image && (
                <>
                    <img
                        src={image}
                        alt=""
                        className="absolute inset-0 size-full object-cover"
                    />
                    <div
                        className="absolute inset-0 bg-gradient-to-r from-black/60 via-black/35 to-black/10"
                        aria-hidden
                    />
                </>
            )}
            <div
                className={cn(
                    'relative grid max-w-2xl content-center gap-4 p-8 sm:p-12',
                    centered && 'mx-auto text-center',
                )}
            >
                {eyebrow && (
                    <p className="text-sm font-medium tracking-[0.18em] uppercase">
                        {eyebrow}
                    </p>
                )}
                <h1 className="text-3xl font-semibold tracking-tight sm:text-5xl">
                    {text(s.heading)}
                </h1>
                {text(s.subheading) && (
                    <p
                        className={cn(
                            'text-lg',
                            image ? 'text-white/85' : 'text-muted-foreground',
                        )}
                    >
                        {text(s.subheading)}
                    </p>
                )}
                <div className={cn(centered && 'flex justify-center')}>
                    <SectionLink
                        href={text(s.button_link)}
                        label={text(s.button_label)}
                        variant="default"
                    />
                </div>
            </div>
        </section>
    );
}

function FeaturedProducts({ section }: { section: HomeSection }) {
    const products = section.data.products ?? [];
    const t = useT();
    const { header, theme } = useStoreTheme();
    const settings = theme?.settings;
    const showExploreAll = settings?.show_explore_all !== false;

    if (header === 'electronics')
        return (
            <section className="electronics-rail">
                <div className="electronics-rail-header">
                    <h2>{text(section.settings.title)}</h2>
                    <Link
                        href={
                            text(section.settings.category)
                                ? category(text(section.settings.category))
                                : '/search'
                        }
                    >
                        View all ›
                    </Link>
                </div>
                {products.length ? (
                    <div className="electronics-product-rail">
                        {products.map((product) => (
                            <ElectronicsCard
                                key={product.id}
                                product={product}
                            />
                        ))}
                    </div>
                ) : (
                    <p className="electronics-empty">
                        No products available in this collection yet.
                    </p>
                )}
            </section>
        );

    if (header === 'atelier') {
        return (
            <section className="ecom-section ecom-essentials">
                <div className="ecom-section-heading">
                    <h2>{text(section.settings.title)}</h2>
                    {showExploreAll && (
                        <Link href="/search" className="ecom-text-link">
                            Explore all
                            <span aria-hidden>→</span>
                        </Link>
                    )}
                </div>
                {products.length > 0 && (
                    <div className="ecom-product-row">
                        {products.map((product) => (
                            <ProductCard key={product.id} product={product} />
                        ))}
                    </div>
                )}
            </section>
        );
    }

    return (
        <section
            className="grid gap-4"
            aria-label={text(section.settings.title)}
        >
            {text(section.settings.title) && (
                <h2 className="text-xl font-semibold">
                    {text(section.settings.title)}
                </h2>
            )}
            {products.length > 0 ? (
                <ProductGrid products={products} />
            ) : (
                <p className="text-muted-foreground">
                    {t(
                        'No products are available yet. Please check back soon.',
                    )}
                </p>
            )}
        </section>
    );
}

function CategoryGrid({ section }: { section: HomeSection }) {
    const categories = section.data.categories ?? [];
    const { header } = useStoreTheme();

    if (categories.length === 0) {
        return null;
    }

    if (header === 'atelier') {
        return (
            <section className="ecom-section ecom-category-section">
                {text(section.settings.title) && (
                    <div className="ecom-section-heading">
                        <h2>{text(section.settings.title)}</h2>
                    </div>
                )}
                <div className="ecom-categories">
                    {categories.map((c) => (
                        <Link key={c.id} href={category(c.slug)}>
                            {c.image_url && (
                                <img src={c.image_url} alt="" loading="lazy" />
                            )}
                            <span>
                                {c.name} <span aria-hidden>→</span>
                            </span>
                        </Link>
                    ))}
                </div>
            </section>
        );
    }

    return (
        <section
            className="grid gap-4"
            aria-label={text(section.settings.title)}
        >
            {text(section.settings.title) && (
                <h2 className="text-xl font-semibold">
                    {text(section.settings.title)}
                </h2>
            )}
            <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                {categories.map((c) => (
                    <li key={c.id}>
                        <Link
                            href={category(c.slug)}
                            className="block h-full rounded-[var(--radius)] border bg-card p-4 transition-colors hover:bg-muted/60"
                        >
                            {c.image_url && (
                                <img
                                    src={c.image_url}
                                    alt=""
                                    loading="lazy"
                                    className="mb-3 aspect-[4/3] w-full rounded-md object-cover"
                                />
                            )}
                            <span className="font-medium">{c.name}</span>
                            {c.description && (
                                <span className="mt-1 line-clamp-2 block text-sm text-muted-foreground">
                                    {c.description}
                                </span>
                            )}
                        </Link>
                    </li>
                ))}
            </ul>
        </section>
    );
}

function ImageWithText({ section }: { section: HomeSection }) {
    const s = section.settings;
    const image = section.data.image;
    const { header } = useStoreTheme();

    if (header === 'atelier') {
        return (
            <section className="ecom-story">
                {image && <img src={image} alt="" loading="lazy" />}
                <div>
                    <p className="ecom-eyebrow">Our approach</p>
                    <h2>{text(s.heading)}</h2>
                    <p>{text(s.body)}</p>
                    {text(s.button_label) && (
                        <a
                            href={text(s.button_link) || '/search'}
                            className="ecom-text-link"
                        >
                            {text(s.button_label)}
                            <span aria-hidden>→</span>
                        </a>
                    )}
                </div>
            </section>
        );
    }

    return (
        <section className="grid items-center gap-8 md:grid-cols-2">
            {image && (
                <img
                    src={image}
                    alt=""
                    loading="lazy"
                    className={cn(
                        'aspect-[4/3] w-full rounded-[var(--radius)] object-cover',
                        s.image_position === 'right' && 'md:order-last',
                    )}
                />
            )}
            <div className={cn('grid gap-4', !image && 'md:col-span-2')}>
                <h2 className="text-2xl font-semibold tracking-tight">
                    {text(s.heading)}
                </h2>
                {text(s.body) && (
                    <p className="whitespace-pre-line text-muted-foreground">
                        {text(s.body)}
                    </p>
                )}
                <div>
                    <SectionLink
                        href={text(s.button_link)}
                        label={text(s.button_label)}
                        variant="outline"
                    />
                </div>
            </div>
        </section>
    );
}

function RichText({ section }: { section: HomeSection }) {
    const s = section.settings;
    const { header } = useStoreTheme();
    if (header === 'electronics')
        return (
            <section className="electronics-description">
                <h2>{text(s.heading)}</h2>
                <p>{text(s.body)}</p>
            </section>
        );

    return (
        <section
            className={cn(
                'mx-auto grid max-w-2xl gap-3',
                s.alignment === 'center' && 'text-center',
            )}
        >
            {text(s.heading) && (
                <h2 className="text-2xl font-semibold tracking-tight">
                    {text(s.heading)}
                </h2>
            )}
            <p className="whitespace-pre-line text-muted-foreground">
                {text(s.body)}
            </p>
        </section>
    );
}

function PromoBanner({ section }: { section: HomeSection }) {
    const s = section.settings;
    const { header } = useStoreTheme();
    if (header === 'electronics')
        return (
            <section className="electronics-promo-strip">
                <p>{text(s.text)}</p>
                {text(s.button_label) && (
                    <a href={text(s.button_link) || '/search'}>
                        {text(s.button_label)} ›
                    </a>
                )}
            </section>
        );

    return (
        <section
            className={cn(
                'flex flex-col items-center justify-between gap-4 rounded-[var(--radius)] px-6 py-5 sm:flex-row',
                s.tone === 'accent' && 'bg-accent text-accent-foreground',
                s.tone === 'muted' && 'bg-muted text-foreground',
                (s.tone === 'primary' || !s.tone) &&
                    'bg-primary text-primary-foreground',
            )}
        >
            <div className="text-center sm:text-left">
                <p className="font-medium">{text(s.text)}</p>
                {text(s.subtext) && (
                    <p className="mt-1 text-sm opacity-80">{text(s.subtext)}</p>
                )}
            </div>
            <SectionLink
                href={text(s.button_link)}
                label={text(s.button_label)}
                variant={
                    s.tone === 'primary' || !s.tone ? 'secondary' : 'default'
                }
            />
        </section>
    );
}
