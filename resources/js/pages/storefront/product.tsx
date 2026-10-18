import {
    VariantSelector,
    type VariantOption,
} from '@/components/storefront/variant-selector';
import { Form, Head, Link } from '@inertiajs/react';
import { Suspense, useMemo, useState } from 'react';
import { themeProduct } from '@/lib/theme-pages';
import CartController from '@/actions/App/Http/Controllers/Storefront/CartController';
import { ElectronicsProduct } from '@/components/storefront/electronics-product';
import { ProductLabels } from '@/components/storefront/product-labels';
import { BuyNowButton } from '@/components/storefront/buy-now-button';
import { StickyAddToCart } from '@/components/storefront/sticky-add-to-cart';
import { PlaceSlot } from '@/components/extensions/place-slot';
import { RelatedProducts } from '@/components/storefront/related-products';
import { DescriptionPanels } from '@/components/storefront/page-settings/description-panels';
import type { DescriptionSection } from '@/components/storefront/page-settings/description-panels';
import { ProductGallery } from '@/components/storefront/page-settings/product-gallery';
import { StockNote } from '@/components/storefront/page-settings/stock-note';
import { useStoreSettings } from '@/hooks/use-store-settings';
import { SaleNotice } from '@/components/storefront/sale-notice';
import { ExtensionSlot } from '@/components/extensions/extension-slot';
import type { SlotItem } from '@/components/extensions/extension-slot';
import InputError from '@/components/input-error';
import { useStoreTheme } from '@/hooks/use-store-theme';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { selectionForVariant, selectVariant } from '@/lib/variant-selection';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { home } from '@/routes';
import { brand, category } from '@/routes/storefront';
import type { MoneyValue, ProductCard, ProductLabel, SaleInfo } from '@/types';

export type Variant = {
    id: number;
    name: string | null;
    sku: string;
    image_id: number | null;
    price: MoneyValue | null;
    compare_at_price: MoneyValue | null;
    option_value_ids: number[];
    purchasable: boolean;
    available: number | null;
    message: string | null;
};

export type ProductPageProps = {
    related?: ProductCard[];
    upsells?: ProductCard[];
    product: {
        id: number;
        name: string;
        slug: string;
        short_description: string | null;
        description: string | null;
        meta_title: string;
        meta_description: string | null;
        images: { id: number; url: string; alt: string | null }[];
        labels?: ProductLabel[];
        brand: { name: string; slug: string } | null;
        options: VariantOption[];
        variants: Variant[];
        /** A sale (e.g. a flash sale) on this product. */
        sale?: SaleInfo | null;
        /** Buy now override: null follows the theme setting. */
        show_buy_button?: boolean | null;
    };
    breadcrumbs: { name: string; slug: string }[];
    slots: SlotItem[];
};

export default function ProductPage({
    product,
    breadcrumbs,
    slots,
    related = [],
    upsells = [],
}: ProductPageProps) {
    const { productPage, theme } = useStoreTheme();
    const CustomProduct = theme?.frontend?.product
        ? themeProduct(theme.name)
        : undefined;
    const t = useT();
    const firstPurchasable =
        product.variants.find((v) => v.purchasable) ?? product.variants[0];
    const [imageIndex, setImageIndex] = useState(() =>
        Math.max(
            0,
            product.images.findIndex(
                (image) => image.id === firstPurchasable?.image_id,
            ),
        ),
    );
    const [selected, setSelected] = useState<Record<number, number>>(() =>
        selectionForVariant(product.options, firstPurchasable),
    );
    const chooseOption = (attributeId: number, valueId: number) => {
        const match = selectVariant(
            product.options,
            product.variants,
            selected,
            attributeId,
            valueId,
        );
        if (!match) return;
        setSelected(selectionForVariant(product.options, match));
        setImageIndex(
            Math.max(
                0,
                product.images.findIndex(
                    (image) => image.id === match.image_id,
                ),
            ),
        );
    };

    const variant = useMemo(() => {
        if (product.options.length === 0) {
            return firstPurchasable;
        }

        const chosen = Object.values(selected);

        return product.variants.find(
            (v) =>
                chosen.length === product.options.length &&
                chosen.every((id) => v.option_value_ids.includes(id)),
        );
    }, [selected, product]);

    // Product tab settings (customizer).
    const pageSettings = useStoreSettings().product;
    const shownRelated = pageSettings.related
        ? related.slice(0, pageSettings.related_count)
        : [];
    const shownUpsells = pageSettings.upsells ? upsells : [];
    const descriptionSections: DescriptionSection[] = [
        ...(product.description
            ? [
                  {
                      id: 'description',
                      title: t('Description'),
                      content: (
                          <p className="whitespace-pre-line">
                              {product.description}
                          </p>
                      ),
                  },
              ]
            : []),
        // Shown open, the options are already beside the price; Details only
        // adds to the tabs and accordion.
        ...(pageSettings.description !== 'open' &&
        (product.brand || product.options.length > 0)
            ? [
                  {
                      id: 'details',
                      title: t('Details'),
                      content: (
                          <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
                              {product.brand && (
                                  <>
                                      <dt className="font-medium text-foreground">
                                          {t('Brand')}
                                      </dt>
                                      <dd>{product.brand.name}</dd>
                                  </>
                              )}
                              {product.options.map((option) => (
                                  <div key={option.id} className="contents">
                                      <dt className="font-medium text-foreground">
                                          {option.name}
                                      </dt>
                                      <dd>
                                          {option.values
                                              .map((v) => v.value)
                                              .join(', ')}
                                      </dd>
                                  </div>
                              ))}
                          </dl>
                      ),
                  },
              ]
            : []),
    ];

    if (CustomProduct)
        return (
            <Suspense fallback={<p role="status">Loading product…</p>}>
                <CustomProduct
                    product={product}
                    breadcrumbs={breadcrumbs}
                    slots={slots}
                    related={shownRelated}
                    upsells={shownUpsells}
                    variant={variant}
                    imageIndex={imageIndex}
                    onImage={setImageIndex}
                    selected={selected}
                    onOption={chooseOption}
                />
            </Suspense>
        );
    if (productPage === 'electronics')
        return (
            <ElectronicsProduct
                product={product}
                breadcrumbs={breadcrumbs}
                slots={slots}
                related={shownRelated}
                upsells={shownUpsells}
                variant={variant}
                imageIndex={imageIndex}
                onImage={setImageIndex}
                selected={selected}
                onOption={chooseOption}
            />
        );

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

            <nav
                aria-label={t('Breadcrumb')}
                className="mb-6 text-sm text-muted-foreground"
            >
                <ol className="flex flex-wrap gap-1">
                    <li>
                        <Link href={home()} className="hover:text-foreground">
                            {t('Home')}
                        </Link>
                    </li>
                    {breadcrumbs.map((b) => (
                        <li
                            key={b.slug}
                            className="before:mr-1 before:content-['/']"
                        >
                            <Link
                                href={category(b.slug)}
                                className="hover:text-foreground"
                            >
                                {b.name}
                            </Link>
                        </li>
                    ))}
                </ol>
            </nav>

            <div
                className={cn(
                    'grid gap-10',
                    productPage === 'split'
                        ? 'md:grid-cols-2'
                        : 'mx-auto max-w-3xl',
                )}
            >
                <ProductGallery
                    images={product.images}
                    index={imageIndex}
                    onIndex={setImageIndex}
                    alt={product.name}
                    thumbnails={pageSettings.thumbnails}
                    zoom={pageSettings.zoom}
                    overlay={<ProductLabels labels={product.labels} />}
                />

                <div className="grid content-start gap-5">
                    <div className="grid gap-2">
                        {product.brand && (
                            <Link
                                href={brand(product.brand.slug)}
                                className="text-sm font-medium tracking-wide text-muted-foreground uppercase hover:text-foreground"
                            >
                                {product.brand.name}
                            </Link>
                        )}
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {product.name}
                        </h1>
                        <PlaceSlot place="product.after_title" />
                        {variant?.price ? (
                            <p
                                className={cn(
                                    'store-price text-xl tabular-nums',
                                    variant.compare_at_price &&
                                        'store-price-sale',
                                )}
                            >
                                {variant.price.formatted}
                                {variant.compare_at_price && (
                                    <s className="ml-3 text-base text-muted-foreground">
                                        <span className="sr-only">
                                            {t('Was')}{' '}
                                        </span>
                                        {variant.compare_at_price.formatted}
                                    </s>
                                )}
                            </p>
                        ) : (
                            <p className="text-muted-foreground">
                                {t('Choose options to see the price.')}
                            </p>
                        )}
                        <SaleNotice sale={product.sale} />
                        <PlaceSlot place="product.after_price" />
                        {product.short_description && (
                            <p className="text-muted-foreground">
                                {product.short_description}
                            </p>
                        )}
                    </div>

                    {product.options.map((option) => (
                        <VariantSelector
                            key={option.id}
                            option={option}
                            value={selected[option.id]}
                            onChange={(value) => chooseOption(option.id, value)}
                        />
                    ))}

                    <StickyAddToCart
                        targetId="product-add-to-cart"
                        name={product.name}
                        image={product.images[0]?.url}
                        variant={variant}
                        hasOptions={product.options.length > 0}
                        showBuyButton={product.show_buy_button}
                    />
                    {variant ? (
                        <Form
                            id="product-add-to-cart"
                            {...CartController.store.form()}
                            options={{ preserveScroll: true }}
                            className="grid gap-3"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <input
                                        type="hidden"
                                        name="variant_id"
                                        value={variant.id}
                                    />
                                    <div className="flex items-end gap-3">
                                        <div
                                            className={cn(
                                                'grid w-24 gap-2',
                                                !pageSettings.quantity && 'hidden',
                                            )}
                                        >
                                            <Label htmlFor="quantity">
                                                {t('Quantity')}
                                            </Label>
                                            <Input
                                                id="quantity"
                                                name="quantity"
                                                type="number"
                                                min={1}
                                                max={variant.available ?? 999}
                                                defaultValue={1}
                                                disabled={!variant.purchasable}
                                            />
                                        </div>
                                        <Button
                                            type="submit"
                                            className="store-button"
                                            disabled={
                                                processing ||
                                                !variant.purchasable
                                            }
                                        >
                                            {variant.purchasable
                                                ? t('Add to cart')
                                                : t('Unavailable')}
                                        </Button>
                                    </div>
                                    {variant.purchasable && (
                                        <BuyNowButton
                                            showBuyButton={
                                                product.show_buy_button
                                            }
                                            disabled={processing}
                                        />
                                    )}
                                    <InputError
                                        message={
                                            errors.quantity ?? errors.variant_id
                                        }
                                    />
                                    {variant.message && (
                                        <p className="text-sm text-muted-foreground">
                                            {variant.message}
                                        </p>
                                    )}
                                    <StockNote
                                        mode={pageSettings.stock_note}
                                        quantity={
                                            variant.purchasable
                                                ? variant.available
                                                : null
                                        }
                                    />
                                    {pageSettings.show_sku && (
                                        <p className="text-xs text-muted-foreground">
                                            {t('SKU :sku', { sku: variant.sku })}
                                        </p>
                                    )}
                                </>
                            )}
                        </Form>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            {t('This combination is not available.')}
                        </p>
                    )}
                    <PlaceSlot place="product.after_add_to_cart" />

                    <DescriptionPanels
                        mode={pageSettings.description}
                        sections={descriptionSections}
                    />
                    <PlaceSlot place="product.after_description" />
                </div>
            </div>
            <ExtensionSlot items={slots} className="mt-12 grid gap-8" />
            <RelatedProducts
                productId={product.id}
                upsells={pageSettings.upsells ? upsells : []}
                related={
                    pageSettings.related
                        ? related.slice(0, pageSettings.related_count)
                        : []
                }
            />
        </>
    );
}
