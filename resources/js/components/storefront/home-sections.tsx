import { Link } from '@inertiajs/react';
import { ProductGrid } from '@/components/storefront/product-card';
import { Button } from '@/components/ui/button';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { category } from '@/routes/storefront';
import type { ProductCard } from '@/types';

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
        | 'image_with_text'
        | 'rich_text'
        | 'promo_banner';
    settings: Record<string, string | number | boolean | null>;
    data: {
        image?: string | null;
        products?: ProductCard[];
        categories?: {
            id: number;
            name: string;
            slug: string;
            description: string | null;
        }[];
    };
};

export function HomeSections({ sections }: { sections: HomeSection[] }) {
    return (
        <div className="grid" style={{ gap: 'var(--store-section-gap, 3rem)' }}>
            {sections.map((section) => (
                <Section key={section.key} section={section} />
            ))}
        </div>
    );
}

function Section({ section }: { section: HomeSection }) {
    switch (section.type) {
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

    if (categories.length === 0) {
        return null;
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
            <p className="text-center font-medium sm:text-left">
                {text(s.text)}
            </p>
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
