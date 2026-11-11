import { ExtensionSlot } from '@/components/extensions/extension-slot';
import { useStoreTheme } from '@/hooks/use-store-theme';
import { GiftOptions, isFreeServiceSlot } from '../product-extras';
import { Form } from '@inertiajs/react';
import { useId, useState } from 'react';
import {
    ArrowLeft,
    ArrowRight,
    ChevronDown,
    ClipboardList,
    Tag,
    Package,
    Layers,
    Truck,
    Undo2,
    Sparkles,
    Minus,
    Plus,
    ShieldCheck,
    MessageCircle,
    ZoomIn,
} from 'lucide-react';
import CartController from '@/actions/App/Http/Controllers/Storefront/CartController';
import { BuyNowButton } from '@/components/storefront/buy-now-button';
import { PlaceSlot, usePlaceItems } from '@/components/extensions/place-slot';
import { ProductDescription } from '@/components/storefront/product-description';
import { ProductLabels } from '@/components/storefront/product-labels';
import { VariantSelector } from '@/components/storefront/variant-selector';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import InputError from '@/components/input-error';
import { StoreMenuLink as Link } from '@/components/storefront/store-menu-link';
import {
    blockAttributes,
    type ThemeSectionProps,
    type ThemeSectionData,
} from '@/components/storefront/theme-sections';
import type { ThemeProductProps } from '@/lib/theme-pages';
import type { ProductCard } from '@/types';
import { SableCard } from '../card';
import { str } from '../utils';
const icons = {
    document: ClipboardList,
    tag: Tag,
    box: Package,
    material: Layers,
    truck: Truck,
    return: Undo2,
    care: Sparkles,
};
export default function MainProduct({
    section,
    page,
}: ThemeSectionProps<Record<string, unknown>, ThemeProductProps>) {
    return page?.product ? (
        <ProductDetails key={page.product.id} section={section} page={page} />
    ) : null;
}
function ProductDetails({
    section,
    page,
}: {
    section: ThemeSectionData;
    page: ThemeProductProps;
}) {
    const { product, variant, imageIndex, onImage, selected, onOption } = page;
    const s = section.settings;
    const [quantity, setQuantity] = useState(1);
    const id = useId();
    const formId = id + '-purchase';
    const promotions = usePlaceItems('product.after_price');
    const afterAdd = usePlaceItems('product.after_add_to_cart');
    const { theme } = useStoreTheme();
    const showBuy =
        s.show_buy !== false &&
        (product.show_buy_button ?? theme?.settings?.show_buy_button !== false);
    const gifts = [...promotions, ...afterAdd].filter(isFreeServiceSlot);
    const photo = product.images[imageIndex] ?? product.images[0];
    const maximum = Math.max(1, Math.min(variant?.available ?? 99, 99));
    const count = Math.min(Math.max(1, quantity), maximum);
    const fallback =
        (s.recommendations as { products?: ProductCard[] } | null)?.products ??
        [];
    const recommendations = [
        ...new Map(
            [...(page.related ?? []), ...fallback]
                .filter((p) => p.id !== product.id)
                .map((p) => [p.id, p]),
        ).values(),
    ].slice(0, Number(s.related_limit) || 5);
    return (
        <div className="sa-product-detail">
            <div className="sa-commerce-frame sa-detail-grid">
                <div className="sa-detail-gallery">
                    <Dialog>
                        <DialogTrigger asChild>
                            <button
                                type="button"
                                className="sa-detail-media"
                                aria-label={'Enlarge ' + product.name}
                            >
                                {photo ? (
                                    <img
                                        src={photo.url}
                                        alt={photo.alt ?? product.name}
                                        fetchPriority="high"
                                    />
                                ) : (
                                    <span>{product.name}</span>
                                )}
                                <ProductLabels labels={product.labels} />
                                <ZoomIn className="sa-zoom-icon" size={20} />
                            </button>
                        </DialogTrigger>
                        <DialogContent className="sa-image-dialog">
                            <DialogTitle className="sr-only">
                                {product.name}
                            </DialogTitle>
                            <DialogDescription className="sr-only">
                                Enlarged product image
                            </DialogDescription>
                            {photo && (
                                <img
                                    src={photo.url}
                                    alt={photo.alt ?? product.name}
                                />
                            )}
                        </DialogContent>
                    </Dialog>
                    {product.images.length > 1 && (
                        <div className="sa-detail-thumbnails">
                            <button
                                type="button"
                                aria-label="Previous product image"
                                onClick={() =>
                                    onImage(
                                        (imageIndex +
                                            product.images.length -
                                            1) %
                                            product.images.length,
                                    )
                                }
                            >
                                <ArrowLeft size={16} />
                            </button>
                            {product.images.map((img, i) => (
                                <button
                                    key={img.id}
                                    type="button"
                                    aria-label={'Show product image ' + (i + 1)}
                                    aria-pressed={i === imageIndex}
                                    onClick={() => onImage(i)}
                                >
                                    <img src={img.url} alt="" />
                                </button>
                            ))}
                            <button
                                type="button"
                                aria-label="Next product image"
                                onClick={() =>
                                    onImage(
                                        (imageIndex + 1) %
                                            product.images.length,
                                    )
                                }
                            >
                                <ArrowRight size={16} />
                            </button>
                        </div>
                    )}
                </div>
                <div className="sa-detail-summary">
                    <h1>{product.name}</h1>
                    <PlaceSlot place="product.after_title" />
                    <p className="sa-detail-price">
                        {variant?.price?.formatted ??
                            'Choose options to see the price.'}
                        {variant?.compare_at_price && (
                            <s>{variant.compare_at_price.formatted}</s>
                        )}
                    </p>
                    {str(s.tax_note) && (
                        <p className="sa-detail-note">{str(s.tax_note)}</p>
                    )}
                    <div className="sa-detail-options">
                        {product.options.map((option) => (
                            <div
                                key={option.id}
                                className={
                                    option.display_type === 'swatches'
                                        ? 'sa-color-options'
                                        : ''
                                }
                            >
                                <VariantSelector
                                    option={option}
                                    value={selected[option.id]}
                                    onChange={(value) =>
                                        onOption(option.id, value)
                                    }
                                />
                            </div>
                        ))}
                    </div>
                    {variant ? (
                        <div className="sa-purchase-area">
                            <label className="sa-quantity-label" htmlFor={id}>
                                {str(s.quantity_label)}
                            </label>
                            <div className="sa-quantity">
                                <button
                                    type="button"
                                    aria-label="Decrease quantity"
                                    disabled={
                                        count <= 1 || !variant.purchasable
                                    }
                                    onClick={() => setQuantity(count - 1)}
                                >
                                    <Minus size={14} />
                                </button>
                                <input
                                    id={id}
                                    type="number"
                                    name="quantity"
                                    form={formId}
                                    min="1"
                                    max={maximum}
                                    value={count}
                                    onChange={(e) =>
                                        setQuantity(Number(e.target.value) || 1)
                                    }
                                    disabled={!variant.purchasable}
                                />
                                <button
                                    type="button"
                                    aria-label="Increase quantity"
                                    disabled={
                                        count >= maximum || !variant.purchasable
                                    }
                                    onClick={() => setQuantity(count + 1)}
                                >
                                    <Plus size={14} />
                                </button>
                            </div>
                            <div className="sa-product-promotions">
                                <ExtensionSlot
                                    items={promotions.filter(
                                        (i) => !isFreeServiceSlot(i),
                                    )}
                                />
                            </div>
                            {s.show_assurances !== false &&
                                promotions.length === 0 && (
                                    <div className="sa-assurance-grid">
                                        <div>
                                            <ShieldCheck size={17} />
                                            <strong>
                                                {str(s.assurance_one)}
                                            </strong>
                                            <span>
                                                {str(s.assurance_one_text)}
                                            </span>
                                        </div>
                                        <div>
                                            <MessageCircle size={17} />
                                            <strong>
                                                {str(s.assurance_two)}
                                            </strong>
                                            <span>
                                                {str(s.assurance_two_text)}
                                            </span>
                                        </div>
                                    </div>
                                )}
                            {gifts.map((item) => (
                                <GiftOptions key={item.key} item={item} />
                            ))}
                            <Form
                                id={formId}
                                {...CartController.store.form()}
                                options={{ preserveScroll: true }}
                                className="sa-purchase-form"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <input
                                            type="hidden"
                                            name="variant_id"
                                            value={variant.id}
                                        />
                                        <button
                                            type="submit"
                                            className="sa-detail-add"
                                            disabled={
                                                processing ||
                                                !variant.purchasable
                                            }
                                        >
                                            {processing
                                                ? 'Adding…'
                                                : variant.purchasable
                                                  ? str(s.add_label)
                                                  : 'Sold out'}
                                        </button>
                                        {showBuy && variant.purchasable && (
                                            <BuyNowButton
                                                showBuyButton={true}
                                                disabled={processing}
                                                className="sa-detail-buy"
                                                label={str(s.buy_label)}
                                            />
                                        )}
                                        <InputError
                                            message={
                                                errors.quantity ??
                                                errors.variant_id
                                            }
                                        />
                                        {variant.message &&
                                            !variant.purchasable && (
                                                <p
                                                    className="sa-detail-note"
                                                    role="status"
                                                >
                                                    {variant.message}
                                                </p>
                                            )}
                                        {s.show_sku === true && (
                                            <p className="sa-detail-note">
                                                SKU: {variant.sku}
                                            </p>
                                        )}
                                    </>
                                )}
                            </Form>
                        </div>
                    ) : (
                        <p className="sa-detail-note">
                            This combination is unavailable.
                        </p>
                    )}
                    {str(s.payment_label) &&
                        showBuy &&
                        variant?.purchasable && (
                            <button
                                type="button"
                                form={formId}
                                className="sa-payment-link"
                                onClick={(e) => {
                                    const form = e.currentTarget.form;
                                    const flag =
                                        form?.querySelector<HTMLInputElement>(
                                            'input[name="buy_now"]',
                                        );
                                    if (form && flag) {
                                        flag.value = '1';
                                        form.requestSubmit();
                                        window.setTimeout(() => {
                                            flag.value = '';
                                        }, 0);
                                    }
                                }}
                            >
                                {str(s.payment_label)}
                            </button>
                        )}
                    <ExtensionSlot
                        items={afterAdd.filter((i) => !isFreeServiceSlot(i))}
                    />
                    <div className="sa-product-information">
                        {product.description && (
                            <details>
                                <summary>
                                    <ClipboardList size={20} />
                                    <span>{str(s.description_label)}</span>
                                    <ChevronDown size={14} />
                                </summary>
                                <div className="sa-information-body">
                                    <ProductDescription product={product} />
                                </div>
                            </details>
                        )}
                        {section.blocks
                            .filter(
                                (b) =>
                                    str(b.settings.heading) &&
                                    str(b.settings.body),
                            )
                            .map((b) => {
                                const Icon =
                                    icons[
                                        str(
                                            b.settings.icon,
                                        ) as keyof typeof icons
                                    ] ?? Package;
                                return (
                                    <details key={b.id} {...blockAttributes(b)}>
                                        <summary>
                                            <Icon size={20} />
                                            <span>
                                                {str(b.settings.heading)}
                                            </span>
                                            <ChevronDown size={14} />
                                        </summary>
                                        <div className="sa-information-body">
                                            <p>{str(b.settings.body)}</p>
                                            {str(b.settings.link) && (
                                                <Link
                                                    href={str(b.settings.link)}
                                                >
                                                    {str(b.settings.link_label)}
                                                </Link>
                                            )}
                                        </div>
                                    </details>
                                );
                            })}
                    </div>
                    <PlaceSlot place="product.after_description" />
                </div>
            </div>
            {recommendations.length > 0 && (
                <section className="sa-commerce-frame sa-related">
                    <h2>{str(s.related_heading)}</h2>
                    <div className="sa-related-grid">
                        {recommendations.map((p) => (
                            <SableCard
                                key={p.id}
                                product={p}
                                label={str(s.add_label)}
                            />
                        ))}
                    </div>
                </section>
            )}
        </div>
    );
}
