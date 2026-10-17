import { Form, Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import CartController from '@/actions/App/Http/Controllers/Storefront/CartController';
import { ExtensionSlot } from '@/components/extensions/extension-slot';
import type { SlotItem } from '@/components/extensions/extension-slot';
import InputError from '@/components/input-error';
import { BuyNowButton } from '@/components/storefront/buy-now-button';
import { StickyAddToCart } from '@/components/storefront/sticky-add-to-cart';
import { RelatedProducts } from '@/components/storefront/related-products';
import { DescriptionPanels } from '@/components/storefront/page-settings/description-panels';
import type { DescriptionSection } from '@/components/storefront/page-settings/description-panels';
import { ProductGallery } from '@/components/storefront/page-settings/product-gallery';
import { StockNote } from '@/components/storefront/page-settings/stock-note';
import { useStoreSettings } from '@/hooks/use-store-settings';
import { SaleNotice } from '@/components/storefront/sale-notice';
import { useStoreTheme } from '@/hooks/use-store-theme';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { home } from '@/routes';
import { brand, category } from '@/routes/storefront';
import type { MoneyValue, ProductCard, SaleInfo } from '@/types';

type Variant = {
    id: number;
    name: string | null;
    sku: string;
    price: MoneyValue | null;
    compare_at_price: MoneyValue | null;
    option_value_ids: number[];
    purchasable: boolean;
    available: number | null;
    message: string | null;
};

type Props = {
    product: {
        id: number;
        name: string;
        slug: string;
        short_description: string | null;
        description: string | null;
        meta_title: string;
        meta_description: string | null;
        images: { id: number; url: string; alt: string | null }[];
        brand: { name: string; slug: string } | null;
        options: {
            id: number;
            name: string;
            values: { id: number; value: string }[];
        }[];
        variants: Variant[];
        /** A sale (e.g. a flash sale) on this product. */
        sale?: SaleInfo | null;
        /** Buy now override: null follows the theme setting. */
        show_buy_button?: boolean | null;
    };
    breadcrumbs: { name: string; slug: string }[];
    slots: SlotItem[];
    related?: ProductCard[];
    upsells?: ProductCard[];
};

export default function ProductPage({
    product,
    breadcrumbs,
    slots,
    related = [],
    upsells = [],
}: Props) {
    const { productPage } = useStoreTheme();
    const t = useT();
    const [imageIndex, setImageIndex] = useState(0);
    const firstPurchasable =
        product.variants.find((v) => v.purchasable) ?? product.variants[0];
    const [selected, setSelected] = useState<Record<number, number>>(() => {
        const initial: Record<number, number> = {};
        product.options.forEach((option) => {
            const match = option.values.find((v) =>
                firstPurchasable?.option_value_ids.includes(v.id),
            );
            if (match) {
                initial[option.id] = match.id;
            }
        });

        return initial;
    });

    const variant = useMemo(() => {
        if (product.options.length === 0) {
            return product.variants[0];
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
        ...(product.brand || product.options.length > 0
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
                        {product.short_description && (
                            <p className="text-muted-foreground">
                                {product.short_description}
                            </p>
                        )}
                    </div>

                    {product.options.map((option) => (
                        <fieldset key={option.id} className="grid gap-2">
                            <legend className="mb-2 text-sm font-medium">
                                {option.name}
                            </legend>
                            <div className="flex flex-wrap gap-2">
                                {option.values.map((value) => {
                                    const active =
                                        selected[option.id] === value.id;

                                    return (
                                        <Button
                                            key={value.id}
                                            type="button"
                                            size="sm"
                                            variant={
                                                active ? 'default' : 'outline'
                                            }
                                            aria-pressed={active}
                                            onClick={() =>
                                                setSelected((s) => ({
                                                    ...s,
                                                    [option.id]: value.id,
                                                }))
                                            }
                                        >
                                            {value.value}
                                        </Button>
                                    );
                                })}
                            </div>
                        </fieldset>
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

                    <DescriptionPanels
                        mode={pageSettings.description}
                        sections={descriptionSections}
                    />
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
