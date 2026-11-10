import { Form, Head } from '@inertiajs/react';
import { PlaceSlot } from '@/components/extensions/place-slot';
import { ImageOff, ShoppingBag } from 'lucide-react';
import { useState } from 'react';
import CartController from '@/actions/App/Http/Controllers/Storefront/CartController';
import InputError from '@/components/input-error';
import { DescriptionPanels } from '@/components/storefront/page-settings/description-panels';
import { StockNote } from '@/components/storefront/page-settings/stock-note';
import { useStoreSettings } from '@/hooks/use-store-settings';
import { ExtensionSlot } from '@/components/extensions/extension-slot';
import { StoreMenuLink as Link } from '@/components/storefront/footer-menus';
import { VariantSelector } from '@/components/storefront/variant-selector';
import { ProductLabels } from '@/components/storefront/product-labels';
import { ProductDescription } from '@/components/storefront/product-description';
import { SaleNotice } from '@/components/storefront/sale-notice';
import { BuyNowButton } from '@/components/storefront/buy-now-button';
import { StickyAddToCart } from '@/components/storefront/sticky-add-to-cart';
import type { ThemeProductProps } from '@/lib/theme-pages';
import { TempoRail, SaveProduct, useTempo } from './components';
import './style.css';

/** The design's "Product details" table: what the shopper picked and can expect. */
function ProductFacts({
    product,
    breadcrumbs,
    variant,
    selected,
    showSku,
}: Pick<
    ThemeProductProps,
    'product' | 'breadcrumbs' | 'variant' | 'selected'
> & { showSku: boolean }) {
    const rows: [string, string][] = [];
    if (product.brand) rows.push(['Brand', product.brand.name]);
    const collection = breadcrumbs[breadcrumbs.length - 1];
    if (collection) rows.push(['Collection', collection.name]);
    for (const option of product.options) {
        const value = option.values.find((v) => v.id === selected[option.id]);
        if (value) rows.push([option.name, value.value]);
    }
    rows.push([
        'Availability',
        !variant
            ? 'Choose your options'
            : variant.purchasable
              ? 'In stock'
              : variant.message || 'Unavailable',
    ]);
    if (variant && showSku) rows.push(['SKU', variant.sku]);
    return (
        <section
            className="tp-product-facts"
            aria-labelledby="tp-product-facts"
        >
            <h2 id="tp-product-facts">Product details</h2>
            <table>
                <tbody>
                    {rows.map(([label, value]) => (
                        <tr key={label}>
                            <th scope="row">{label}</th>
                            <td>{value}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </section>
    );
}

export default function TempoProduct({
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
    const { text } = useTempo();
    const productSettings = useStoreSettings().product;
    const [quantity, setQuantity] = useState(1);
    const maximum = Math.max(1, Math.min(999, variant?.available ?? 999));
    const recommendations = [...upsells, ...related].filter(
        (item, i, all) =>
            item.id !== product.id &&
            all.findIndex((other) => other.id === item.id) === i,
    );
    const currentImage = product.images[imageIndex] ?? product.images[0];
    const gallery = currentImage
        ? [
              currentImage,
              ...product.images.filter((image) => image.id !== currentImage.id),
          ]
        : [];
    return (
        <div className="tp-frame tp-product-page">
            <Head title={product.meta_title}>
                {product.meta_description && (
                    <meta
                        name="description"
                        content={product.meta_description}
                    />
                )}
            </Head>
            <nav className="tp-crumbs" aria-label="Breadcrumb">
                <Link href="/">Home</Link>
                {breadcrumbs.map((item) => (
                    <Link key={item.slug} href={`/c/${item.slug}`}>
                        {item.name}
                    </Link>
                ))}
                <span>{product.name}</span>
            </nav>
            <div
                className="tp-product-layout"
                data-recommendations={recommendations.length > 0}
            >
                <section aria-label="Product images">
                    <div
                        className="tp-gallery-grid"
                        tabIndex={gallery.length > 1 ? 0 : undefined}
                    >
                        {gallery.length ? (
                            gallery.map((image, i) => (
                                <div
                                    className="tp-gallery-cell store-zoom"
                                    key={image.id}
                                >
                                    {i === 0 && (
                                        <ProductLabels
                                            labels={product.labels}
                                        />
                                    )}
                                    <img
                                        src={image.url}
                                        alt={
                                            image.alt ||
                                            `${product.name}, view ${product.images.indexOf(image) + 1}`
                                        }
                                        loading={i === 0 ? 'eager' : 'lazy'}
                                    />
                                </div>
                            ))
                        ) : (
                            <div className="tp-gallery-cell">
                                <ImageOff
                                    size={50}
                                    aria-label="No product image"
                                />
                            </div>
                        )}
                    </div>
                    {gallery.length > 1 && (
                        <div className="tp-gallery-thumbnails">
                            {product.images.map((image, i) => (
                                <button
                                    type="button"
                                    key={image.id}
                                    aria-label={`View product image ${i + 1}`}
                                    aria-pressed={i === imageIndex}
                                    onClick={() => onImage(i)}
                                >
                                    <img
                                        src={image.url}
                                        alt=""
                                        loading="lazy"
                                    />
                                </button>
                            ))}
                        </div>
                    )}
                </section>
                <section className="tp-product-info" data-sticky="true">
                    <h1>{product.name}</h1>
                    <PlaceSlot place="product.after_title" />
                    {product.brand && (
                        <Link
                            className="tp-product-brand"
                            href={`/b/${product.brand.slug}`}
                        >
                            {product.brand.name}
                        </Link>
                    )}
                    <div className="store-price tp-product-price">
                        <strong>
                            {variant?.price?.formatted ?? 'Select an option'}
                        </strong>
                        {variant?.compare_at_price && (
                            <s>{variant.compare_at_price.formatted}</s>
                        )}
                    </div>
                    <SaleNotice sale={product.sale} />
                    <PlaceSlot place="product.after_price" />
                    <Form
                        id="product-add-to-cart"
                        key={variant?.id ?? 'unselected'}
                        {...CartController.store.form()}
                        options={{ preserveScroll: true }}
                    >
                        {({ processing, errors }) => (
                            <>
                                {product.options.map((option) => (
                                    <VariantSelector
                                        key={option.id}
                                        option={option}
                                        value={selected[option.id]}
                                        onChange={(value) =>
                                            onOption(option.id, value)
                                        }
                                    />
                                ))}
                                {variant && (
                                    <input
                                        type="hidden"
                                        name="variant_id"
                                        value={variant.id}
                                    />
                                )}
                                <div
                                    className="tp-quantity"
                                    hidden={!productSettings.quantity}
                                >
                                    <label htmlFor="tempo-quantity">Qty</label>
                                    <div>
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
                                            −
                                        </button>
                                        <input
                                            id="tempo-quantity"
                                            name="quantity"
                                            type="number"
                                            min="1"
                                            max={maximum}
                                            value={Math.min(quantity, maximum)}
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
                                            +
                                        </button>
                                    </div>
                                </div>
                                <p className="tp-product-stock" role="status">
                                    {variant?.purchasable
                                        ? 'Available online'
                                        : variant?.message ||
                                          'This combination is unavailable.'}
                                </p>
                                <div className="tp-purchase-actions">
                                    <button
                                        className="store-button tp-button"
                                        type="submit"
                                        disabled={
                                            processing || !variant?.purchasable
                                        }
                                    >
                                        <ShoppingBag size={20} aria-hidden />
                                        {processing
                                            ? 'Adding…'
                                            : variant?.purchasable
                                              ? text(
                                                    'product_button',
                                                    'Add to Bag',
                                                )
                                              : 'Unavailable'}
                                    </button>
                                    {variant?.purchasable && (
                                        <BuyNowButton
                                            showBuyButton={
                                                product.show_buy_button
                                            }
                                            disabled={processing}
                                            className="store-button tp-outline"
                                        />
                                    )}
                                </div>
                                <InputError
                                    message={
                                        errors.quantity ?? errors.variant_id
                                    }
                                />
                            </>
                        )}
                    </Form>
                    <div className="tp-product-wishlist">
                        <SaveProduct
                            id={product.id}
                            name={product.name}
                            slug={product.slug}
                            label
                        />
                    </div>
                    {text('product_offer') && (
                        <div className="tp-product-offers">
                            {text('product_offer')}
                        </div>
                    )}
                    <PlaceSlot place="product.after_add_to_cart" />
                    <StockNote
                        mode={productSettings.stock_note}
                        quantity={
                            variant?.purchasable ? variant.available : null
                        }
                    />
                    <ProductFacts
                        product={product}
                        breadcrumbs={breadcrumbs}
                        variant={variant}
                        selected={selected}
                        showSku={productSettings.show_sku}
                    />
                </section>
            </div>
            <section className="tp-product-details">
                <h2>Details & Care</h2>
                {product.short_description && (
                    <p>{product.short_description}</p>
                )}
                <DescriptionPanels
                    mode={productSettings.description}
                    sections={
                        product.description
                            ? [
                                  {
                                      id: 'description',
                                      title: 'Description',
                                      content: (
                                          <ProductDescription
                                              product={product}
                                              className="tp-description"
                                          />
                                      ),
                                  },
                              ]
                            : []
                    }
                />
                <PlaceSlot place="product.after_description" />
                <details>
                    <summary>Shipping & Returns</summary>
                    <p>{text('delivery_text')}</p>
                </details>
            </section>
            <ExtensionSlot
                items={slots}
                className="tp-extensions"
                extraProps={{ variant }}
            />
            <TempoRail
                title={text('related_heading', 'Recommended for You')}
                products={recommendations}
            />
            <StickyAddToCart
                targetId="product-add-to-cart"
                name={product.name}
                image={currentImage?.url}
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
