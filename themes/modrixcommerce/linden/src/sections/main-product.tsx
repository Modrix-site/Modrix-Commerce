import { Form, Link } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Minus, Plus, Maximize2, X, Leaf, Truck } from 'lucide-react';
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
import { Card, str, rememberViewed, SaveActions } from '../components';
import ProductShare from '../share-product';
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
    const max = variant?.available ?? 999;
    const count = Math.max(
        1,
        Math.min(Math.max(1, max), Math.floor(quantity) || 1),
    );
    const image = product.images[imageIndex] ?? product.images[0];
    const related = page.related ?? [];
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
    useEffect(() => {
        const dialog = zoomDialog.current;
        if (zoom && dialog && !dialog.open) dialog.showModal();
        if (!zoom && dialog?.open) dialog.close();
    }, [zoom]);
    return (
        <>
            <nav className="lv-breadcrumb" aria-label="Breadcrumb">
                <div className="lv-frame">
                    <Link href="/">Home</Link> /{' '}
                    <Link href="/search">Products</Link>
                    {page.breadcrumbs.map((c) => (
                        <span key={c.slug}>
                            {' '}
                            / <Link href={'/c/' + c.slug}>{c.name}</Link>
                        </span>
                    ))}{' '}
                    / <span>{product.name}</span>
                </div>
            </nav>
            <div className="lv-frame lv-product">
                <div className="lv-gallery">
                    <div className="lv-main-image">
                        {image ? (
                            <img
                                src={image.url}
                                alt={image.alt ?? product.name}
                                fetchPriority="high"
                            />
                        ) : (
                            <div className="lv-empty">No image</div>
                        )}
                        {image && (
                            <button
                                type="button"
                                className="lv-image-zoom"
                                aria-label="Enlarge product image"
                                onClick={() => setZoom(true)}
                            >
                                <Maximize2 size={20} />
                            </button>
                        )}
                    </div>
                    {product.images.length > 1 && (
                        <div className="lv-thumbs">
                            {product.images.map((im, i) => (
                                <button
                                    key={im.id}
                                    type="button"
                                    aria-label={'View product image ' + (i + 1)}
                                    aria-pressed={imageIndex === i}
                                    onClick={() => onImage(i)}
                                >
                                    <img src={im.url} alt="" />
                                </button>
                            ))}
                        </div>
                    )}
                </div>
                <div className="lv-product-summary">
                    <ProductLabels labels={product.labels} />
                    <h1>{product.name}</h1>
                    <PlaceSlot
                        place="product.after_title"
                        extraProps={{ variant }}
                    />
                    {product.short_description && (
                        <p className="lv-product-intro">
                            {product.short_description}
                        </p>
                    )}
                    <SaleNotice sale={product.sale} />
                    <div className="lv-product-price">
                        {variant?.price?.formatted ?? str(s.choose_label)}
                        {variant?.compare_at_price && (
                            <s>{variant.compare_at_price.formatted}</s>
                        )}
                    </div>
                    <PlaceSlot
                        place="product.after_price"
                        extraProps={{ variant }}
                    />
                    <StickyAddToCart
                        targetId="lv-product-purchase"
                        name={product.name}
                        image={product.images[0]?.url}
                        variant={variant}
                        options={product.options}
                        selected={selected}
                        onOption={onOption}
                        hasOptions={product.options.length > 0}
                        showBuyButton={product.show_buy_button}
                    />
                    <div id="lv-product-purchase">
                        {product.options.length > 0 && (
                            <div className="lv-options">
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
                                        <div className="lv-purchase-row">
                                            <div className="lv-quantity">
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
                                                                e.target.value,
                                                            ),
                                                        )
                                                    }
                                                />
                                                <button
                                                    type="button"
                                                    aria-label="Increase quantity"
                                                    disabled={count >= max}
                                                    onClick={() =>
                                                        setQuantity(count + 1)
                                                    }
                                                >
                                                    <Plus size={14} />
                                                </button>
                                            </div>
                                            <button
                                                className="lv-btn lv-btn-solid store-button"
                                                disabled={
                                                    processing ||
                                                    !variant.purchasable
                                                }
                                            >
                                                {processing
                                                    ? str(s.adding_label)
                                                    : !variant.purchasable
                                                      ? str(s.sold_label)
                                                      : str(s.add_label)}
                                            </button>
                                            {s.show_buy_now === true && (
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
                            <p>{str(s.choose_label)}</p>
                        )}
                    </div>
                    <PlaceSlot
                        place="product.after_add_to_cart"
                        extraProps={{ variant }}
                    />
                    {(str(s.service_one) || str(s.service_two)) && (
                        <div className="lv-product-services">
                            {str(s.service_one) && (
                                <div>
                                    <Leaf size={22} />
                                    {str(s.service_one)}
                                </div>
                            )}
                            {str(s.service_two) && (
                                <div>
                                    <Truck size={22} />
                                    {str(s.service_two)}
                                </div>
                            )}
                        </div>
                    )}
                    <div className="lv-product-accordions">
                        <details open={s.description_open === true}>
                            <summary>{str(s.description_label)}</summary>
                            <ProductDescription product={product} />
                            <PlaceSlot
                                place="product.after_description"
                                extraProps={{ variant }}
                            />
                        </details>
                        {section.blocks.map((b) => (
                            <details key={b.id}>
                                <summary>{str(b.settings.heading)}</summary>
                                <p>{str(b.settings.body)}</p>
                            </details>
                        ))}
                    </div>
                    {s.show_save === true && (
                        <div className="lv-product-save">
                            <SaveActions
                                item={{
                                    id: product.id,
                                    name: product.name,
                                    slug: product.slug,
                                    image: product.images[0]?.url ?? '',
                                    price: variant?.price?.formatted ?? '',
                                    stock: variant?.purchasable ?? false,
                                    description:
                                        product.short_description ?? '',
                                }}
                            />
                            <ProductShare name={product.name} />
                        </div>
                    )}
                </div>
            </div>
            {/* Modules (bundles, reviews) and recommendations use the full width
                under the gallery and summary, not the narrow summary column. */}
            <div className="lv-frame lv-product-modules">
                <ExtensionSlot items={page.slots} extraProps={{ variant }} />
                {related.length > 0 && (
                    <section className="lv-product-recommendations">
                        <h2>{str(s.related_heading)}</h2>
                        <div className="lv-product-carousel">
                            {related
                                .slice(0, Number(s.related_limit) || 4)
                                .map((p) => (
                                    <Card key={p.id} product={p} />
                                ))}
                        </div>
                    </section>
                )}
            </div>
            {image && (
                <dialog
                    ref={zoomDialog}
                    aria-label="Product image"
                    className="lv-zoom"
                    onClose={() => setZoom(false)}
                    onClick={(event) => {
                        if (event.target === event.currentTarget)
                            setZoom(false);
                    }}
                >
                    <button
                        type="button"
                        autoFocus
                        aria-label="Close product image"
                        onClick={() => setZoom(false)}
                    >
                        <X />
                    </button>
                    <img src={image.url} alt={image.alt ?? product.name} />
                </dialog>
            )}
        </>
    );
}
