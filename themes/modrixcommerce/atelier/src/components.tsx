import { Form, Link, usePage } from '@inertiajs/react';
import './style.css';
import {
    Heart,
    X,
    ShoppingBag,
    ArrowRight,
    Check,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { CSSProperties } from 'react';
import type { ProductCard } from '@/types';
import CartController from '@/actions/App/Http/Controllers/Storefront/CartController';
import InputError from '@/components/input-error';
import { PlaceSlot } from '@/components/extensions/place-slot';
import { ProductLabels } from '@/components/storefront/product-labels';
import { SaleTag } from '@/components/storefront/sale-notice';

export const str = (v: unknown, fallback = ''): string =>
    typeof v === 'string' && v.trim() !== '' ? v : fallback;

export const num = (v: unknown, fallback = 0): number => {
    const n = Number(v);
    return Number.isFinite(n) ? n : fallback;
};

export function assetUrl(val: unknown, fallback = ''): string {
    if (typeof val !== 'string' || !val) return fallback;
    if (val.startsWith('asset:')) {
        return `/themes/modrixcommerce/atelier/${val.slice(6)}`;
    }
    return val;
}

export type Collection = {
    title: string;
    url: string;
    products: ProductCard[];
};

export const collection = (v: unknown): Collection | null =>
    v && typeof v === 'object' && 'products' in v ? (v as Collection) : null;

export type SavedItem = {
    id: number;
    name: string;
    slug: string;
    image: string;
    price: string;
    stock: boolean;
    description: string;
};

const key = (kind: string) => 'atelier:' + kind;

export function toSaved(p: ProductCard): SavedItem {
    return {
        id: p.id,
        name: p.name,
        slug: p.slug,
        image: p.image?.url ?? '',
        price: p.price?.formatted ?? '',
        stock: p.in_stock,
        description: p.short_description ?? '',
    };
}

function loadSaved(kind: string): SavedItem[] {
    try {
        const v = JSON.parse(localStorage.getItem(key(kind)) ?? '[]');
        return Array.isArray(v)
            ? v
                  .filter(
                      (x) =>
                          x &&
                          Number.isInteger(x.id) &&
                          typeof x.slug === 'string' &&
                          typeof x.name === 'string',
                  )
                  .slice(0, 50)
            : [];
    } catch {
        return [];
    }
}

export function useDeviceList(kind: string) {
    const [items, setItems] = useState<SavedItem[]>([]);
    useEffect(() => {
        const refresh = () => setItems(loadSaved(kind));
        refresh();
        window.addEventListener('atelier:lists', refresh);
        window.addEventListener('storage', refresh);
        return () => {
            window.removeEventListener('atelier:lists', refresh);
            window.removeEventListener('storage', refresh);
        };
    }, [kind]);
    return items;
}

export function toggleSaved(kind: string, item: SavedItem) {
    try {
        const list = loadSaved(kind);
        const next = list.some((p) => p.id === item.id)
            ? list.filter((p) => p.id !== item.id)
            : [...list, item].slice(-50);
        localStorage.setItem(key(kind), JSON.stringify(next));
        window.dispatchEvent(new Event('atelier:lists'));
    } catch {
        /* Browsing remains uninterrupted without localStorage */
    }
}

export function rememberViewed(item: SavedItem) {
    try {
        const list = loadSaved('recent').filter((x) => x.id !== item.id);
        localStorage.setItem(
            key('recent'),
            JSON.stringify([item, ...list].slice(0, 12)),
        );
        window.dispatchEvent(new Event('atelier:lists'));
    } catch {}
}

export function QuickAdd({ product }: { product: ProductCard }) {
    const { storefront } = usePage().props;
    const labels = storefront?.theme?.settings;
    if (!product.in_stock) {
        return (
            <button type="button" className="at-btn at-btn-disabled" disabled>
                {str(labels?.sold_label, 'Currently unavailable')}
            </button>
        );
    }
    if (!product.variant_id) {
        return (
            <Link className="at-btn at-btn-secondary" href={'/p/' + product.slug}>
                {str(labels?.options_label, 'Choose options')}
            </Link>
        );
    }
    return (
        <Form
            {...CartController.store.form()}
            options={{ preserveScroll: true }}
            className="w-full"
        >
            {({ processing, errors }) => (
                <>
                    <input
                        type="hidden"
                        name="variant_id"
                        value={product.variant_id ?? ''}
                    />
                    <input type="hidden" name="quantity" value="1" />
                    <button
                        className="at-btn at-btn-primary w-full bg-black text-white hover:bg-neutral-800"
                        type="submit"
                        disabled={processing}
                    >
                        <ShoppingBag size={14} className="mr-1.5 inline" />
                        {processing
                            ? 'Adding…'
                            : str(labels?.cart_label, 'Add to bag')}
                    </button>
                    <InputError
                        message={errors.variant_id ?? errors.quantity}
                    />
                </>
            )}
        </Form>
    );
}

export function Card({
    product: p,
    quickAdd = false,
}: {
    product: ProductCard;
    quickAdd?: boolean;
}) {
    const { storefront } = usePage().props;
    const saved = useDeviceList('wishlist');
    const isSaved = saved.some((x) => x.id === p.id);
    const amount = Number(p.price?.amount);
    const was = Number(p.compare_at_price?.amount);
    const discount =
        amount > 0 && was > amount ? Math.round((1 - amount / was) * 100) : 0;

    return (
        <article className="at-card" key={p.id}>
            <div className="at-card-photo">
                <Link
                    className="at-photo-link"
                    href={'/p/' + p.slug}
                    aria-label={p.name}
                >
                    {p.image ? (
                        <img
                            src={p.image.url}
                            alt={p.image.alt ?? p.name}
                            loading="lazy"
                        />
                    ) : (
                        <div className="at-empty-photo">No image</div>
                    )}
                </Link>
                <button
                    className="at-heart"
                    type="button"
                    aria-label={`${isSaved ? 'Remove from favourites' : 'Save to favourites'}: ${p.name}`}
                    aria-pressed={isSaved}
                    onClick={() => toggleSaved('wishlist', toSaved(p))}
                >
                    <Heart
                        size={17}
                        fill={isSaved ? 'currentColor' : 'none'}
                    />
                </button>
                {discount > 0 && (
                    <span className="at-badge at-badge-sale">−{discount}%</span>
                )}
                <ProductLabels labels={p.labels} />
            </div>

            <div className="at-card-caption">
                {p.brand ? (
                    <Link className="at-card-brand" href={'/b/' + p.brand.slug}>
                        {p.brand.name}
                    </Link>
                ) : (
                    <span className="at-card-brand">{storefront?.storeName}</span>
                )}
                <h3>
                    <Link href={'/p/' + p.slug}>{p.name}</Link>
                </h3>
                <PlaceSlot place="product.card" extraProps={{ product: p }} />
                <p className="at-card-price">
                    {p.has_variants ? 'From ' : ''}
                    {p.price?.formatted ?? 'Price unavailable'}
                    {p.compare_at_price && <s>{p.compare_at_price.formatted}</s>}
                </p>
                <SaleTag sale={p.sale} className="at-card-sale" />
                {quickAdd && (
                    <div className="at-card-quick-add">
                        <QuickAdd product={p} />
                    </div>
                )}
            </div>
        </article>
    );
}

export function WishlistDrawer({
    open,
    onClose,
}: {
    open: boolean;
    onClose: () => void;
}) {
    if (!open) return null;
    return <WishlistDrawerModal onClose={onClose} />;
}

function WishlistDrawerModal({ onClose }: { onClose: () => void }) {
    const items = useDeviceList('wishlist');
    const dialog = useRef<HTMLDialogElement>(null);

    useEffect(() => {
        const el = dialog.current;
        if (el && !el.open) {
            el.showModal();
        }
        return () => {
            if (el?.open) el.close();
        };
    }, []);

    return (
        <dialog
            ref={dialog}
            className="at-drawer"
            aria-label="Favourites"
            onClose={onClose}
            onClick={(e) => {
                if (e.target === dialog.current) onClose();
            }}
        >
            <div className="at-drawer-head">
                <h2>Favourites ({items.length})</h2>
                <button
                    className="at-icon-btn"
                    type="button"
                    aria-label="Close favourites"
                    onClick={onClose}
                >
                    <X size={20} />
                </button>
            </div>
            <div className="at-drawer-body">
                {items.length === 0 ? (
                    <div className="at-empty-state">
                        <Heart size={36} strokeWidth={1.2} />
                        <h3>Your favourites list is empty</h3>
                        <p>Save items with the heart icon while browsing to view them here.</p>
                        <Link
                            className="at-btn at-btn-primary"
                            href="/search"
                            onClick={onClose}
                        >
                            Explore the collection
                        </Link>
                    </div>
                ) : (
                    <div className="at-drawer-list">
                        {items.map((p) => (
                            <div className="at-drawer-item" key={p.id}>
                                <Link
                                    href={'/p/' + p.slug}
                                    onClick={onClose}
                                    className="at-drawer-item-thumb"
                                >
                                    {p.image ? (
                                        <img src={p.image} alt={p.name} />
                                    ) : (
                                        <span />
                                    )}
                                </Link>
                                <div className="at-drawer-item-info">
                                    <Link
                                        href={'/p/' + p.slug}
                                        onClick={onClose}
                                        className="at-drawer-item-title"
                                    >
                                        {p.name}
                                    </Link>
                                    <p className="at-drawer-item-price">{p.price}</p>
                                </div>
                                <button
                                    className="at-icon-btn"
                                    type="button"
                                    onClick={() => toggleSaved('wishlist', p)}
                                    aria-label={'Remove ' + p.name}
                                >
                                    <X size={16} />
                                </button>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </dialog>
    );
}
