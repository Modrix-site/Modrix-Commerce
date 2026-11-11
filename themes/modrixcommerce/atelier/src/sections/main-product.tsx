import { Form, Link } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import {
    Minus,
    Plus,
    Maximize2,
    X,
    Check,
    Heart,
    ChevronDown,
} from 'lucide-react';
import '../style.css';
import CartController from '@/actions/App/Http/Controllers/Storefront/CartController';
import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
import type { ThemeProductProps } from '@/lib/theme-pages';
import { PlaceSlot } from '@/components/extensions/place-slot';
import { ExtensionSlot } from '@/components/extensions/extension-slot';
import { ProductDescription } from '@/components/storefront/product-description';
import { VariantSelector } from '@/components/storefront/variant-selector';
import { BuyNowButton } from '@/components/storefront/buy-now-button';
import { StickyAddToCart } from '@/components/storefront/sticky-add-to-cart';
import { ProductLabels } from '@/components/storefront/product-labels';
import { SaleNotice } from '@/components/storefront/sale-notice';
import InputError from '@/components/input-error';
import {
    Card,
    str,
    rememberViewed,
    toggleSaved,
    useDeviceList,
} from '../components';

type Props = ThemeSectionProps<Record<string, unknown>, ThemeProductProps>;

export default function MainProduct(props: Props) {
    return props.page?.product ? (
        <Product key={props.page.product.id} {...props} />
    ) : null;
}

function Product({ section, page }: Props) {
    const s = section.settings;
    const { product, variant, imageIndex, onImage, selected, onOption } = page;
    const [quantity, setQuantity] = useState(1);
    const [zoom, setZoom] = useState(false);
    const zoomDialog = useRef<HTMLDialogElement>(null);

    const saved = useDeviceList('wishlist');
    const isSaved = saved.some((x) => x.id === product.id);

    const max = variant?.available ?? 999;
    const count = Math.max(
        1,
        Math.min(Math.max(1, max), Math.floor(quantity) || 1),
    );
    const image = product.images[imageIndex] ?? product.images[0];
    const related = page.related ?? [];

    useEffect(() => {
        rememberViewed({
            id: product.id,
            name: product.name,
            slug: product.slug,
            image: product.images[0]?.url ?? '',
            price: variant?.price?.formatted ?? '',
            stock: variant?.purchasable ?? false,
            description: product.short_description ?? '',
        });
    }, [
        product.id,
        product.name,
        product.slug,
        product.images,
        product.short_description,
        variant?.price?.formatted,
        variant?.purchasable,
    ]);

    useEffect(() => {
        const dialog = zoomDialog.current;
        if (zoom && dialog && !dialog.open) dialog.showModal();
    }, [zoom]);

    const galleryWidth = str(s.gallery_width, '56');

    return (
        <div className="at-product-page at-frame">
            {/* Breadcrumb Navigation */}
            <nav className="at-breadcrumb" aria-label="Breadcrumb">
                <Link href="/">Home</Link>
                <span>/</span>
                <Link href="/search">Products</Link>
                {page.breadcrumbs.map((c) => (
                    <span key={c.slug} className="flex items-center gap-2">
                        <span>/</span>
                        <Link href={'/c/' + c.slug}>{c.name}</Link>
                    </span>
                ))}
                <span>/</span>
                <span className="text-neutral-900">{product.name}</span>
            </nav>

            <div
                className="at-product-layout"
                style={
                    {
                        '--at-gallery-w': `${galleryWidth}%`,
                    } as React.CSSProperties
                }
            >
                {/* Product Gallery */}
                <div className="at-gallery">
                    <div className="at-gallery-main">
                        {image ? (
                            <img
                                src={image.url}
                                alt={image.alt ?? product.name}
                                fetchPriority="high"
                            />
                        ) : (
                            <div className="text-sm text-neutral-400">
                                Image not available
                            </div>
                        )}
                        {image && (
                            <button
                                type="button"
                                className="at-zoom-btn"
                                aria-label="Zoom product image"
                                onClick={() => setZoom(true)}
                            >
                                <Maximize2 size={18} />
                            </button>
                        )}
                    </div>

                    {product.images.length > 1 && (
                        <div className="at-gallery-thumbs">
                            {product.images.map((im, i) => (
                                <button
                                    key={im.id}
                                    type="button"
                                    className="at-thumb-btn"
                                    aria-label={'Image ' + (i + 1)}
                                    aria-pressed={imageIndex === i}
                                    onClick={() => onImage(i)}
                                >
                                    <img src={im.url} alt="" />
                                </button>
                            ))}
                        </div>
                    )}
                </div>

                {/* Sticky Purchase Panel */}
                <div className="at-product-summary">
                    <div className="mb-2 flex items-center justify-between">
                        <ProductLabels labels={product.labels} />
                        {s.show_wishlist !== false && (
                            <button
                                type="button"
                                className="at-icon-btn"
                                aria-label={`${isSaved ? 'Remove from favourites' : 'Save to favourites'}: ${product.name}`}
                                aria-pressed={isSaved}
                                onClick={() =>
                                    toggleSaved('wishlist', {
                                        id: product.id,
                                        name: product.name,
                                        slug: product.slug,
                                        image: product.images[0]?.url ?? '',
                                        price: variant?.price?.formatted ?? '',
                                        stock: variant?.purchasable ?? false,
                                        description:
                                            product.short_description ?? '',
                                    })
                                }
                            >
                                <Heart
                                    size={20}
                                    fill={isSaved ? 'currentColor' : 'none'}
                                />
                            </button>
                        )}
                    </div>

                    <h1>{product.name}</h1>
                    <PlaceSlot
                        place="product.after_title"
                        extraProps={{ variant }}
                    />

                    <SaleNotice sale={product.sale} />

                    <div className="at-product-price-box">
                        <span>
                            {variant?.price?.formatted ??
                                str(s.choose_label, 'Choose options')}
                        </span>
                        {variant?.compare_at_price && (
                            <s>{variant.compare_at_price.formatted}</s>
                        )}
                    </div>
                    <PlaceSlot
                        place="product.after_price"
                        extraProps={{ variant }}
                    />

                    {product.short_description && (
                        <p className="at-product-short-desc">
                            {product.short_description}
                        </p>
                    )}

                    {s.show_sku !== false && variant?.sku && (
                        <p className="mb-4 text-xs tracking-wider text-neutral-400 uppercase">
                            SKU: {variant.sku}
                        </p>
                    )}

                    <StickyAddToCart
                        targetId="at-product-purchase"
                        name={product.name}
                        image={product.images[0]?.url}
                        variant={variant}
                        options={product.options}
                        selected={selected}
                        onOption={onOption}
                        hasOptions={product.options.length > 0}
                        showBuyButton={product.show_buy_button}
                    />

                    <div id="at-product-purchase">
                        {product.options.length > 0 && (
                            <div className="mb-6 space-y-4">
                                {product.options.map((o) => (
                                    <VariantSelector
                                        key={o.id}
                                        option={o}
                                        value={selected[o.id]}
                                        onChange={(v) => onOption(o.id, v)}
                                    />
                                ))}
                            </div>
                        )}

                        {variant ? (
                            <Form
                                {...CartController.store.form()}
                                options={{ preserveScroll: true }}
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <input
                                            type="hidden"
                                            name="variant_id"
                                            value={variant.id}
                                        />
                                        <input
                                            type="hidden"
                                            name="quantity"
                                            value={count}
                                        />

                                        <div className="at-product-actions my-4 flex flex-col items-stretch gap-3 sm:flex-row">
                                            {s.show_quantity !== false && (
                                                <div className="at-quantity-picker shrink-0">
                                                    <button
                                                        type="button"
                                                        aria-label="Decrease quantity"
                                                        disabled={count <= 1}
                                                        onClick={() =>
                                                            setQuantity(
                                                                Math.max(
                                                                    1,
                                                                    count - 1,
                                                                ),
                                                            )
                                                        }
                                                    >
                                                        <Minus size={14} />
                                                    </button>
                                                    <input
                                                        aria-label="Quantity"
                                                        type="number"
                                                        min={1}
                                                        max={max}
                                                        value={count}
                                                        onChange={(e) =>
                                                            setQuantity(
                                                                Number(
                                                                    e.target
                                                                        .value,
                                                                ),
                                                            )
                                                        }
                                                    />
                                                    <button
                                                        type="button"
                                                        aria-label="Increase quantity"
                                                        disabled={count >= max}
                                                        onClick={() =>
                                                            setQuantity(
                                                                count + 1,
                                                            )
                                                        }
                                                    >
                                                        <Plus size={14} />
                                                    </button>
                                                </div>
                                            )}

                                            <button
                                                type="submit"
                                                className="at-btn at-btn-primary flex-1 bg-black text-white hover:bg-neutral-800 sm:w-auto"
                                                disabled={
                                                    processing ||
                                                    !variant.purchasable
                                                }
                                            >
                                                {processing
                                                    ? str(
                                                          s.adding_label,
                                                          'Adding…',
                                                      )
                                                    : !variant.purchasable
                                                      ? str(
                                                            s.sold_label,
                                                            'Currently unavailable',
                                                        )
                                                      : str(
                                                            s.add_label,
                                                            'Add to bag',
                                                        )}
                                            </button>

                                            {s.show_buy_now !== false && (
                                                <BuyNowButton
                                                    showBuyButton={
                                                        product.show_buy_button
                                                    }
                                                    disabled={
                                                        processing ||
                                                        !variant.purchasable
                                                    }
                                                    label={str(
                                                        s.buy_label,
                                                        'Buy now',
                                                    )}
                                                    className="at-btn at-btn-secondary !h-[var(--at-button-height,46px)] flex-1 !rounded-none !border-black bg-white !text-black hover:!bg-black hover:!text-white sm:w-auto"
                                                />
                                            )}
                                        </div>

                                        <InputError
                                            message={
                                                errors.variant_id ??
                                                errors.quantity
                                            }
                                        />
                                    </>
                                )}
                            </Form>
                        ) : (
                            <p className="mb-4 text-sm text-neutral-500">
                                {str(s.choose_label, 'Choose options')}
                            </p>
                        )}
                    </div>

                    {s.show_stock !== false && (
                        <p className="at-stock-status">
                            <Check size={15} />
                            <span>
                                {variant?.purchasable
                                    ? 'Available now'
                                    : 'Currently unavailable'}
                            </span>
                        </p>
                    )}

                    {/* Slot for Buy Together & Save, Protection Plans, etc. */}
                    <PlaceSlot
                        place="product.after_add_to_cart"
                        extraProps={{ variant }}
                    />

                    {/* Accordions */}
                    <div className="at-accordions">
                        <details className="at-accordion" open>
                            <summary>
                                <span>{str(s.details_heading, 'Details')}</span>
                                <ChevronDown size={15} />
                            </summary>
                            <div className="at-accordion-content">
                                <ProductDescription product={product} />
                                <PlaceSlot
                                    place="product.after_description"
                                    extraProps={{ variant }}
                                />
                            </div>
                        </details>

                        {str(s.delivery_text) && (
                            <details className="at-accordion">
                                <summary>
                                    <span>
                                        {str(
                                            s.delivery_heading,
                                            'Delivery & returns',
                                        )}
                                    </span>
                                    <ChevronDown size={15} />
                                </summary>
                                <div className="at-accordion-content">
                                    <p>{str(s.delivery_text)}</p>
                                </div>
                            </details>
                        )}

                        {str(s.services_text) && (
                            <details className="at-accordion">
                                <summary>
                                    <span>
                                        {str(
                                            s.services_heading,
                                            'Care & services',
                                        )}
                                    </span>
                                    <ChevronDown size={15} />
                                </summary>
                                <div className="at-accordion-content">
                                    <p>{str(s.services_text)}</p>
                                </div>
                            </details>
                        )}

                        {section.blocks.map((b) => (
                            <details className="at-accordion" key={b.id}>
                                <summary>
                                    <span>{str(b.settings.heading)}</span>
                                    <ChevronDown size={15} />
                                </summary>
                                <div className="at-accordion-content">
                                    <p>{str(b.settings.body)}</p>
                                </div>
                            </details>
                        ))}
                    </div>
                </div>
            </div>

            {/* Below-the-fold full-width container for bundles, reviews and recommendations */}
            <div className="at-product-modules">
                <ExtensionSlot items={page.slots} extraProps={{ variant }} />

                {related.length > 0 && (
                    <div className="mt-16">
                        <div className="at-section-head">
                            <h2>
                                {str(s.related_heading, 'You may also like')}
                            </h2>
                        </div>
                        <div className="at-product-grid">
                            {related
                                .slice(0, Number(s.related_limit) || 4)
                                .map((p) => (
                                    <Card key={p.id} product={p} quickAdd />
                                ))}
                        </div>
                    </div>
                )}
            </div>

            {/* Image Zoom Modal */}
            {zoom && image && (
                <dialog
                    ref={zoomDialog}
                    aria-label="Enlarged product image"
                    className="max-h-[90vh] max-w-4xl bg-white p-6 shadow-2xl backdrop:bg-black/50"
                    onClose={() => setZoom(false)}
                    onClick={(e) => {
                        if (e.target === zoomDialog.current) setZoom(false);
                    }}
                >
                    <div className="mb-2 flex justify-end">
                        <button
                            type="button"
                            className="at-icon-btn"
                            onClick={() => setZoom(false)}
                        >
                            <X size={20} />
                        </button>
                    </div>
                    <img
                        src={image.url}
                        alt={image.alt ?? product.name}
                        className="mx-auto max-h-[75vh] w-auto object-contain"
                    />
                </dialog>
            )}
        </div>
    );
}
