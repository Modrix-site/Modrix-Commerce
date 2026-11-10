import { Form, Link, usePage } from '@inertiajs/react';
import {
    Heart,
    ChartNoAxesColumnIncreasing,
    X,
    Truck,
    RotateCcw,
    ShieldCheck,
    Headphones,
    Gift,
    ShoppingCart,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { CSSProperties } from 'react';
import type { ProductCard } from '@/types';
import CartController from '@/actions/App/Http/Controllers/Storefront/CartController';
import InputError from '@/components/input-error';
import { PlaceSlot } from '@/components/extensions/place-slot';
import { ProductLabels } from '@/components/storefront/product-labels';
import { SaleTag } from '@/components/storefront/sale-notice';
export const str = (v: unknown) => (typeof v === 'string' ? v : '');
export const icons = {
    delivery: Truck,
    returns: RotateCcw,
    payment: ShieldCheck,
    support: Headphones,
    gift: Gift,
};
export type Collection = {
    title: string;
    url: string;
    products: ProductCard[];
};
export const collection = (v: unknown) =>
    v && typeof v === 'object' && 'products' in v ? (v as Collection) : null;
export const columns = (n: unknown, fallback = 6) =>
    ({ '--lv-columns': Number(n) || fallback }) as CSSProperties;
export type Saved = {
    id: number;
    name: string;
    slug: string;
    image: string;
    price: string;
    stock: boolean;
    description: string;
};
const key = (kind: string) => 'linden:' + kind;
export function entry(p: ProductCard): Saved {
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
function load(kind: string): Saved[] {
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
    const [items, setItems] = useState<Saved[]>([]);
    useEffect(() => {
        const refresh = () => setItems(load(kind));
        refresh();
        window.addEventListener('linden:lists', refresh);
        window.addEventListener('storage', refresh);
        return () => {
            window.removeEventListener('linden:lists', refresh);
            window.removeEventListener('storage', refresh);
        };
    }, [kind]);
    return items;
}
export function toggleSaved(kind: string, item: Saved) {
    try {
        const list = load(kind);
        const next = list.some((p) => p.id === item.id)
            ? list.filter((p) => p.id !== item.id)
            : [...list, item].slice(kind === 'compare' ? -4 : -50);
        localStorage.setItem(key(kind), JSON.stringify(next));
        window.dispatchEvent(new Event('linden:lists'));
    } catch {
        /* Browsing remains available when device storage is disabled. */
    }
}
export function SaveControls({ product }: { product: ProductCard }) {
    return <SaveActions item={entry(product)} />;
}
export function SaveActions({ item }: { item: Saved }) {
    const saved = useDeviceList('wishlist');
    const compared = useDeviceList('compare');
    return (
        <>
            <button
                type="button"
                className="lv-card-save"
                aria-label={'Save ' + item.name}
                aria-pressed={saved.some((x) => x.id === item.id)}
                onClick={() => toggleSaved('wishlist', item)}
            >
                <Heart size={15} />
            </button>
            <button
                type="button"
                className="lv-card-save"
                aria-label={'Compare ' + item.name}
                aria-pressed={compared.some((x) => x.id === item.id)}
                onClick={() => toggleSaved('compare', item)}
            >
                <ChartNoAxesColumnIncreasing size={15} />
            </button>
        </>
    );
}
export function ProductDrawer({
    kind,
    open,
    onClose,
}: {
    kind: 'wishlist' | 'compare';
    open: boolean;
    onClose: () => void;
}) {
    const items = useDeviceList(kind);
    const dialog = useRef<HTMLDialogElement>(null);
    useEffect(() => {
        if (open && !dialog.current?.open) dialog.current?.showModal();
        if (!open && dialog.current?.open) dialog.current.close();
    }, [open]);
    return (
        <dialog
            ref={dialog}
            className="lv-drawer"
            aria-label={kind === 'wishlist' ? 'Wishlist' : 'Compare products'}
            onClose={onClose}
            onClick={(e) => {
                if (e.target === dialog.current) onClose();
            }}
        >
            <div className="lv-drawer-head">
                <h2>
                    {kind === 'wishlist' ? 'Your wishlist' : 'Compare products'}
                </h2>
                <button
                    className="lv-icon"
                    type="button"
                    aria-label="Close panel"
                    onClick={onClose}
                >
                    <X size={20} />
                </button>
            </div>
            <div className="lv-drawer-body">
                {items.length === 0 ? (
                    <div className="lv-empty">
                        <h3>
                            {kind === 'wishlist'
                                ? 'Your favourites, all together.'
                                : 'Find the right fit.'}
                        </h3>
                        <p>
                            {kind === 'wishlist'
                                ? 'Use the heart on a product to save it here.'
                                : 'Choose up to four products with the compare control.'}
                        </p>
                        <Link
                            className="lv-btn store-button"
                            href="/search"
                            onClick={onClose}
                        >
                            Explore products
                        </Link>
                    </div>
                ) : kind === 'compare' ? (
                    <table className="lv-compare-table">
                        <caption className="lv-muted">
                            Up to four products · saved on this device
                        </caption>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Price</th>
                                <th>Availability</th>
                                <th>
                                    <span className="sr-only">Remove</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {items.map((p) => (
                                <tr key={p.id}>
                                    <td>
                                        <Link
                                            href={'/p/' + p.slug}
                                            onClick={onClose}
                                        >
                                            {p.image && (
                                                <img src={p.image} alt="" />
                                            )}
                                            {p.name}
                                        </Link>
                                    </td>
                                    <td>{p.price}</td>
                                    <td>
                                        {p.stock ? 'In stock' : 'Unavailable'}
                                    </td>
                                    <td>
                                        <button
                                            className="lv-icon"
                                            onClick={() => toggleSaved(kind, p)}
                                            aria-label={'Remove ' + p.name}
                                        >
                                            <X size={14} />
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                ) : (
                    items.map((p) => (
                        <div className="lv-saved-item" key={p.id}>
                            {p.image ? <img src={p.image} alt="" /> : <span />}
                            <div>
                                <Link href={'/p/' + p.slug} onClick={onClose}>
                                    {p.name}
                                </Link>
                                <p>{p.price}</p>
                            </div>
                            <button
                                className="lv-icon"
                                onClick={() => toggleSaved(kind, p)}
                                aria-label={'Remove ' + p.name}
                            >
                                <X size={16} />
                            </button>
                        </div>
                    ))
                )}
                {items.length > 0 && (
                    <p
                        className="lv-muted"
                        style={{ marginTop: 20, fontSize: 12 }}
                    >
                        Saved on this device only.
                    </p>
                )}
            </div>
        </dialog>
    );
}
export function QuickAdd({ product }: { product: ProductCard }) {
    const { storefront } = usePage().props;
    const labels = storefront?.theme?.settings;
    if (!product.in_stock)
        return (
            <button type="button" className="lv-btn" disabled>
                {str(labels?.sold_label) || 'Sold out'}
            </button>
        );
    if (!product.variant_id)
        return (
            <Link className="lv-btn store-button" href={'/p/' + product.slug}>
                {str(labels?.options_label) || 'Choose options'}
            </Link>
        );
    return (
        <Form
            {...CartController.store.form()}
            options={{ preserveScroll: true }}
            className="contents"
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
                        className="lv-btn store-button"
                        type="submit"
                        disabled={processing}
                    >
                        <ShoppingCart size={12} />
                        {processing
                            ? 'Adding…'
                            : str(labels?.cart_label) || 'Add to cart'}
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
    quickAdd = true,
}: {
    product: ProductCard;
    quickAdd?: boolean;
}) {
    const { storefront } = usePage().props;
    const amount = Number(p.price?.amount);
    const was = Number(p.compare_at_price?.amount);
    const discount =
        amount > 0 && was > amount ? Math.round((1 - amount / was) * 100) : 0;
    return (
        <article className="lv-card">
            <Link className="lv-card-image" href={'/p/' + p.slug}>
                {p.image ? (
                    <img
                        src={p.image.url}
                        alt={p.image.alt ?? p.name}
                        loading="lazy"
                    />
                ) : (
                    <span className="lv-empty">No image</span>
                )}
                {discount > 0 && (
                    <span className="lv-card-badge">−{discount}%</span>
                )}
                <ProductLabels labels={p.labels} />
            </Link>
            {p.brand ? (
                <Link className="lv-card-brand" href={'/b/' + p.brand.slug}>
                    {p.brand.name}
                </Link>
            ) : (
                <span className="lv-card-brand">{storefront?.storeName}</span>
            )}
            <Link href={'/p/' + p.slug} className="lv-card-name">
                {p.name}
            </Link>
            <PlaceSlot place="product.card" extraProps={{ product: p }} />
            <p
                className={
                    'lv-card-price store-price ' +
                    (discount ? 'lv-discount store-price-sale' : '')
                }
            >
                {p.has_variants ? 'From ' : ''}
                {p.price?.formatted ?? 'Price unavailable'}
                {p.compare_at_price && <s>{p.compare_at_price.formatted}</s>}
            </p>
            <SaleTag sale={p.sale} className="lv-card-sale" />
            <span className="lv-card-stock">
                {p.in_stock ? 'Available now' : 'Out of stock'}
            </span>
            <div className="lv-card-actions">
                {quickAdd && p.show_buy_button !== false && (
                    <QuickAdd product={p} />
                )}
                <SaveControls product={p} />
            </div>
        </article>
    );
}
export function Countdown({
    endsAt,
    label,
}: {
    endsAt: string;
    label: string;
}) {
    const [now, setNow] = useState<number | null>(null);
    const end = Date.parse(endsAt);
    useEffect(() => {
        setNow(Date.now());
        const id = window.setInterval(() => setNow(Date.now()), 1000);
        return () => window.clearInterval(id);
    }, [endsAt]);
    if (!now || !Number.isFinite(end) || end <= now) return null;
    const s = Math.floor((end - now) / 1000);
    const z = (n: number) => String(n).padStart(2, '0');
    return (
        <span
            className="lv-countdown"
            aria-label={label + ' ' + Math.floor(s / 86400) + ' days'}
        >
            {label} {z(Math.floor(s / 86400))} : {z(Math.floor(s / 3600) % 24)}{' '}
            : {z(Math.floor(s / 60) % 60)} : {z(s % 60)}
        </span>
    );
}

export function rememberViewed(item: Saved) {
    try {
        const list = load('recent').filter((x) => x.id !== item.id);
        localStorage.setItem(
            key('recent'),
            JSON.stringify([item, ...list].slice(0, 12)),
        );
        window.dispatchEvent(new Event('linden:lists'));
    } catch {}
}
