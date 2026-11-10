import { Form, Link } from '@inertiajs/react';
import { useEffect, useId, useState } from 'react';
import { Minus, Plus } from 'lucide-react';
import CartController from '@/actions/App/Http/Controllers/Storefront/CartController';
import {
    blockAttributes,
    type ThemeSectionProps,
} from '@/components/storefront/theme-sections';
import type { ThemeProductProps } from '@/lib/theme-pages';
import { PlaceSlot } from '@/components/extensions/place-slot';
import { ExtensionSlot } from '@/components/extensions/extension-slot';
import { ProductDescription } from '@/components/storefront/product-description';
import { VariantSelector } from '@/components/storefront/variant-selector';
import { BuyNowButton } from '@/components/storefront/buy-now-button';
import { StickyAddToCart } from '@/components/storefront/sticky-add-to-cart';
import InputError from '@/components/input-error';
import { ProductLabels } from '@/components/storefront/product-labels';
import { SaleNotice } from '@/components/storefront/sale-notice';
import { Card, str, icons, rememberViewed } from '../components';
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
    const tabs = [
        str(s.description_label),
        str(s.specifications_label),
        str(s.delivery_label),
    ];
    const recommendations = page.related ?? [];
    return (
        <>
            <nav className="bl-breadcrumb" aria-label="Breadcrumb">
                <div className="bl-frame">
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
            <div className="bl-frame bl-product">
                <div className="bl-gallery">
                    <div className="bl-thumbs" aria-label="Product gallery">
                        {product.images.map((img, i) => (
                            <button
                                type="button"
                                key={img.id}
                                className="bl-thumb"
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
                    <div className="bl-main-image">
                        <ProductLabels labels={product.labels} />
                        {image ? (
                            <img
                                src={image.url}
                                alt={image.alt ?? product.name}
                                fetchPriority="high"
                            />
                        ) : (
                            <p className="bl-muted">No image available</p>
                        )}
                    </div>
                </div>
                <div className="bl-product-summary">
                    <h1>{product.name}</h1>
                    <PlaceSlot place="product.after_title" />
                    {product.brand && (
                        <div className="bl-product-meta">
                            Brand:{' '}
                            <Link href={'/b/' + product.brand.slug}>
                                {product.brand.name}
                            </Link>
                        </div>
                    )}
                    <p className="bl-product-price store-price store-price-sale">
                        {variant?.price?.formatted ??
                            'Choose options to see the price.'}
                        {variant?.compare_at_price && (
                            <s>{variant.compare_at_price.formatted}</s>
                        )}
                    </p>
                    <SaleNotice sale={product.sale} />
                    <PlaceSlot place="product.after_price" />
                    {product.short_description && (
                        <p className="bl-muted">{product.short_description}</p>
                    )}
                    <StickyAddToCart
                        targetId="bl-product-purchase"
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
                        id="bl-product-purchase"
                        className="bl-product-options"
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
                        <p className="bl-stock-note" role="status">
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
                                            className="bl-qty-label"
                                        >
                                            Quantity
                                        </label>
                                        <div className="bl-purchase-row">
                                            <div className="bl-quantity">
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
                                                className="bl-btn bl-btn-dark store-button"
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
                    <PlaceSlot place="product.after_add_to_cart" />
                    <div className="bl-product-sku">
                        SKU: {variant?.sku ?? '—'}
                        {product.brand && <p>Brand: {product.brand.name}</p>}
                    </div>
                </div>
                {s.show_service_rail !== false && (
                    <aside className="bl-product-rail">
                        <div className="bl-rail-card">
                            {section.blocks.map((b) => {
                                const Icon =
                                    icons[
                                        str(
                                            b.settings.icon,
                                        ) as keyof typeof icons
                                    ] ?? icons.delivery;
                                return (
                                    <div
                                        className="bl-rail-line"
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
                                className="bl-promo bl-rail-promo"
                            >
                                <img src={str(s.promo_image)} alt="" />
                                <div>
                                    <h3>{str(s.promo_heading)}</h3>
                                    <span className="bl-btn store-button">
                                        Explore
                                    </span>
                                </div>
                            </Link>
                        )}
                    </aside>
                )}
            </div>
            <div className="bl-frame bl-detail-tabs">
                <div
                    className="bl-tabs"
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
                                              ? 2
                                              : (i +
                                                    (e.key === 'ArrowRight'
                                                        ? 1
                                                        : 2)) %
                                                3;
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
                            <div className="bl-description">
                                <ProductDescription product={product} />
                                {!product.description &&
                                    !product.description_html && (
                                        <p>
                                            Product details are being updated.
                                        </p>
                                    )}
                            </div>
                        ) : i === 1 ? (
                            <table className="bl-specs">
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
                        ) : (
                            <p className="bl-description">
                                {str(s.delivery_text)}
                            </p>
                        )}
                    </div>
                ))}
            </div>
            <div className="bl-frame">
                <PlaceSlot place="product.after_description" />
                {/* After-details modules (reviews, bundles) follow the description. */}
                <ExtensionSlot items={page.slots} extraProps={{ variant }} />
            </div>
            {recommendations.length > 0 && (
                <section className="bl-frame bl-section">
                    <div className="bl-section-head">
                        <h2>{str(s.related_heading)}</h2>
                    </div>
                    <div className="bl-product-grid">
                        {recommendations.slice(0, 6).map((p) => (
                            <Card key={p.id} product={p} />
                        ))}
                    </div>
                </section>
            )}
        </>
    );
}
