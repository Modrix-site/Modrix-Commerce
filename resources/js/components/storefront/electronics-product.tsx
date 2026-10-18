import { VariantSelector } from '@/components/storefront/variant-selector';
import { Form, Head, Link } from '@inertiajs/react';
import { ImageOff, ShieldCheck, Truck } from 'lucide-react';
import { useEffect } from 'react';
import CartController from '@/actions/App/Http/Controllers/Storefront/CartController';
import { ElectronicsCard } from '@/components/storefront/electronics-card';
import { ExtensionSlot } from '@/components/extensions/extension-slot';
import { rememberProduct } from '@/components/storefront/recent-products';
import { BuyNowButton } from '@/components/storefront/buy-now-button';
import { RelatedProducts } from '@/components/storefront/related-products';
import { StickyAddToCart } from '@/components/storefront/sticky-add-to-cart';
import { SaleNotice } from '@/components/storefront/sale-notice';
import InputError from '@/components/input-error';
import { home } from '@/routes';
import { brand, category } from '@/routes/storefront';
import type { ProductPageProps, Variant } from '@/pages/storefront/product';

export function ElectronicsProduct({
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
}: ProductPageProps & {
    variant: Variant | undefined;
    imageIndex: number;
    onImage: (index: number) => void;
    selected: Record<number, number>;
    onOption: (id: number, value: number) => void;
}) {
    const image = product.images[imageIndex];
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
            in_stock: product.variants.some((item) => item.purchasable),
            variant_id:
                product.variants.length === 1 ? product.variants[0]?.id : null,
        });
    }, [product]);
    const saving =
        variant?.compare_at_price && variant.price
            ? Number(
                  variant.compare_at_price.display_amount ??
                      variant.compare_at_price.amount,
              ) - Number(variant.price.display_amount ?? variant.price.amount)
            : 0;
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
            <nav aria-label="Breadcrumb" className="electronics-breadcrumb">
                <Link href={home()}>Home</Link>
                {breadcrumbs.map((item) => (
                    <span key={item.slug}>
                        {' '}
                        / <Link href={category(item.slug)}>{item.name}</Link>
                    </span>
                ))}
                <span> / {product.name}</span>
            </nav>
            <div className="electronics-product">
                <div className="electronics-gallery">
                    <div className="electronics-thumbnails">
                        {product.images.map((photo, index) => (
                            <button
                                key={photo.id}
                                type="button"
                                aria-label={`Show image ${index + 1}`}
                                aria-current={imageIndex === index}
                                onClick={() => onImage(index)}
                            >
                                <img src={photo.url} alt="" />
                            </button>
                        ))}
                    </div>
                    {image ? (
                        <img
                            className="electronics-main-image"
                            src={image.url}
                            alt={image.alt ?? product.name}
                        />
                    ) : (
                        <div className="electronics-main-image flex items-center justify-center">
                            <ImageOff size={35} />
                        </div>
                    )}
                </div>
                <div className="electronics-product-summary">
                    {product.brand && (
                        <Link
                            className="electronics-product-brand"
                            href={brand(product.brand.slug)}
                        >
                            {product.brand.name}
                        </Link>
                    )}
                    <h1>{product.name}</h1>
                    {variant && (
                        <p className="text-muted-foreground">
                            SKU: {variant.sku}
                        </p>
                    )}
                    {product.options.length > 0 && (
                        <div className="electronics-variant-options">
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
                        </div>
                    )}
                    <details open>
                        <summary>Product highlights</summary>
                        <p>
                            {product.short_description ||
                                'Explore the product details and choose the option that suits you.'}
                        </p>
                    </details>
                    <details open>
                        <summary>Product information</summary>
                        <p>
                            {product.description ||
                                product.short_description ||
                                'Contact our team for more information about this product.'}
                        </p>
                    </details>
                </div>
                <aside
                    className="electronics-purchase"
                    aria-label="Purchase product"
                >
                    <p className="electronics-stock">
                        {variant?.purchasable
                            ? 'In stock'
                            : 'Currently unavailable'}
                    </p>
                    <div className="electronics-purchase-price">
                        {variant?.compare_at_price && (
                            <s>{variant.compare_at_price.formatted}</s>
                        )}
                        <strong>
                            {variant?.price?.formatted ?? 'Select an option'}
                        </strong>
                        {saving > 0 && (
                            <span className="electronics-saving">
                                Save{' '}
                                {variant?.price?.display_currency ??
                                    variant?.price?.currency}{' '}
                                {saving.toLocaleString(undefined, {
                                    maximumFractionDigits: 2,
                                })}
                            </span>
                        )}
                    </div>
                    <SaleNotice sale={product.sale} />
                    <div className="electronics-purchase-notice">
                        <Truck size={19} />
                        Delivery options are shown at checkout.
                    </div>
                    <div className="electronics-purchase-notice">
                        <ShieldCheck size={19} />
                        Secure checkout with your chosen payment method.
                    </div>
                    <StickyAddToCart
                        targetId="product-add-to-cart"
                        name={product.name}
                        image={product.images[0]?.url}
                        variant={variant}
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
                                <div className="grid gap-4">
                                    <input
                                        type="hidden"
                                        name="variant_id"
                                        value={variant.id}
                                    />
                                    <label className="flex items-center justify-between text-xs">
                                        Quantity
                                        <input
                                            type="number"
                                            name="quantity"
                                            aria-label="Quantity"
                                            min={1}
                                            max={variant.available ?? 999}
                                            defaultValue={1}
                                            disabled={!variant.purchasable}
                                        />
                                    </label>
                                    <button
                                        type="submit"
                                        className="electronics-button"
                                        disabled={
                                            processing || !variant.purchasable
                                        }
                                    >
                                        {processing
                                            ? 'ADDING…'
                                            : variant.purchasable
                                              ? 'ADD TO CART'
                                              : 'UNAVAILABLE'}
                                    </button>
                                    {variant.purchasable && (
                                        <BuyNowButton
                                            showBuyButton={
                                                product.show_buy_button
                                            }
                                            disabled={processing}
                                            label="BUY NOW"
                                        />
                                    )}
                                    <InputError
                                        message={
                                            errors.variant_id ?? errors.quantity
                                        }
                                    />
                                    {variant.message && (
                                        <p className="text-xs">
                                            {variant.message}
                                        </p>
                                    )}
                                </div>
                            )}
                        </Form>
                    )}
                    {!variant && (
                        <p className="text-xs">
                            Choose a supported combination of options to
                            continue.
                        </p>
                    )}
                </aside>
            </div>
            <section className="electronics-product-section">
                <h2>More information</h2>
                <p className="text-sm whitespace-pre-line">
                    {product.description ?? product.short_description}
                </p>
            </section>
            <ExtensionSlot items={slots} />
            <RelatedProducts productId={product.id} upsells={upsells} />
            {related.filter((item) => item.id !== product.id).length > 0 && (
                <section className="electronics-rail mt-8">
                    <div className="electronics-rail-header">
                        <h2>Similar items</h2>
                    </div>
                    <div className="electronics-product-rail">
                        {related
                            .filter((item) => item.id !== product.id)
                            .slice(0, 4)
                            .map((item) => (
                                <ElectronicsCard key={item.id} product={item} />
                            ))}
                    </div>
                </section>
            )}
        </>
    );
}
