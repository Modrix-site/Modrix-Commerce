import { Form } from '@inertiajs/react';
import CartController from '@/actions/App/Http/Controllers/Storefront/CartController';
import InputError from '@/components/input-error';
import { StoreMenuLink as Link } from '@/components/storefront/store-menu-link';
import { ProductLabels } from '@/components/storefront/product-labels';
import { useStoreSettings } from '@/hooks/use-store-settings';
import { useT } from '@/lib/i18n';
import type { ProductCard } from '@/types';
export function SableCard({
    product: p,
    quickAdd = true,
    swatches = true,
    label = 'Add to bag',
}: {
    product: ProductCard;
    quickAdd?: boolean;
    swatches?: boolean;
    label?: string;
}) {
    const t = useT();
    const { catalog } = useStoreSettings();
    const href = '/p/' + encodeURIComponent(p.slug);
    const enabled =
        quickAdd && (p.show_buy_button ?? catalog.quick_add ?? true);
    return (
        <article className="sa-card">
            <Link href={href} className="sa-card-image" aria-label={p.name}>
                {p.image ? (
                    <img
                        src={p.image.url}
                        alt={p.image.alt ?? p.name}
                        loading="lazy"
                    />
                ) : (
                    <span className="sa-no-image">{p.name}</span>
                )}
                {catalog.hover_image && p.hover_image && (
                    <img
                        className="sa-hover-image"
                        src={p.hover_image.url}
                        alt=""
                        loading="lazy"
                    />
                )}
                {catalog.badges && <ProductLabels labels={p.labels} />}
            </Link>
            <div className="sa-card-details">
                <Link href={href} className="sa-card-name">
                    {p.name}
                </Link>
                <p className="sa-price">
                    {p.has_variants && t('From') + ' '}
                    {p.price?.formatted}
                    {p.compare_at_price && (
                        <s>{p.compare_at_price.formatted}</s>
                    )}
                </p>
                {swatches && p.swatches && p.swatches.length > 0 && (
                    <div className="sa-swatches" aria-label="Available colors">
                        {p.swatches.map((s) => (
                            <Link
                                key={s.id}
                                href={href}
                                title={s.value}
                                aria-label={
                                    p.name + ': ' + s.value + ' options'
                                }
                                style={{ backgroundColor: s.color }}
                            />
                        ))}
                    </div>
                )}
            </div>
            {enabled &&
                (p.in_stock ? (
                    p.variant_id != null ? (
                        <Form
                            {...CartController.store.form()}
                            options={{ preserveScroll: true }}
                        >
                            {({ processing, errors }) => (
                                <>
                                    <input
                                        type="hidden"
                                        name="variant_id"
                                        value={p.variant_id ?? ''}
                                    />
                                    <input
                                        type="hidden"
                                        name="quantity"
                                        value="1"
                                    />
                                    <button
                                        className="sa-quick-add"
                                        disabled={processing}
                                    >
                                        {processing ? t('Adding…') : label}
                                    </button>
                                    <InputError
                                        message={
                                            errors.variant_id ?? errors.quantity
                                        }
                                    />
                                </>
                            )}
                        </Form>
                    ) : (
                        <Link className="sa-quick-add" href={href}>
                            {t('Choose options')}
                        </Link>
                    )
                ) : (
                    <span className="sa-sold-out">{t('Out of stock')}</span>
                ))}
        </article>
    );
}
