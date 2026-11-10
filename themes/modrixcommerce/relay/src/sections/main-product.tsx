import { Form, Link } from '@inertiajs/react';
import { useEffect, useId, useState } from 'react';
import { Minus, Plus } from 'lucide-react';
import CartController from '@/actions/App/Http/Controllers/Storefront/CartController';
import {
    blockAttributes,
    type ThemeSectionProps,
} from '@/components/storefront/theme-sections';
import type { ThemeProductProps } from '@/lib/theme-pages';
import { PlaceSlot, usePlaceItems } from '@/components/extensions/place-slot';
import { ProductDescription } from '@/components/storefront/product-description';
import { VariantSelector } from '@/components/storefront/variant-selector';
import { BuyNowButton } from '@/components/storefront/buy-now-button';
import { StickyAddToCart } from '@/components/storefront/sticky-add-to-cart';
import InputError from '@/components/input-error';
import { ProductLabels } from '@/components/storefront/product-labels';
import { SaleNotice } from '@/components/storefront/sale-notice';
import { Card, str, icons, rememberViewed, SaveActions } from '../components';
import ProductShare from '../share-product';
import { ExtensionSlot } from '@/components/extensions/extension-slot';
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
    const [tab, setTab] = useState(0);
    const id = useId();
    useEffect(
        () =>
            rememberViewed({
                id: product.id,
                name: product.name,
                slug: product.slug,
                image: product.images[0]?.url ?? '',
                price: variant?.price?.formatted ?? '',
                stock: variant?.purchasable ?? false,
                description: product.short_description ?? '',
            }),
        [
            product.id,
            product.name,
            product.slug,
            product.images,
            product.short_description,
            variant?.price?.formatted,
            variant?.purchasable,
        ],
    );
    const maximum =
        variant?.available !== null && variant?.available !== undefined
            ? Math.max(1, variant.available)
            : 999;
    const count = Math.max(1, Math.min(maximum, Math.floor(quantity) || 1));
    const image = product.images[imageIndex] ?? product.images[0];
    const detailBlocks = usePlaceItems('product.after_description');
    const tabs = [
        str(s.description_label),
        str(s.specifications_label),
        str(s.delivery_label),
        ...(detailBlocks.length
            ? [str(s.extensions_label) || 'Reviews & details']
            : []),
    ];
    const recommendations = page.related ?? [];
    return (
        <>
            <nav className="re-breadcrumb" aria-label="Breadcrumb">
                <div className="re-frame">
                    <Link href="/">Home</Link>
                    <span>/</span>
                    <Link href="/search">Products</Link>
                    {page.breadcrumbs.map((c) => (
                        <span key={c.slug}>
                            {' '}
                            / <Link href={'/c/' + c.slug}>{c.name}</Link>
                        </span>
                    ))}
                    <span>/</span>
                    {product.name}
                </div>
            </nav>
            <div className="re-frame re-product">
                <div className="re-gallery">
                    <div className="re-thumbs" aria-label="Product gallery">
                        {product.images.map((img, i) => (
                            <button
                                type="button"
                                key={img.id}
                                className="re-thumb"
                                aria-label={'View image ' + (i + 1)}
                                aria-pressed={i === imageIndex}
                                onClick={() => onImage(i)}
                            >
                                <img
                                    src={img.url}
                                    alt=""
                                    loading={i ? 'lazy' : 'eager'}
                                />
                            </button>
                        ))}
                    </div>
                    <div className="re-main-image">
                        <ProductLabels labels={product.labels} />
                        {image ? (
                            <img
                                src={image.url}
                                alt={image.alt ?? product.name}
                                fetchPriority="high"
                            />
                        ) : (
                            <p className="re-muted">No image available</p>
                        )}
                    </div>
                </div>
                <div className="re-product-summary">
                    <h1>{product.name}</h1>
                    <PlaceSlot place="product.after_title" />
                    {product.brand && (
                        <div className="re-product-meta">
                            Brand:{' '}
                            <Link href={'/b/' + product.brand.slug}>
                                {product.brand.name}
                            </Link>
                        </div>
                    )}
                    <p className="re-product-price store-price store-price-sale">
                        {variant?.price?.formatted ??
                            'Choose options to see the price.'}
                        {variant?.compare_at_price && (
                            <s>{variant.compare_at_price.formatted}</s>
                        )}
                    </p>
                    <SaleNotice sale={product.sale} />
                    <PlaceSlot place="product.after_price" />
                    {product.short_description && (
                        <p className="re-muted">{product.short_description}</p>
                    )}
                    <StickyAddToCart
                        targetId="re-product-purchase"
                        name={product.name}
                        image={product.images[0]?.url}
                        variant={variant}
                        options={product.options}
                        selected={selected}
                        onOption={onOption}
                        hasOptions={product.options.length > 0}
                        showBuyButton={product.show_buy_button}
                    />
                    <div
                        id="re-product-purchase"
                        className="re-product-options"
                    >
                        {product.options.map((option) => (
                            <div key={option.id} style={{ marginBottom: 18 }}>
                                <VariantSelector
                                    option={option}
                                    value={selected[option.id]}
                                    onChange={(v) => onOption(option.id, v)}
                                />
                            </div>
                        ))}
                        <p className="re-stock-note" role="status">
                            {variant?.purchasable
                                ? variant.available === null
                                    ? 'Available to order'
                                    : variant.available + ' available'
                                : (variant?.message ??
                                  'This combination is unavailable.')}
                        </p>
                        {variant && (
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
                                        <label
                                            htmlFor={id + '-quantity'}
                                            className="re-qty-label"
                                        >
                                            Quantity
                                        </label>
                                        <div className="re-purchase-row">
                                            <div className="re-quantity">
                                                <button
                                                    type="button"
                                                    aria-label="Decrease quantity"
                                                    disabled={
                                                        !variant.purchasable ||
                                                        count === 1
                                                    }
                                                    onClick={() =>
                                                        setQuantity(
                                                            Math.max(
                                                                1,
                                                                count - 1,
                                                            ),
                                                        )
                                                    }
                                                >
                                                    <Minus size={13} />
                                                </button>
                                                <input
                                                    id={id + '-quantity'}
                                                    name="quantity"
                                                    type="number"
                                                    min="1"
                                                    max={maximum}
                                                    step="1"
                                                    value={count}
                                                    disabled={
                                                        !variant.purchasable
                                                    }
                                                    onChange={(e) =>
                                                        setQuantity(
                                                            Number(
                                                                e.target.value,
                                                            ) || 1,
                                                        )
                                                    }
                                                />
                                                <button
                                                    type="button"
                                                    aria-label="Increase quantity"
                                                    disabled={
                                                        !variant.purchasable ||
                                                        count >= maximum
                                                    }
                                                    onClick={() =>
                                                        setQuantity(
                                                            Math.min(
                                                                maximum,
                                                                count + 1,
                                                            ),
                                                        )
                                                    }
                                                >
                                                    <Plus size={13} />
                                                </button>
                                            </div>
                                            <button
                                                className="re-btn re-btn-dark store-button"
                                                type="submit"
                                                disabled={
                                                    processing ||
                                                    !variant.purchasable
                                                }
                                            >
                                                {processing
                                                    ? 'Adding…'
                                                    : str(s.add_label)}
                                            </button>
                                            <BuyNowButton
                                                showBuyButton={
                                                    product.show_buy_button
                                                }
                                                disabled={
                                                    processing ||
                                                    !variant.purchasable
                                                }
                                                label={str(s.buy_label)}
                                            />
                                            <div className="re-product-saving">
                                                <SaveActions
                                                    item={{
                                                        id: product.id,
                                                        name: product.name,
                                                        slug: product.slug,
                                                        image:
                                                            product.images[0]
                                                                ?.url ?? '',
                                                        price:
                                                            variant?.price
                                                                ?.formatted ??
                                                            '',
                                                        stock:
                                                            variant?.purchasable ??
                                                            false,
                                                        description:
                                                            product.short_description ??
                                                            '',
                                                    }}
                                                />
                                            </div>
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
                        )}
                    </div>

                    <div className="re-product-sku">
                        SKU: {variant?.sku ?? '—'}
                        {product.brand && <p>Brand: {product.brand.name}</p>}
                    </div>
                    <ProductShare name={product.name} />
                </div>
                {s.show_service_rail !== false && (
                    <aside className="re-product-rail">
                        <div className="re-rail-card">
                            {section.blocks.map((b) => {
                                const Icon =
                                    icons[
                                        str(
                                            b.settings.icon,
                                        ) as keyof typeof icons
                                    ] ?? icons.delivery;
                                return (
                                    <div
                                        className="re-rail-line"
                                        key={b.id}
                                        {...blockAttributes(b)}
                                    >
                                        <Icon size={22} />
                                        <span>{str(b.settings.heading)}</span>
                                    </div>
                                );
                            })}
                        </div>
                        {str(s.promo_image) && (
                            <Link
                                href={str(s.promo_link) || '/search'}
                                className="re-promo re-rail-promo"
                            >
                                <img src={str(s.promo_image)} alt="" />
                                <div>
                                    <h3>{str(s.promo_heading)}</h3>
                                    <span className="re-btn store-button">
                                        Explore
                                    </span>
                                </div>
                            </Link>
                        )}
                    </aside>
                )}
            </div>
            <div className="re-frame re-product-module-area">
                <div>
                    <PlaceSlot
                        place="product.after_add_to_cart"
                        extraProps={{ variant }}
                    />
                </div>
            </div>
            <div className="re-frame re-detail-tabs">
                <div
                    className="re-tabs"
                    role="tablist"
                    aria-label="Product information"
                >
                    {tabs.map((label, i) => (
                        <button
                            type="button"
                            key={i}
                            id={id + '-tab-' + i}
                            role="tab"
                            aria-controls={id + '-panel-' + i}
                            aria-selected={tab === i}
                            tabIndex={tab === i ? 0 : -1}
                            onClick={() => setTab(i)}
                            onKeyDown={(e) => {
                                if (
                                    [
                                        'ArrowLeft',
                                        'ArrowRight',
                                        'Home',
                                        'End',
                                    ].includes(e.key)
                                ) {
                                    e.preventDefault();
                                    const next =
                                        e.key === 'Home'
                                            ? 0
                                            : e.key === 'End'
                                              ? tabs.length - 1
                                              : (i +
                                                    (e.key === 'ArrowRight'
                                                        ? 1
                                                        : tabs.length - 1)) %
                                                tabs.length;
                                    setTab(next);
                                    document
                                        .getElementById(id + '-tab-' + next)
                                        ?.focus();
                                }
                            }}
                        >
                            {label}
                        </button>
                    ))}
                </div>
                {tabs.map((_, i) => (
                    <div
                        key={i}
                        role="tabpanel"
                        id={id + '-panel-' + i}
                        aria-labelledby={id + '-tab-' + i}
                        hidden={tab !== i}
                        tabIndex={0}
                    >
                        {i === 0 ? (
                            <div className="re-description">
                                <ProductDescription product={product} />
                                {!product.description &&
                                    !product.description_html && (
                                        <p>
                                            Product details are being updated.
                                        </p>
                                    )}
                            </div>
                        ) : i === 1 ? (
                            <table className="re-specs">
                                <tbody>
                                    <tr>
                                        <th>Product</th>
                                        <td>{product.name}</td>
                                    </tr>
                                    <tr>
                                        <th>SKU</th>
                                        <td>{variant?.sku ?? '—'}</td>
                                    </tr>
                                    {product.brand && (
                                        <tr>
                                            <th>Brand</th>
                                            <td>{product.brand.name}</td>
                                        </tr>
                                    )}
                                    {product.options.map((o) => (
                                        <tr key={o.id}>
                                            <th>{o.name}</th>
                                            <td>
                                                {o.values.find(
                                                    (v) =>
                                                        v.id === selected[o.id],
                                                )?.value ?? 'Choose an option'}
                                            </td>
                                        </tr>
                                    ))}
                                    <tr>
                                        <th>Availability</th>
                                        <td>
                                            {variant?.purchasable
                                                ? 'Available'
                                                : 'Unavailable'}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        ) : i === 2 ? (
                            <p className="re-description">
                                {str(s.delivery_text)}
                            </p>
                        ) : (
                            <ExtensionSlot items={detailBlocks} />
                        )}
                    </div>
                ))}
            </div>
            {/* After-details modules (reviews, bundles) follow the description. */}
            <div className="re-frame re-product-module-area">
                <div>
                    <ExtensionSlot
                        items={page.slots}
                        extraProps={{ variant }}
                    />
                </div>
            </div>
            {recommendations.length > 0 && (
                <section className="re-frame re-section">
                    <div className="re-section-head">
                        <h2>{str(s.related_heading)}</h2>
                    </div>
                    <div className="re-product-grid">
                        {recommendations.slice(0, 6).map((p) => (
                            <Card key={p.id} product={p} />
                        ))}
                    </div>
                </section>
            )}
        </>
    );
}
