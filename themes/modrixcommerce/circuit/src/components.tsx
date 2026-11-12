import { usePage } from '@inertiajs/react';
import {
    Box,
    Camera,
    Gamepad2,
    Headphones,
    Heart,
    House,
    ImageOff,
    Keyboard,
    Laptop,
    Monitor,
    Printer,
    Smartphone,
    Speaker,
    Tablet,
    Tv,
    Watch,
} from 'lucide-react';
import { createContext, useContext, useEffect, useState } from 'react';
import type { CSSProperties, ReactNode } from 'react';
import { PlaceSlot } from '@/components/extensions/place-slot';
import { BuyButton } from '@/components/storefront/buy-button';
import { StoreMenuLink as Link } from '@/components/storefront/footer-menus';
import { ProductLabels } from '@/components/storefront/product-labels';
import { SaleTag } from '@/components/storefront/sale-notice';
import { useStoreSettings } from '@/hooks/use-store-settings';
import type { ProductCard } from '@/types';

type SectionContent = {
    settings: Record<string, unknown>;
    blocks: { type: string; settings: Record<string, unknown> }[];
};

const SectionContext = createContext<SectionContent | null>(null);

/**
 * Gives Circuit's components the settings and blocks of the theme-editor
 * section they render in. They win over the theme settings.
 */
export function CircuitSection({
    section,
    children,
}: {
    section: SectionContent;
    children: ReactNode;
}) {
    return (
        <SectionContext.Provider value={section}>
            {children}
        </SectionContext.Provider>
    );
}

/** Circuit's settings: the current section's first, then the theme's, then the default. */
export function useCircuit() {
    const { storefront } = usePage().props;
    const section = useContext(SectionContext);
    const theme = storefront?.theme.settings ?? {};
    const value = (key: string): unknown =>
        section && key in section.settings ? section.settings[key] : theme[key];

    return {
        storefront,
        text: (key: string, fallback = '') => {
            const v = value(key);

            return typeof v === 'string' || typeof v === 'number'
                ? String(v) ||
                      (section && key in section.settings ? '' : fallback)
                : fallback;
        },
        show: (key: string) => value(key) !== false,
        number: (key: string, fallback: number) => {
            const n = Number(value(key));
            return Number.isFinite(n) && n > 0 ? n : fallback;
        },
        /** The section's blocks of a type (settings only), or the legacy list outside the editor's sections. */
        list: <T extends Record<string, unknown>>(
            type: string,
            legacy: () => T[],
        ): T[] =>
            section
                ? (section.blocks
                      .filter((block) => block.type === type)
                      .map((block) => block.settings) as T[])
                : legacy(),
    };
}

/** The theme's color settings as CSS variables on the page root. */
export function CircuitVariables() {
    const { text } = useCircuit();
    const vars = {
        '--ci-accent': text('accent', '#e5484d'),
        '--ci-success': text('success', '#1f9d55'),
        '--ci-panel': text('panel', '#f4f4f6'),
        '--ci-header-bg': text('header_background', '#14102a'),
        '--ci-header-ink': text('header_foreground', '#ffffff'),
        '--ci-voucher': text('voucher_color', '#0b4d3f'),
    };
    const shape =
        { pill: '999px', rounded: '8px', square: '0' }[
            text('button_shape', 'pill')
        ] ?? '999px';
    const css = [
        ...Object.entries(vars)
            .filter(([, value]) => /^#[0-9a-f]{3,8}$/i.test(value))
            .map(([key, value]) => `${key}:${value}`),
        `--ci-button-radius:${shape}`,
    ].join(';');
    return <style>{`[data-theme="modrixcommerce/circuit"]{${css}}`}</style>;
}

/* ---------- Lists kept on this device (wishlist, recently viewed) ---------- */

export type SavedProduct = {
    id: number;
    slug: string;
    name: string;
    image: string | null;
    price: string | null;
};

const SAVED_KEY = 'circuit.saved';
const RECENT_KEY = 'circuit.recent';
const CHANGED = 'circuit:lists';

function read(key: string): SavedProduct[] {
    try {
        const value: unknown = JSON.parse(localStorage.getItem(key) || '[]');
        return Array.isArray(value)
            ? value.filter(
                  (item): item is SavedProduct =>
                      typeof item === 'object' &&
                      item !== null &&
                      Number.isInteger((item as SavedProduct).id) &&
                      typeof (item as SavedProduct).slug === 'string',
              )
            : [];
    } catch {
        return [];
    }
}

function write(key: string, items: SavedProduct[]) {
    try {
        localStorage.setItem(key, JSON.stringify(items));
        window.dispatchEvent(new Event(CHANGED));
    } catch {
        /* Device storage may be unavailable (private mode). */
    }
}

/** A list stored on this device, kept in sync across components and tabs. */
export function useDeviceList(kind: 'saved' | 'recent') {
    const key = kind === 'saved' ? SAVED_KEY : RECENT_KEY;
    const [items, setItems] = useState<SavedProduct[]>([]);
    useEffect(() => {
        const refresh = () => setItems(read(key));
        refresh();
        window.addEventListener('storage', refresh);
        window.addEventListener(CHANGED, refresh);
        return () => {
            window.removeEventListener('storage', refresh);
            window.removeEventListener(CHANGED, refresh);
        };
    }, [key]);
    return items;
}

export function toggleSaved(product: SavedProduct) {
    const items = read(SAVED_KEY);
    const exists = items.some((item) => item.id === product.id);
    write(
        SAVED_KEY,
        exists
            ? items.filter((item) => item.id !== product.id)
            : [product, ...items].slice(0, 50),
    );
}

/** Remembers a viewed product (most recent first, 12 at most). */
export function rememberViewed(product: SavedProduct) {
    write(
        RECENT_KEY,
        [
            product,
            ...read(RECENT_KEY).filter((item) => item.id !== product.id),
        ].slice(0, 12),
    );
}

export function cardEntry(product: ProductCard): SavedProduct {
    return {
        id: product.id,
        slug: product.slug,
        name: product.name,
        image: product.image?.url ?? null,
        price: product.price?.formatted ?? null,
    };
}

export function SaveButton({
    product,
    className = 'ci-save',
    label,
}: {
    product: SavedProduct;
    className?: string;
    label?: ReactNode;
}) {
    const { show } = useCircuit();
    const saved = useDeviceList('saved').some((item) => item.id === product.id);
    if (!show('show_saved')) return null;
    return (
        <button
            type="button"
            className={className}
            aria-pressed={saved}
            aria-label={`${saved ? 'Remove' : 'Save'} ${product.name} ${saved ? 'from' : 'to'} your wishlist on this device`}
            onClick={() => toggleSaved(product)}
        >
            <Heart
                size={16}
                fill={saved ? 'currentColor' : 'none'}
                aria-hidden
            />
            {label}
        </button>
    );
}

/* ---------- Product card ---------- */

function discountPercent(product: ProductCard): number | null {
    const price = Number(product.price?.amount);
    const compare = Number(product.compare_at_price?.amount);
    return compare > price && price > 0
        ? Math.round((1 - price / compare) * 100)
        : null;
}

export function CircuitCard({
    product,
    variant = 'grid',
}: {
    product: ProductCard;
    variant?: 'grid' | 'list' | 'compact';
}) {
    const { catalog } = useStoreSettings();
    const url = `/p/${product.slug}`;
    const off = discountPercent(product);
    return (
        <article className="ci-card" data-variant={variant}>
            <div className="ci-card-photo store-card-image">
                <Link href={url} aria-label={product.name} tabIndex={-1}>
                    {product.image ? (
                        <img
                            src={product.image.url}
                            alt={product.image.alt || product.name}
                            loading="lazy"
                        />
                    ) : (
                        <ImageOff size={32} aria-label="No product image" />
                    )}
                    {product.image &&
                        catalog.hover_image &&
                        product.hover_image && (
                            <img
                                className="store-card-hover"
                                src={product.hover_image.url}
                                alt=""
                                loading="lazy"
                            />
                        )}
                </Link>
                <div className="ci-card-badges">
                    {catalog.badges && off !== null && (
                        <span className="ci-badge ci-badge-sale">-{off}%</span>
                    )}
                    {catalog.badges && (
                        <ProductLabels
                            labels={product.labels}
                            className="ci-labels"
                        />
                    )}
                </div>
                {catalog.badges && (
                    <SaleTag sale={product.sale} className="ci-card-sale" />
                )}
                {variant !== 'compact' && (
                    <SaveButton product={cardEntry(product)} />
                )}
            </div>
            <div className="ci-card-copy">
                {catalog.show_brand && product.brand && (
                    <p className="ci-card-brand">{product.brand.name}</p>
                )}
                <h3>
                    <Link href={url}>{product.name}</Link>
                </h3>
                <PlaceSlot place="product.card" extraProps={{ product }} />
                <p className="ci-price store-price">
                    <span
                        className={off !== null ? 'ci-price-sale' : undefined}
                    >
                        {product.has_variants && 'From '}
                        {product.price?.formatted ?? 'View product'}
                    </span>
                    {off !== null && (
                        <s>{product.compare_at_price?.formatted}</s>
                    )}
                </p>
                {!product.in_stock && (
                    <p className="ci-card-note">Out of stock</p>
                )}
                {variant !== 'compact' && (
                    <BuyButton
                        product={product}
                        className="store-button ci-button ci-card-buy"
                    />
                )}
            </div>
        </article>
    );
}

export function ProductRow({
    products,
    columns = 5,
}: {
    products: ProductCard[];
    columns?: number;
}) {
    if (!products.length) return null;
    return (
        <div
            className="ci-row"
            style={{ '--ci-row-columns': columns } as CSSProperties}
        >
            {products.map((product) => (
                <CircuitCard key={product.id} product={product} />
            ))}
        </div>
    );
}

/* ---------- Countdown (real date from the customizer) ---------- */

function parseDate(value: string): number | null {
    const match = value
        .trim()
        .match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?$/);
    if (!match) return null;
    const [, y, m, d, h = '23', min = '59'] = match;
    const time = new Date(
        Number(y),
        Number(m) - 1,
        Number(d),
        Number(h),
        Number(min),
    ).getTime();
    return Number.isNaN(time) ? null : time;
}

export function Countdown({ until, label }: { until: string; label: string }) {
    const end = parseDate(until);
    const [now, setNow] = useState<number | null>(null);
    useEffect(() => {
        setNow(Date.now());
        const timer = window.setInterval(() => setNow(Date.now()), 1000);
        return () => window.clearInterval(timer);
    }, []);
    if (end === null || now === null || end <= now) return null;
    const left = Math.floor((end - now) / 1000);
    const parts = [
        Math.floor(left / 86400),
        Math.floor(left / 3600) % 24,
        Math.floor(left / 60) % 60,
        left % 60,
    ];
    return (
        <div
            className="ci-countdown"
            role="timer"
            aria-label={`${label} ${parts[0]} days ${parts[1]} hours ${parts[2]} minutes`}
        >
            <span>{label}</span>
            {parts.map((value, i) => (
                <b key={i} aria-hidden>
                    {String(value).padStart(2, '0')}
                </b>
            ))}
        </div>
    );
}

/* ---------- Small shared pieces ---------- */

export function PaymentBadges({
    labels,
    className,
}: {
    labels: string;
    className?: string;
}) {
    const items = labels
        .split(',')
        .map((item) => item.trim())
        .filter(Boolean)
        .slice(0, 10);
    if (!items.length) return null;
    return (
        <ul
            className={`ci-payments ${className ?? ''}`}
            aria-label="Payment methods"
        >
            {items.map((item) => (
                <li key={item}>{item}</li>
            ))}
        </ul>
    );
}

const ICONS: [RegExp, typeof Box][] = [
    [/laptop|pc|computer|macbook|notebook/i, Laptop],
    [/phone|mobile/i, Smartphone],
    [/tablet|ipad/i, Tablet],
    [/camera|photo/i, Camera],
    [/head|audio|ear|sound/i, Headphones],
    [/speaker/i, Speaker],
    [/watch|wear/i, Watch],
    [/tv|television/i, Tv],
    [/monitor|screen|display/i, Monitor],
    [/game|console/i, Gamepad2],
    [/print/i, Printer],
    [/keyboard|mouse|accessor/i, Keyboard],
    [/home|appliance|kitchen/i, House],
];

/** An icon for a category, guessed from its name. */
export function CategoryIcon({
    name,
    size = 18,
}: {
    name: string;
    size?: number;
}) {
    const Icon = ICONS.find(([pattern]) => pattern.test(name))?.[1] ?? Box;
    return <Icon size={size} aria-hidden />;
}

export function SectionHeading({
    title,
    action,
}: {
    title: string;
    action?: ReactNode;
}) {
    return (
        <div className="ci-section-head">
            <h2>{title}</h2>
            {action}
        </div>
    );
}
