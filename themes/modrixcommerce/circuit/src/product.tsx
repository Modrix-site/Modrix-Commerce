import { Form, Head } from '@inertiajs/react';
import {
    CircleHelp,
    Expand,
    ImageOff,
    Minus,
    PackageCheck,
    Plus,
    Share2,
    TicketPercent,
    Truck,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { CSSProperties, ReactNode } from 'react';
import CartController from '@/actions/App/Http/Controllers/Storefront/CartController';
import { ExtensionSlot } from '@/components/extensions/extension-slot';
import { PlaceSlot } from '@/components/extensions/place-slot';
import InputError from '@/components/input-error';
import { BuyNowButton } from '@/components/storefront/buy-now-button';
import { StoreMenuLink as Link } from '@/components/storefront/footer-menus';
import { ProductLabels } from '@/components/storefront/product-labels';
import { ProductDescription } from '@/components/storefront/product-description';
import { SaleNotice } from '@/components/storefront/sale-notice';
import { StickyAddToCart } from '@/components/storefront/sticky-add-to-cart';
import { VariantSelector } from '@/components/storefront/variant-selector';
import { useStoreSettings } from '@/hooks/use-store-settings';
import type { ThemeProductProps } from '@/lib/theme-pages';
import {
    PaymentBadges,
    ProductRow,
    SaveButton,
    rememberViewed,
    useCircuit,
    useDeviceList,
} from './components';
import './style.css';

function Accordion({
    title,
    children,
    open = false,
}: {
    title: string;
    children: ReactNode;
    open?: boolean;
}) {
    return (
        <details className="ci-accordion" open={open}>
            <summary>
                {title}
                <Plus size={16} aria-hidden className="ci-accordion-plus" />
                <Minus size={16} aria-hidden className="ci-accordion-minus" />
            </summary>
            <div>{children}</div>
        </details>
    );
}

function Marquee({ items }: { items: string[] }) {
    if (!items.length) return null;
    const row = [...items, ...items, ...items];
    return (
        <div className="ci-marquee" aria-label={items.join(', ')}>
            <div aria-hidden>
                {row.map((item, i) => (
                    <span key={i}>{item}</span>
                ))}
            </div>
        </div>
    );
}

const DELIVERY_ICONS = {
    truck: Truck,
    ticket: TicketPercent,
    package: PackageCheck,
};

export default function CircuitProduct({
    product,
    breadcrumbs,
    slots,
    related = [],
    upsells = [],
    variant,
    imageIndex,
    onImage,
    selected,
    onOption,
}: ThemeProductProps) {
    const { text, show, number, list } = useCircuit();
    const productSettings = useStoreSettings().product;
    const delivery = list<{ icon?: string; text?: string }>('delivery', () =>
        ['truck', 'ticket', 'package'].map((icon, i) => ({
            icon,
            text: text(`delivery_${i + 1}`),
        })),
    );
    const [quantity, setQuantity] = useState(1);
    const [zoom, setZoom] = useState(false);
    const [shared, setShared] = useState(false);
    const zoomDialog = useRef<HTMLDialogElement>(null);
    const recent = useDeviceList('recent').filter(
        (item) => item.id !== product.id,
    );
    const maximum = Math.max(1, Math.min(999, variant?.available ?? 999));
    const image = product.images[imageIndex] ?? product.images[0];
    const price = Number(variant?.price?.amount);
    const compare = Number(variant?.compare_at_price?.amount);
    const off =
        compare > price && price > 0
            ? Math.round((1 - price / compare) * 100)
            : null;
    const threshold = number('stock_threshold', 20);
    const lowStock =
        productSettings.stock_note !== 'off' &&
        variant?.purchasable &&
        variant.available !== null &&
        variant.available > 0 &&
        variant.available <= threshold;
    const recommendations = [...upsells, ...related].filter(
        (item, i, all) =>
            item.id !== product.id &&
            all.findIndex((other) => other.id === item.id) === i,
    );
    const sizeOption = product.options.find((option) =>
        /size|storage|capacity|memory/i.test(option.name),
    );

    useEffect(() => {
        rememberViewed({
            id: product.id,
            slug: product.slug,
            name: product.name,
            image: product.images[0]?.url ?? null,
            price: variant?.price?.formatted ?? null,
        });
        // Record each product once per visit.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [product.id]);

    useEffect(() => {
        if (zoom && !zoomDialog.current?.open) zoomDialog.current?.showModal();
        if (!zoom && zoomDialog.current?.open) zoomDialog.current.close();
    }, [zoom]);

    const share = async () => {
        const url = window.location.href;
        try {
            if (navigator.share)
                await navigator.share({ title: product.name, url });
            else {
                await navigator.clipboard.writeText(url);
                setShared(true);
            }
        } catch {
            /* The shopper closed the share sheet. */
        }
    };

    return (
        <div className="ci-frame ci-product-page">
            <Head title={product.meta_title}>
                {product.meta_description && (
                    <meta
                        name="description"
                        content={product.meta_description}
                    />
                )}
            </Head>
            <nav className="ci-crumbs" aria-label="Breadcrumb">
                <Link href="/">{text('home_label', 'Home')}</Link>
                {breadcrumbs.map((item) => (
                    <Link key={item.slug} href={`/c/${item.slug}`}>
                        {item.name}
                    </Link>
                ))}
                <span>{product.name}</span>
            </nav>
            <div className="ci-product">
                <section
                    className="ci-gallery"
                    aria-label="Product images"
                    data-thumbnails={product.images.length > 1}
                >
                    {product.images.length > 1 && (
                        <div className="ci-thumbs">
                            {product.images.map((img, i) => (
                                <button
                                    key={img.id}
                                    type="button"
                                    aria-label={`Show image ${i + 1}`}
                                    aria-pressed={i === imageIndex}
                                    onClick={() => onImage(i)}
                                >
                                    <img src={img.url} alt="" loading="lazy" />
                                </button>
                            ))}
                        </div>
                    )}
                    <div className="ci-main-image">
                        <ProductLabels labels={product.labels} />
                        {image ? (
                            <img
                                src={image.url}
                                alt={image.alt || product.name}
                            />
                        ) : (
                            <ImageOff size={48} aria-label="No product image" />
                        )}
                        {image && (
                            <button
                                type="button"
                                className="ci-zoom"
                                aria-label="Enlarge image"
                                onClick={() => setZoom(true)}
                            >
                                <Expand size={16} />
                            </button>
                        )}
                    </div>
                </section>
                <section className="ci-info" aria-label="Product details">
                    {off !== null && (
                        <span className="ci-badge ci-badge-sale">-{off}%</span>
                    )}
                    {product.brand && (
                        <Link
                            className="ci-info-brand"
                            href={`/b/${product.brand.slug}`}
                        >
                            {product.brand.name}
                        </Link>
                    )}
                    <h1>{product.name}</h1>
                    <PlaceSlot place="product.after_title" />
                    <p className="ci-info-price store-price">
                        <span
                            className={
                                off !== null ? 'ci-price-sale' : undefined
                            }
                        >
                            {variant?.price?.formatted ?? 'Select an option'}
                        </span>
                        {off !== null && (
                            <s>{variant?.compare_at_price?.formatted}</s>
                        )}
                    </p>
                    {product.short_description && (
                        <p className="ci-info-lead">
                            {product.short_description}
                        </p>
                    )}
                    <SaleNotice sale={product.sale} />
                    <PlaceSlot place="product.after_price" />
                    {delivery.some((line) => line.text) && (
                        <ul className="ci-delivery">
                            {delivery.map((line, i) => {
                                const Icon =
                                    DELIVERY_ICONS[
                                        line.icon as keyof typeof DELIVERY_ICONS
                                    ] ?? Truck;
                                return line.text ? (
                                    <li key={i}>
                                        <Icon size={18} aria-hidden />
                                        {line.text}
                                    </li>
                                ) : null;
                            })}
                        </ul>
                    )}
                    <Form
                        id="product-add-to-cart"
                        key={variant?.id ?? 'unselected'}
                        {...CartController.store.form()}
                        options={{ preserveScroll: true }}
                        className="ci-buy"
                    >
                        {({ processing, errors }) => (
                            <>
                                {product.options.map((option) => (
                                    <div key={option.id} className="ci-option">
                                        <VariantSelector
                                            option={option}
                                            value={selected[option.id]}
                                            onChange={(value) =>
                                                onOption(option.id, value)
                                            }
                                        />
                                        {option.id === sizeOption?.id &&
                                            text('size_guide_label') && (
                                                <Link
                                                    className="ci-size-guide"
                                                    href={text(
                                                        'size_guide_link',
                                                        '/search',
                                                    )}
                                                >
                                                    {text('size_guide_label')}
                                                </Link>
                                            )}
                                    </div>
                                ))}
                                {variant && (
                                    <input
                                        type="hidden"
                                        name="variant_id"
                                        value={variant.id}
                                    />
                                )}
                                <div className="ci-social-actions">
                                    {text('ask_link') && (
                                        <Link href={text('ask_link')}>
                                            <CircleHelp size={15} aria-hidden />{' '}
                                            {text(
                                                'ask_label',
                                                'Ask a question',
                                            )}
                                        </Link>
                                    )}
                                    {show('show_share') && (
                                        <button
                                            type="button"
                                            onClick={() => void share()}
                                        >
                                            <Share2 size={15} aria-hidden />{' '}
                                            {shared ? 'Link copied' : 'Share'}
                                        </button>
                                    )}
                                </div>
                                {lowStock && variant && (
                                    <div className="ci-stock">
                                        <p>
                                            Hurry up! Only{' '}
                                            <b>{variant.available} item(s)</b>{' '}
                                            left in stock
                                        </p>
                                        <div
                                            className="ci-stock-bar"
                                            aria-hidden
                                        >
                                            <span
                                                style={{
                                                    width: `${Math.max(6, Math.min(100, ((variant.available ?? 0) / threshold) * 100))}%`,
                                                }}
                                            />
                                        </div>
                                    </div>
                                )}
                                <div className="ci-buy-row">
                                    {productSettings.quantity && (
                                        <div className="ci-qty">
                                            <button
                                                type="button"
                                                aria-label="Decrease quantity"
                                                disabled={
                                                    quantity <= 1 ||
                                                    !variant?.purchasable
                                                }
                                                onClick={() =>
                                                    setQuantity(
                                                        Math.max(
                                                            1,
                                                            Math.min(
                                                                quantity,
                                                                maximum,
                                                            ) - 1,
                                                        ),
                                                    )
                                                }
                                            >
                                                <Minus size={14} />
                                            </button>
                                            <input
                                                name="quantity"
                                                type="number"
                                                aria-label="Quantity"
                                                min="1"
                                                max={maximum}
                                                value={Math.min(
                                                    quantity,
                                                    maximum,
                                                )}
                                                disabled={!variant?.purchasable}
                                                onChange={(event) =>
                                                    setQuantity(
                                                        Math.max(
                                                            1,
                                                            Math.min(
                                                                maximum,
                                                                Number(
                                                                    event.target
                                                                        .value,
                                                                ) || 1,
                                                            ),
                                                        ),
                                                    )
                                                }
                                            />
                                            <button
                                                type="button"
                                                aria-label="Increase quantity"
                                                disabled={
                                                    quantity >= maximum ||
                                                    !variant?.purchasable
                                                }
                                                onClick={() =>
                                                    setQuantity(
                                                        Math.min(
                                                            maximum,
                                                            quantity + 1,
                                                        ),
                                                    )
                                                }
                                            >
                                                <Plus size={14} />
                                            </button>
                                        </div>
                                    )}
                                    {!productSettings.quantity && (
                                        <input
                                            type="hidden"
                                            name="quantity"
                                            value="1"
                                        />
                                    )}
                                    <button
                                        className="store-button ci-button ci-add"
                                        type="submit"
                                        disabled={
                                            processing || !variant?.purchasable
                                        }
                                    >
                                        {processing
                                            ? 'Adding…'
                                            : variant?.purchasable
                                              ? 'Add to Cart'
                                              : variant?.message ||
                                                'Unavailable'}
                                    </button>
                                    <SaveButton
                                        product={{
                                            id: product.id,
                                            slug: product.slug,
                                            name: product.name,
                                            image:
                                                product.images[0]?.url ?? null,
                                            price:
                                                variant?.price?.formatted ??
                                                null,
                                        }}
                                        className="ci-round"
                                    />
                                </div>
                                {variant?.purchasable && (
                                    <BuyNowButton
                                        showBuyButton={product.show_buy_button}
                                        disabled={processing}
                                        className="store-button ci-button ci-buy-now"
                                    />
                                )}
                                <InputError
                                    message={
                                        errors.quantity ?? errors.variant_id
                                    }
                                />
                            </>
                        )}
                    </Form>
                    <PlaceSlot place="product.after_add_to_cart" />
                    <dl className="ci-meta">
                        {variant && productSettings.show_sku && (
                            <>
                                <dt>{text('sku_label', 'Sku:')}</dt>
                                <dd>{variant.sku}</dd>
                            </>
                        )}
                        <dt>{text('available_label', 'Available:')}</dt>
                        <dd>
                            {variant?.purchasable
                                ? 'In stock'
                                : variant
                                  ? 'Out of stock'
                                  : 'Choose options'}
                        </dd>
                        {breadcrumbs.length > 0 && (
                            <>
                                <dt>
                                    {text('collections_label', 'Collections:')}
                                </dt>
                                <dd>
                                    {breadcrumbs.map((item, i) => (
                                        <span key={item.slug}>
                                            {i > 0 && ', '}
                                            <Link href={`/c/${item.slug}`}>
                                                {item.name}
                                            </Link>
                                        </span>
                                    ))}
                                </dd>
                            </>
                        )}
                        {product.brand && (
                            <>
                                <dt>{text('brand_label', 'Brand:')}</dt>
                                <dd>{product.brand.name}</dd>
                            </>
                        )}
                    </dl>
                    {text('payment_labels') && (
                        <div className="ci-safe-checkout">
                            <p>
                                {text(
                                    'safe_checkout_label',
                                    'Guarantee Safe Checkout:',
                                )}
                            </p>
                            <PaymentBadges labels={text('payment_labels')} />
                        </div>
                    )}
                    <div className="ci-accordions">
                        {product.description && (
                            <Accordion
                                title={text('description_label', 'Description')}
                                open={productSettings.description === 'open'}
                            >
                                <ProductDescription
                                    product={product}
                                    className="ci-description"
                                />
                            </Accordion>
                        )}
                        {text('shipping_text') && (
                            <Accordion
                                title={text(
                                    'shipping_label',
                                    'Shipping and Returns',
                                )}
                            >
                                <p>{text('shipping_text')}</p>
                            </Accordion>
                        )}
                        {text('returns_text') && (
                            <Accordion
                                title={text('returns_label', 'Return Policies')}
                            >
                                <p>{text('returns_text')}</p>
                            </Accordion>
                        )}
                    </div>
                    <PlaceSlot place="product.after_description" />
                </section>
            </div>
            <Marquee
                items={text('marquee')
                    .split('|')
                    .map((item) => item.trim())
                    .filter(Boolean)}
            />
            <ExtensionSlot
                items={slots}
                className="ci-extensions"
                extraProps={{ variant }}
            />
            {productSettings.related && recommendations.length > 0 && (
                <section
                    className="ci-section ci-centered-section"
                    aria-label={text('related_heading', 'People Also Bought')}
                >
                    <h2>{text('related_heading', 'People Also Bought')}</h2>
                    {text('related_text') && <p>{text('related_text')}</p>}
                    <ProductRow
                        products={recommendations.slice(
                            0,
                            productSettings.related_count,
                        )}
                        columns={4}
                    />
                </section>
            )}
            {recent.length > 0 && (
                <section
                    className="ci-section ci-centered-section"
                    aria-label={text('recent_heading', 'Recently Viewed')}
                >
                    <h2>{text('recent_heading', 'Recently Viewed')}</h2>
                    {text('recent_text') && <p>{text('recent_text')}</p>}
                    <div
                        className="ci-row"
                        style={{ '--ci-row-columns': 4 } as CSSProperties}
                    >
                        {recent.slice(0, 4).map((item) => (
                            <article
                                key={item.id}
                                className="ci-card"
                                data-variant="compact"
                            >
                                <div className="ci-card-photo">
                                    <Link
                                        href={`/p/${item.slug}`}
                                        aria-label={item.name}
                                        tabIndex={-1}
                                    >
                                        {item.image ? (
                                            <img
                                                src={item.image}
                                                alt=""
                                                loading="lazy"
                                            />
                                        ) : (
                                            <ImageOff size={28} aria-hidden />
                                        )}
                                    </Link>
                                </div>
                                <div className="ci-card-copy">
                                    <h3>
                                        <Link href={`/p/${item.slug}`}>
                                            {item.name}
                                        </Link>
                                    </h3>
                                    {item.price && (
                                        <p className="ci-price">{item.price}</p>
                                    )}
                                </div>
                            </article>
                        ))}
                    </div>
                </section>
            )}
            <dialog
                ref={zoomDialog}
                className="ci-zoom-dialog"
                aria-label={`${product.name}, enlarged`}
                onClose={() => setZoom(false)}
                onClick={() => setZoom(false)}
            >
                <button
                    type="button"
                    aria-label="Close"
                    onClick={() => setZoom(false)}
                >
                    <X size={22} />
                </button>
                {image && (
                    <img src={image.url} alt={image.alt || product.name} />
                )}
            </dialog>
            <StickyAddToCart
                targetId="product-add-to-cart"
                name={product.name}
                image={image?.url}
                variant={variant}
                options={product.options}
                selected={selected}
                onOption={onOption}
                hasOptions={product.options.length > 0}
                showBuyButton={product.show_buy_button}
            />
        </div>
    );
}
