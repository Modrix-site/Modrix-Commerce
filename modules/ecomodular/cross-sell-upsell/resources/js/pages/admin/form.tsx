import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { FormField, NativeSelect } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ProductPicker } from '../../components/product-picker';
import type { PickedProduct } from '../../components/product-picker';
import { stateBadge } from './index';
import type { OfferSummary } from './index';

type Offer = OfferSummary & {
    heading: string | null;
    trigger_ids: number[];
    product_ids: number[];
    trigger_products: PickedProduct[];
    products: PickedProduct[];
    starts_at_local: string | null;
    ends_at_local: string | null;
    update_url: string;
};

type Props = {
    offer: Offer | null;
    currency: string;
    timezone: string;
    categories: { id: number; name: string }[];
    storeUrl: string;
    indexUrl: string;
    searchUrl: string;
};

const PLACES = [
    {
        key: 'show_on_product',
        label: 'Product page',
        hint: 'A block under "Add to cart" (movable in the theme customizer).',
    },
    {
        key: 'show_in_popup',
        label: 'Add-to-cart pop-up',
        hint: 'Right after the shopper adds a trigger product.',
    },
    {
        key: 'show_in_cart',
        label: 'Cart',
        hint: '"Complete your order" under the cart items.',
    },
    {
        key: 'show_at_checkout',
        label: 'Checkout add-on',
        hint: 'A tick box above "Place order" (products without options).',
    },
] as const;

type PlaceKey = (typeof PLACES)[number]['key'];

export default function CrossSellForm({
    offer,
    currency,
    timezone,
    categories,
    storeUrl,
    indexUrl,
    searchUrl,
}: Props) {
    const [type, setType] = useState(offer?.type ?? 'cross_sell');
    const [triggerType, setTriggerType] = useState(
        offer?.trigger_type ?? 'any',
    );
    const [mode, setMode] = useState(offer?.suggestion_mode ?? 'manual');
    const [discountType, setDiscountType] = useState<string>(
        offer?.discount_type ?? '',
    );
    const [triggerProducts, setTriggerProducts] = useState<PickedProduct[]>(
        offer?.trigger_products ?? [],
    );
    const [triggerCategories, setTriggerCategories] = useState<number[]>(
        offer?.trigger_type === 'categories' ? offer.trigger_ids : [],
    );
    const [products, setProducts] = useState<PickedProduct[]>(
        offer?.products ?? [],
    );
    const [places, setPlaces] = useState<Record<PlaceKey, boolean>>({
        show_on_product: offer?.show_on_product ?? true,
        show_in_popup: offer?.show_in_popup ?? true,
        show_in_cart: offer?.show_in_cart ?? true,
        show_at_checkout: offer?.show_at_checkout ?? false,
    });
    const [active, setActive] = useState(offer?.is_active ?? true);

    const title = offer ? offer.name : 'New offer';

    return (
        <>
            <Head title={offer ? `Offer: ${offer.name}` : 'New offer'} />
            <AdminPage>
                <PageHeader
                    title={title}
                    description="Suggest add-ons (cross-sell) or a better version (upsell) of what the shopper is looking at or has in the cart."
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={indexUrl}>All offers</Link>
                        </Button>
                    }
                />

                <Form
                    action={offer ? offer.update_url : storeUrl}
                    method={offer ? 'put' : 'post'}
                    options={{ preserveScroll: true }}
                    className="grid gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        Offer
                                        {offer && (
                                            <Badge
                                                variant={
                                                    stateBadge[offer.state]
                                                        .variant
                                                }
                                            >
                                                {stateBadge[offer.state].label}
                                            </Badge>
                                        )}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-4 sm:grid-cols-2">
                                    <FormField
                                        id="name"
                                        label="Name"
                                        error={errors.name}
                                        hint="Only staff see this."
                                    >
                                        <Input
                                            id="name"
                                            name="name"
                                            defaultValue={offer?.name}
                                            maxLength={120}
                                            required
                                        />
                                    </FormField>
                                    <FormField
                                        id="type"
                                        label="Type"
                                        error={errors.type}
                                    >
                                        <NativeSelect
                                            id="type"
                                            name="type"
                                            value={type}
                                            onChange={(e) =>
                                                setType(
                                                    e.target.value as
                                                        | 'upsell'
                                                        | 'cross_sell',
                                                )
                                            }
                                        >
                                            <option value="cross_sell">
                                                Cross-sell (add-ons)
                                            </option>
                                            <option value="upsell">
                                                Upsell (a better version)
                                            </option>
                                        </NativeSelect>
                                    </FormField>
                                    <div className="sm:col-span-2">
                                        <FormField
                                            id="heading"
                                            label="Heading shoppers see"
                                            error={errors.heading}
                                            hint={`Leave empty for “${type === 'upsell' ? 'Upgrade your choice' : 'Goes well with'}”.`}
                                        >
                                            <Input
                                                id="heading"
                                                name="heading"
                                                defaultValue={
                                                    offer?.heading ?? ''
                                                }
                                                maxLength={120}
                                            />
                                        </FormField>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Trigger</CardTitle>
                                    <CardDescription>
                                        The offer shows for these products — on
                                        their page, after they are added to the
                                        cart, or while they are in it.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-4">
                                    <FormField
                                        id="trigger_type"
                                        label="Shown for"
                                        error={errors.trigger_type}
                                    >
                                        <NativeSelect
                                            id="trigger_type"
                                            name="trigger_type"
                                            value={triggerType}
                                            onChange={(e) =>
                                                setTriggerType(
                                                    e.target.value as
                                                        | 'any'
                                                        | 'products'
                                                        | 'categories',
                                                )
                                            }
                                        >
                                            <option value="any">
                                                Any product
                                            </option>
                                            <option value="products">
                                                Specific products
                                            </option>
                                            <option value="categories">
                                                Products in categories
                                            </option>
                                        </NativeSelect>
                                    </FormField>

                                    {triggerType === 'products' && (
                                        <div className="grid gap-2">
                                            <Label>Trigger products</Label>
                                            <ProductPicker
                                                name="trigger_ids"
                                                searchUrl={searchUrl}
                                                value={triggerProducts}
                                                onChange={setTriggerProducts}
                                                max={100}
                                            />
                                            <InputError
                                                message={errors.trigger_ids}
                                            />
                                        </div>
                                    )}

                                    {triggerType === 'categories' && (
                                        <div className="grid gap-2">
                                            <Label>Trigger categories</Label>
                                            {triggerCategories.map((id) => (
                                                <input
                                                    key={id}
                                                    type="hidden"
                                                    name="trigger_ids[]"
                                                    value={id}
                                                />
                                            ))}
                                            {categories.length === 0 ? (
                                                <p className="text-sm text-muted-foreground">
                                                    There are no categories yet.
                                                </p>
                                            ) : (
                                                <div className="grid max-h-64 gap-2 overflow-y-auto rounded-md border p-3 sm:grid-cols-2">
                                                    {categories.map((c) => (
                                                        <label
                                                            key={c.id}
                                                            className="flex items-center gap-2 text-sm"
                                                        >
                                                            <Checkbox
                                                                checked={triggerCategories.includes(
                                                                    c.id,
                                                                )}
                                                                onCheckedChange={(
                                                                    on,
                                                                ) =>
                                                                    setTriggerCategories(
                                                                        (
                                                                            ids,
                                                                        ) =>
                                                                            on ===
                                                                            true
                                                                                ? [
                                                                                      ...ids,
                                                                                      c.id,
                                                                                  ]
                                                                                : ids.filter(
                                                                                      (
                                                                                          x,
                                                                                      ) =>
                                                                                          x !==
                                                                                          c.id,
                                                                                  ),
                                                                    )
                                                                }
                                                            />
                                                            {c.name}
                                                        </label>
                                                    ))}
                                                </div>
                                            )}
                                            <InputError
                                                message={errors.trigger_ids}
                                            />
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Suggested products</CardTitle>
                                    <CardDescription>
                                        Products already in the cart, sold out
                                        or unpublished are never suggested.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-4">
                                    <FormField
                                        id="suggestion_mode"
                                        label="Suggestions"
                                        error={errors.suggestion_mode}
                                    >
                                        <NativeSelect
                                            id="suggestion_mode"
                                            name="suggestion_mode"
                                            value={mode}
                                            onChange={(e) =>
                                                setMode(
                                                    e.target.value as
                                                        | 'manual'
                                                        | 'automatic',
                                                )
                                            }
                                        >
                                            <option value="manual">
                                                Products I pick
                                            </option>
                                            <option value="automatic">
                                                Automatic (same category
                                                {type === 'upsell'
                                                    ? ', higher price'
                                                    : ''}
                                                )
                                            </option>
                                        </NativeSelect>
                                    </FormField>
                                    {mode === 'manual' && (
                                        <div className="grid gap-2">
                                            <ProductPicker
                                                name="product_ids"
                                                searchUrl={searchUrl}
                                                value={products}
                                                onChange={setProducts}
                                                max={12}
                                            />
                                            <InputError
                                                message={errors.product_ids}
                                            />
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Where it shows</CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-3 sm:grid-cols-2">
                                    {PLACES.map((place) => (
                                        <label
                                            key={place.key}
                                            className="flex items-start gap-3 rounded-md border p-3"
                                        >
                                            <input
                                                type="hidden"
                                                name={place.key}
                                                value={
                                                    places[place.key]
                                                        ? '1'
                                                        : '0'
                                                }
                                            />
                                            <Checkbox
                                                checked={places[place.key]}
                                                onCheckedChange={(on) =>
                                                    setPlaces((p) => ({
                                                        ...p,
                                                        [place.key]:
                                                            on === true,
                                                    }))
                                                }
                                                className="mt-0.5"
                                            />
                                            <span className="grid gap-0.5">
                                                <span className="text-sm font-medium">
                                                    {place.label}
                                                </span>
                                                <span className="text-xs text-muted-foreground">
                                                    {place.hint}
                                                </span>
                                            </span>
                                        </label>
                                    ))}
                                    <div className="sm:col-span-2">
                                        <InputError
                                            message={errors.show_on_product}
                                        />
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Discount and schedule</CardTitle>
                                    <CardDescription>
                                        The discount applies when the product is
                                        added from this offer, while a trigger
                                        product is in the cart (one discounted
                                        unit per trigger unit). Coupons apply on
                                        top. Times are in the store time zone (
                                        {timezone}).
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-4 sm:grid-cols-2">
                                    <FormField
                                        id="discount_type"
                                        label="Discount"
                                        error={errors.discount_type}
                                    >
                                        <NativeSelect
                                            id="discount_type"
                                            name="discount_type"
                                            value={discountType}
                                            onChange={(e) =>
                                                setDiscountType(e.target.value)
                                            }
                                        >
                                            <option value="">
                                                No discount
                                            </option>
                                            <option value="percent">
                                                Percentage off
                                            </option>
                                            <option value="fixed">
                                                Fixed amount off ({currency})
                                            </option>
                                        </NativeSelect>
                                    </FormField>
                                    {discountType !== '' && (
                                        <FormField
                                            id="discount_value"
                                            label={
                                                discountType === 'percent'
                                                    ? 'Percentage (1–90)'
                                                    : `Amount per unit (${currency})`
                                            }
                                            error={errors.discount_value}
                                        >
                                            <Input
                                                id="discount_value"
                                                name="discount_value"
                                                inputMode="decimal"
                                                defaultValue={
                                                    offer?.discount_value
                                                        ? String(
                                                              Number(
                                                                  offer.discount_value,
                                                              ),
                                                          )
                                                        : ''
                                                }
                                                placeholder={
                                                    discountType === 'percent'
                                                        ? '10'
                                                        : '5.00'
                                                }
                                            />
                                        </FormField>
                                    )}
                                    <FormField
                                        id="starts_at"
                                        label="Starts (optional)"
                                        error={errors.starts_at}
                                    >
                                        <Input
                                            id="starts_at"
                                            name="starts_at"
                                            type="datetime-local"
                                            defaultValue={
                                                offer?.starts_at_local ?? ''
                                            }
                                        />
                                    </FormField>
                                    <FormField
                                        id="ends_at"
                                        label="Ends (optional)"
                                        error={errors.ends_at}
                                    >
                                        <Input
                                            id="ends_at"
                                            name="ends_at"
                                            type="datetime-local"
                                            defaultValue={
                                                offer?.ends_at_local ?? ''
                                            }
                                        />
                                    </FormField>
                                    <FormField
                                        id="priority"
                                        label="Priority"
                                        error={errors.priority}
                                        hint="When several offers match, the highest priority shows first."
                                    >
                                        <Input
                                            id="priority"
                                            name="priority"
                                            type="number"
                                            min={-1000}
                                            max={1000}
                                            defaultValue={offer?.priority ?? 0}
                                        />
                                    </FormField>
                                    <label className="flex items-center gap-2 self-end pb-2 text-sm">
                                        <input
                                            type="hidden"
                                            name="is_active"
                                            value={active ? '1' : '0'}
                                        />
                                        <Checkbox
                                            checked={active}
                                            onCheckedChange={(on) =>
                                                setActive(on === true)
                                            }
                                        />
                                        Active
                                    </label>
                                </CardContent>
                            </Card>

                            <div className="flex gap-2">
                                <Button type="submit" disabled={processing}>
                                    {offer ? 'Save offer' : 'Create offer'}
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href={indexUrl}>Cancel</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </AdminPage>
        </>
    );
}
