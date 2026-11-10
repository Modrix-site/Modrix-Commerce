import { Form, Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { ImageOff, PawPrint, ShieldCheck, Truck } from 'lucide-react';
import CartController from '@/actions/App/Http/Controllers/Storefront/CartController';
import { VariantSelector } from '@/components/storefront/variant-selector';
import { BuyNowButton } from '@/components/storefront/buy-now-button';
import { ProductDescription } from '@/components/storefront/product-description';
import { ProductLabels } from '@/components/storefront/product-labels';
import { RelatedProducts } from '@/components/storefront/related-products';
import { SaleNotice } from '@/components/storefront/sale-notice';
import { StickyAddToCart } from '@/components/storefront/sticky-add-to-cart';
import { rememberProduct } from '@/components/storefront/recent-products';
import { ExtensionSlot } from '@/components/extensions/extension-slot';
import { PlaceSlot } from '@/components/extensions/place-slot';
import InputError from '@/components/input-error';
import type { ThemeProductProps } from '@/lib/theme-pages';
import { category } from '@/routes/storefront';

export default function PawzyProduct({
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
    const s = usePage().props.storefront?.theme.settings ?? {};
    const [tab, setTab] = useState('description');
    const photo = product.images[imageIndex];
    useEffect(() => {
        rememberProduct({
            id: product.id,
            name: product.name,
            slug: product.slug,
            short_description: product.short_description,
            image: product.images[0] ?? null,
            price: product.variants[0]?.price ?? null,
            compare_at_price: product.variants[0]?.compare_at_price ?? null,
            has_variants: product.variants.length > 1,
            in_stock: product.variants.some((v) => v.purchasable),
            variant_id:
                product.variants.length === 1 ? product.variants[0]?.id : null,
        });
    }, [product]);
    return (
        <div className="pawzy-product-page">
            <Head title={product.meta_title}>
                {product.meta_description && (
                    <meta
                        name="description"
                        content={product.meta_description}
                    />
                )}
            </Head>
            <nav className="pawzy-breadcrumb" aria-label="Breadcrumb">
                <Link href="/">Home</Link>
                {breadcrumbs.map((c) => (
                    <Link href={category(c.slug)} key={c.slug}>
                        {c.name}
                    </Link>
                ))}
                <span>{product.name}</span>
            </nav>
            <div className="pawzy-product">
                <div className="pawzy-gallery">
                    <div className="pawzy-main-image relative">
                        <ProductLabels labels={product.labels} />
                        {photo ? (
                            <img
                                src={photo.url}
                                alt={photo.alt ?? product.name}
                                fetchPriority="high"
                            />
                        ) : (
                            <ImageOff size={50} />
                        )}
                    </div>
                    <div className="pawzy-thumbnails">
                        {product.images.map((p, i) => (
                            <button
                                type="button"
                                key={p.id}
                                aria-label={`Show image ${i + 1}`}
                                aria-pressed={i === imageIndex}
                                onClick={() => onImage(i)}
                            >
                                <img src={p.url} alt="" />
                            </button>
                        ))}
                    </div>
                </div>
                <div className="pawzy-product-info">
                    <h1>{product.name}</h1>
                    <PlaceSlot place="product.after_title" />
                    <p className="pawzy-product-price store-price">
                        {variant?.price?.formatted ?? 'Select an option'}{' '}
                        {variant?.compare_at_price && (
                            <s>{variant.compare_at_price.formatted}</s>
                        )}
                    </p>
                    <SaleNotice sale={product.sale} />
                    <PlaceSlot place="product.after_price" />
                    <p className="pawzy-short-description">
                        {product.short_description}
                    </p>
                    <dl className="pawzy-product-meta">
                        <dt>Available</dt>
                        <dd>
                            {variant?.purchasable
                                ? 'In stock'
                                : 'Currently unavailable'}
                        </dd>
                        <dt>SKU</dt>
                        <dd>{variant?.sku ?? 'Select an option'}</dd>
                        {breadcrumbs.length > 0 && (
                            <>
                                <dt>Category</dt>
                                <dd>{breadcrumbs.at(-1)?.name}</dd>
                            </>
                        )}
                    </dl>
                    {product.options.map((option) => (
                        <VariantSelector
                            key={option.id}
                            option={option}
                            value={selected[option.id]}
                            onChange={(value) => onOption(option.id, value)}
                        />
                    ))}
                    {variant ? (
                        <>
                            <StickyAddToCart
                                targetId="pawzy-add-to-cart"
                                name={product.name}
                                image={product.images[0]?.url}
                                variant={variant}
                                options={product.options}
                                selected={selected}
                                onOption={onOption}
                            />
                            <Form
                                key={variant.id}
                                id="pawzy-add-to-cart"
                                {...CartController.store.form()}
                                options={{ preserveScroll: true }}
                            >
                                {({ processing, errors }) => (
                                    <div className="pawzy-purchase">
                                        <input
                                            type="hidden"
                                            name="variant_id"
                                            value={variant.id}
                                        />
                                        <label>
                                            Quantity
                                            <input
                                                aria-label="Quantity"
                                                name="quantity"
                                                type="number"
                                                min={1}
                                                max={variant.available ?? 999}
                                                defaultValue={1}
                                                disabled={!variant.purchasable}
                                            />
                                        </label>
                                        <button
                                            className="pawzy-button store-button"
                                            type="submit"
                                            disabled={
                                                processing ||
                                                !variant.purchasable
                                            }
                                        >
                                            {processing
                                                ? 'Adding…'
                                                : variant.purchasable
                                                  ? 'Add to bag'
                                                  : 'Unavailable'}
                                        </button>
                                        {variant.purchasable && (
                                            <div className="pawzy-buy-now">
                                                <BuyNowButton
                                                    showBuyButton={
                                                        product.show_buy_button
                                                    }
                                                    disabled={processing}
                                                    label="Buy it now"
                                                />
                                            </div>
                                        )}
                                        <InputError
                                            message={
                                                errors.quantity ??
                                                errors.variant_id
                                            }
                                        />
                                        {variant.message && (
                                            <p>{variant.message}</p>
                                        )}
                                    </div>
                                )}
                            </Form>
                        </>
                    ) : (
                        <p>Choose an available combination to continue.</p>
                    )}
                    <PlaceSlot place="product.after_add_to_cart" />
                    {s.show_product_benefits !== false && (
                        <div className="pawzy-benefits">
                            <h3>The benefits of choosing us</h3>
                            <div>
                                {[
                                    [PawPrint, 'Care for your pet'],
                                    [ShieldCheck, 'Secure checkout'],
                                    [Truck, 'Delivery options'],
                                ].map(([Icon, label]) => {
                                    const Symbol = Icon as typeof Truck;
                                    return (
                                        <span key={String(label)}>
                                            <Symbol size={25} />
                                            {String(label)}
                                        </span>
                                    );
                                })}
                            </div>
                        </div>
                    )}
                    <p className="pawzy-delivery">
                        <Truck size={21} />
                        {String(
                            s.product_shipping ??
                                'Delivery options are shown at checkout.',
                        )}
                    </p>
                </div>
            </div>
            <section className="pawzy-product-tabs">
                <div role="tablist" aria-label="Product information">
                    {[
                        ['description', 'Description'],
                        ['delivery', 'Delivery policy'],
                        ['returns', 'Shipping & return'],
                        ['care', 'Care guide'],
                    ].map(([id, label]) => (
                        <button
                            key={id}
                            type="button"
                            role="tab"
                            id={`pawzy-tab-${id}`}
                            aria-selected={tab === id}
                            aria-controls={`pawzy-panel-${id}`}
                            onClick={() => setTab(id)}
                        >
                            {label}
                        </button>
                    ))}
                </div>
                {[
                    ['description', ''],
                    ['delivery', s.product_shipping],
                    ['returns', s.product_returns],
                    ['care', s.product_custom_tab],
                ].map(([id, text]) => (
                    <div
                        role="tabpanel"
                        key={String(id)}
                        id={`pawzy-panel-${id}`}
                        aria-labelledby={`pawzy-tab-${id}`}
                        hidden={tab !== id}
                        tabIndex={0}
                    >
                        {id === 'description' ? (
                            <ProductDescription product={product} />
                        ) : (
                            <p>{String(text ?? '')}</p>
                        )}
                    </div>
                ))}
            </section>
            <PlaceSlot place="product.after_description" />
            <ExtensionSlot items={slots} />
            <RelatedProducts
                productId={product.id}
                related={related}
                upsells={upsells}
            />
        </div>
    );
}
