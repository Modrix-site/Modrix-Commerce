import { Form, Head } from '@inertiajs/react';
import { PlaceSlot } from '@/components/extensions/place-slot';
import { ImageOff } from 'lucide-react';
import CartController from '@/actions/App/Http/Controllers/Storefront/CartController';
import { StoreMenuLink as Link } from '@/components/storefront/footer-menus';
import { VariantSelector } from '@/components/storefront/variant-selector';
import { ExtensionSlot } from '@/components/extensions/extension-slot';
import { BuyNowButton } from '@/components/storefront/buy-now-button';
import { StickyAddToCart } from '@/components/storefront/sticky-add-to-cart';
import { ProductDescription } from '@/components/storefront/product-description';
import { SaleNotice } from '@/components/storefront/sale-notice';
import InputError from '@/components/input-error';
import { DescriptionPanels } from '@/components/storefront/page-settings/description-panels';
import { StockNote } from '@/components/storefront/page-settings/stock-note';
import { useStoreSettings } from '@/hooks/use-store-settings';
import type { ThemeProductProps } from '@/lib/theme-pages';
import { VoltoraRail, useVoltora } from './components';
import './style.css';
export default function VoltoraProduct({
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
    const { text, show } = useVoltora();
    const productSettings = useStoreSettings().product;
    const image = product.images[imageIndex];
    return (
        <>
            <Head title={product.meta_title}>
                {product.meta_description && (
                    <meta
                        name="description"
                        content={product.meta_description}
                    />
                )}
            </Head>
            <div className="voltora-wrap">
                <nav className="voltora-breadcrumb" aria-label="Breadcrumb">
                    <Link href="/">Home</Link>
                    <span>/</span>
                    <Link href="/search">Catalogue</Link>
                    {breadcrumbs.map((item) => (
                        <span key={item.slug}>
                            {' '}
                            / <Link href={`/c/${item.slug}`}>{item.name}</Link>
                        </span>
                    ))}
                </nav>
                <div className="voltora-product">
                    <div className="voltora-gallery">
                        <div
                            className="voltora-main-photo store-zoom"
                            style={{
                                height: `${text('product_image_height', '420')}px`,
                            }}
                        >
                            {image ? (
                                <img
                                    src={image.url}
                                    alt={image.alt || product.name}
                                />
                            ) : (
                                <ImageOff size={48} />
                            )}
                        </div>
                        {product.images.length > 1 && (
                            <div className="voltora-thumbnails">
                                {product.images.map((photo, i) => (
                                    <button
                                        key={photo.id}
                                        aria-label={`View product image ${i + 1}`}
                                        aria-pressed={i === imageIndex}
                                        onClick={() => onImage(i)}
                                    >
                                        <img
                                            src={photo.url}
                                            alt=""
                                            loading="lazy"
                                        />
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>
                    <div className="voltora-product-info">
                        <p className="voltora-product-brand">
                            {product.brand?.name}
                        </p>
                        <h1>{product.name}</h1>
                        <PlaceSlot place="product.after_title" />

                        <SaleNotice sale={product.sale} className="mb-4" />
                        <PlaceSlot place="product.after_price" />

                        {product.options.map((option) => (
                            <VariantSelector
                                key={option.id}
                                option={option}
                                value={selected[option.id]}
                                onChange={(value) => onOption(option.id, value)}
                            />
                        ))}
                    </div>
                    <aside className="voltora-purchase">
                        <p className="voltora-product-price">
                            {variant?.price?.formatted ?? 'Select an option'}{' '}
                            {variant?.compare_at_price && (
                                <s>{variant.compare_at_price.formatted}</s>
                            )}
                        </p>
                        <p className="voltora-availability">
                            {variant?.purchasable
                                ? variant.available !== null
                                    ? 'In stock'
                                    : 'Available to order'
                                : variant?.message ||
                                  'This combination is unavailable.'}
                        </p>
                        <StickyAddToCart
                            targetId="product-add-to-cart"
                            name={product.name}
                            image={product.images[0]?.url}
                            variant={variant}
                            options={product.options}
                            selected={selected}
                            onOption={onOption}
                            hasOptions={product.options.length > 0}
                            showBuyButton={product.show_buy_button}
                        />
                        {variant && (
                            <Form
                                id="product-add-to-cart"
                                key={variant.id}
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
                                            className="voltora-quantity"
                                            hidden={!productSettings.quantity}
                                        >
                                            Quantity
                                            <input
                                                type="number"
                                                name="quantity"
                                                defaultValue="1"
                                                min="1"
                                                max={variant.available ?? 999}
                                                disabled={!variant.purchasable}
                                            />
                                        </label>
                                        <button
                                            className="voltora-primary voltora-add"
                                            disabled={
                                                processing ||
                                                !variant.purchasable
                                            }
                                            type="submit"
                                        >
                                            {processing
                                                ? 'ADDING…'
                                                : variant.purchasable
                                                  ? text(
                                                        'product_button',
                                                        'ADD TO BAG',
                                                    )
                                                  : 'UNAVAILABLE'}
                                        </button>
                                        {variant.purchasable && (
                                            <BuyNowButton
                                                showBuyButton={
                                                    product.show_buy_button
                                                }
                                                disabled={processing}
                                                label={text(
                                                    'product_buy_now_button',
                                                    'Buy now',
                                                )}
                                                className="mt-3 rounded-none tracking-[0.12em]"
                                            />
                                        )}
                                        <InputError
                                            message={
                                                errors.quantity ??
                                                errors.variant_id
                                            }
                                        />
                                    </>
                                )}
                            </Form>
                        )}
                        <PlaceSlot place="product.after_add_to_cart" />
                        <StockNote
                            mode={productSettings.stock_note}
                            quantity={
                                variant?.purchasable ? variant.available : null
                            }
                        />
                        {variant && productSettings.show_sku && (
                            <small className="voltora-sku">
                                SKU: {variant.sku}
                            </small>
                        )}
                        {[
                            ['Delivery', 'delivery_text'],
                            ['Payment', 'payment_text'],
                            ['Warranty', 'warranty_text'],
                        ].map(([label, key]) => (
                            <details
                                className="voltora-product-service"
                                key={key}
                            >
                                <summary>{label}</summary>
                                <p>{text(key)}</p>
                            </details>
                        ))}
                        <p className="voltora-purchase-trust">
                            {text('product_trust')}
                        </p>
                    </aside>
                </div>
                {show('show_product_details') && (
                    <section className="voltora-product-details">
                        <h2>About this product</h2>
                        <div>
                            <h2>Details</h2>
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
                                                      />
                                                  ),
                                              },
                                          ]
                                        : []
                                }
                            />
                            <PlaceSlot place="product.after_description" />
                        </div>
                    </section>
                )}
                <ExtensionSlot
                    items={slots}
                    className="voltora-product-extensions"
                    extraProps={{ variant }}
                />
            </div>
            {upsells.some((item) => item.id !== product.id) && (
                <VoltoraRail
                    title="Customers also bought"
                    products={upsells.filter((item) => item.id !== product.id)}
                />
            )}
            <VoltoraRail
                title={text('related_heading', 'People also viewed')}
                products={related.filter((item) => item.id !== product.id)}
            />
        </>
    );
}
