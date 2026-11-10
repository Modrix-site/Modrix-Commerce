import { Head, Link, router, useForm } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import type { FormEvent } from 'react';
import { useCallback, useEffect, useRef, useState } from 'react';
import CheckoutController from '@/actions/App/Http/Controllers/Storefront/CheckoutController';
import { FormField } from '@/components/admin/form-field';
import { ExtensionSlot } from '@/components/extensions/extension-slot';
import type { SlotItem } from '@/components/extensions/extension-slot';
import { PlaceSlot } from '@/components/extensions/place-slot';
import InputError from '@/components/input-error';
import { OrderTotals } from '@/components/storefront/order-totals';
import { CheckoutCoupon } from '@/components/storefront/page-settings/checkout-coupon';
import { TermsCheckbox } from '@/components/storefront/page-settings/terms-checkbox';
import { TrustBadges } from '@/components/storefront/page-settings/trust-badges';
import RegionCombobox from '@/components/storefront/region-combobox';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useStoreSettings } from '@/hooks/use-store-settings';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { regionsFor } from '@/lib/regions';
import { show as cartShow } from '@/routes/cart';
import type { Address, Country, MoneyValue, PricedCart } from '@/types';

type AddressFields = {
    first_name: string;
    last_name: string;
    company: string;
    line1: string;
    line2: string;
    city: string;
    region: string;
    postal_code: string;
    country_code: string;
    phone: string;
};

type Props = {
    summary: PricedCart;
    paymentMethods: {
        code: string;
        label: string;
        instructions: string | null;
        fee: MoneyValue | null;
    }[];
    paymentSlots?: SlotItem[];
    slots?: SlotItem[];
    addresses: Address[];
    email: string | null;
    countries: Country[];
    soleCountry: string | null;
    deliveryRestriction?: string | null;
    checkoutToken: string;
    /** The store's terms page (Checkout tab "Require accepting the terms"). */
    termsUrl?: string | null;
};

const emptyAddress: AddressFields = {
    first_name: '',
    last_name: '',
    company: '',
    line1: '',
    line2: '',
    city: '',
    region: '',
    postal_code: '',
    country_code: '',
    phone: '',
};

function fromSaved(a: Address): AddressFields {
    return {
        first_name: a.first_name,
        last_name: a.last_name,
        company: a.company ?? '',
        line1: a.line1,
        line2: a.line2 ?? '',
        city: a.city,
        region: a.region ?? '',
        postal_code: a.postal_code ?? '',
        country_code: a.country_code,
        phone: a.phone ?? '',
    };
}

export default function Checkout({
    summary,
    paymentMethods,
    paymentSlots = [],
    slots = [],
    addresses,
    email,
    countries,
    soleCountry,
    deliveryRestriction = null,
    checkoutToken,
    termsUrl = null,
}: Props) {
    const t = useT();
    // Checkout tab settings (customizer).
    const pageSettings = useStoreSettings().checkout;
    const shippingAddresses = addresses.filter((a) =>
        countries.some((c) => c.code === a.country_code),
    );
    const defaultAddress =
        shippingAddresses.find((a) => a.is_default_shipping) ??
        shippingAddresses[0];
    // A store that ships to one country starts with it filled in. The server
    // sends `soleCountry`; fall back to the single option in the list.
    const defaultCountry =
        soleCountry ?? (countries.length === 1 ? countries[0].code : '');
    const form = useForm({
        email: email ?? '',
        phone: '',
        shipping: defaultAddress
            ? fromSaved(defaultAddress)
            : { ...emptyAddress, country_code: defaultCountry },
        billing_same: true,
        billing: { ...emptyAddress, country_code: defaultCountry },
        shipping_method: '',
        payment_method: summary.payment_method ?? paymentMethods[0]?.code ?? '',
        customer_note: '',
        terms: false,
        save_address: addresses.length === 0,
        checkout_token: checkoutToken,
        expected_total: '',
    });
    const { data, setData } = form;
    const errors = form.errors as Record<string, string | undefined>;
    // Payment methods a module reported as unavailable for this order (e.g. a provider's eligibility check).
    const [unavailable, setUnavailable] = useState<Record<string, string>>({});
    const reportAvailability = useCallback(
        (code: string, reason: string | null) =>
            setUnavailable((current) => {
                if ((current[code] ?? null) === reason) {
                    return current;
                }

                const next = { ...current };

                if (reason === null) {
                    delete next[code];
                } else {
                    next[code] = reason;
                }

                return next;
            }),
        [],
    );

    // Move off a method that just became unavailable.
    useEffect(() => {
        if (unavailable[data.payment_method] === undefined) {
            return;
        }

        const fallback = paymentMethods.find(
            (m) => unavailable[m.code] === undefined,
        );

        if (fallback) {
            setData('payment_method', fallback.code);
        }
    }, [unavailable, data.payment_method, paymentMethods, setData]);
    const firstQuote = useRef(true);

    // Re-quote shipping and tax on the server whenever the destination or method changes.
    useEffect(() => {
        if (firstQuote.current && !data.shipping.country_code) {
            firstQuote.current = false;

            return;
        }

        firstQuote.current = false;
        const timer = window.setTimeout(() => {
            router.reload({
                only: ['summary', 'paymentMethods', 'paymentSlots', 'slots'],
                data: {
                    country: data.shipping.country_code || undefined,
                    region: data.shipping.region || undefined,
                    city: data.shipping.city || undefined,
                    postal_code: data.shipping.postal_code || undefined,
                    shipping_method: data.shipping_method || undefined,
                    payment_method: data.payment_method || undefined,
                },
            });
        }, 300);

        return () => window.clearTimeout(timer);
    }, [
        data.shipping.country_code,
        data.shipping.region,
        data.shipping.city,
        data.shipping.postal_code,
        data.shipping_method,
        data.payment_method,
    ]);

    // Drop a selected method that is no longer offered for the new destination.
    useEffect(() => {
        if (
            data.shipping_method &&
            !summary.shipping_options.some(
                (o) => o.code === data.shipping_method,
            )
        ) {
            setData('shipping_method', '');
        }
    }, [summary.shipping_options, data.shipping_method, setData]);

    const setShipping = (field: keyof AddressFields, value: string) =>
        setData('shipping', {
            ...data.shipping,
            [field]: value,
            // A region chosen for another country no longer applies.
            ...(field === 'country_code' &&
            !regionsFor(value).includes(data.shipping.region)
                ? { region: '' }
                : {}),
        });
    const setBilling = (field: keyof AddressFields, value: string) =>
        setData('billing', {
            ...data.billing,
            [field]: value,
            ...(field === 'country_code' &&
            !regionsFor(value).includes(data.billing.region)
                ? { region: '' }
                : {}),
        });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (summary.payment_method !== data.payment_method) {
            return;
        }
        // The total shown is sent only so the server can detect a price change; it is never charged.
        form.transform((d) => ({
            ...d,
            expected_total: summary.grand_total.amount,
        }));
        form.submit(CheckoutController.store(), { preserveScroll: true });
    };

    const generalError = errors.cart ?? errors.total ?? errors.coupon;
    const paymentQuotePending = summary.payment_method !== data.payment_method;

    return (
        <>
            <Head title={t('Checkout')} />
            <h1 className="mb-6 text-2xl font-semibold tracking-tight">
                {pageSettings.heading === 'Checkout'
                    ? t('Checkout')
                    : pageSettings.heading}
            </h1>

            {generalError && (
                <Alert variant="destructive" className="mb-6">
                    <TriangleAlert />
                    <AlertDescription>
                        {generalError}{' '}
                        {errors.cart && (
                            <Link href={cartShow()} className="underline">
                                {t('Go to cart')}
                            </Link>
                        )}
                    </AlertDescription>
                </Alert>
            )}

            <form onSubmit={submit} className="grid gap-8 lg:grid-cols-3">
                <div className="grid content-start gap-8 lg:col-span-2">
                    <section
                        className="grid gap-4"
                        aria-labelledby="contact-heading"
                    >
                        <h2
                            id="contact-heading"
                            className="text-lg font-semibold"
                        >
                            {t('Contact')}
                        </h2>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField
                                id="email"
                                label={t('Email')}
                                error={errors.email}
                            >
                                <Input
                                    id="email"
                                    type="email"
                                    autoComplete="email"
                                    value={data.email}
                                    readOnly={email !== null}
                                    onChange={(e) =>
                                        setData('email', e.target.value)
                                    }
                                    required
                                />
                            </FormField>
                            {pageSettings.phone !== 'hidden' && (
                                <FormField
                                    id="phone"
                                    label={
                                        pageSettings.phone === 'required'
                                            ? t('Phone')
                                            : t('Phone (optional)')
                                    }
                                    error={errors.phone}
                                >
                                    <Input
                                        id="phone"
                                        type="tel"
                                        autoComplete="tel"
                                        value={data.phone}
                                        required={
                                            pageSettings.phone === 'required'
                                        }
                                        onChange={(e) =>
                                            setData('phone', e.target.value)
                                        }
                                    />
                                </FormField>
                            )}
                        </div>
                    </section>

                    <section
                        className="grid gap-4"
                        aria-labelledby="shipping-heading"
                    >
                        <h2
                            id="shipping-heading"
                            className="text-lg font-semibold"
                        >
                            {summary.requires_shipping === false
                                ? t('Billing address')
                                : t('Shipping address')}
                        </h2>
                        {deliveryRestriction && (
                            <p className="text-sm text-muted-foreground">
                                {t('We deliver only to :location.', {
                                    location: deliveryRestriction,
                                })}
                            </p>
                        )}
                        {shippingAddresses.length > 0 && (
                            <label className="grid gap-2 text-sm">
                                {t('Use a saved address')}
                                <select
                                    className="h-9 rounded-md border border-input bg-transparent px-2 dark:bg-input/30"
                                    defaultValue={defaultAddress?.id}
                                    onChange={(e) => {
                                        const chosen = shippingAddresses.find(
                                            (a) =>
                                                a.id === Number(e.target.value),
                                        );
                                        setData(
                                            'shipping',
                                            chosen
                                                ? fromSaved(chosen)
                                                : // A new address keeps the store's only country,
                                                  // so shipping can be quoted.
                                                  {
                                                      ...emptyAddress,
                                                      country_code:
                                                          defaultCountry,
                                                  },
                                        );
                                    }}
                                >
                                    {shippingAddresses.map((a) => (
                                        <option key={a.id} value={a.id}>
                                            {a.first_name} {a.last_name},{' '}
                                            {a.line1}, {a.city}
                                        </option>
                                    ))}
                                    <option value="">
                                        {t('Enter a new address')}
                                    </option>
                                </select>
                            </label>
                        )}
                        <AddressInputs
                            prefix="shipping"
                            values={data.shipping}
                            onChange={setShipping}
                            countries={countries}
                            soleCountry={defaultCountry || null}
                            errors={errors}
                        />
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={data.billing_same}
                                onCheckedChange={(c) =>
                                    setData('billing_same', c === true)
                                }
                            />
                            {t('Billing address is the same as shipping')}
                        </label>
                    </section>

                    {!data.billing_same && (
                        <section
                            className="grid gap-4"
                            aria-labelledby="billing-heading"
                        >
                            <h2
                                id="billing-heading"
                                className="text-lg font-semibold"
                            >
                                {t('Billing address')}
                            </h2>
                            <AddressInputs
                                prefix="billing"
                                values={data.billing}
                                onChange={setBilling}
                                countries={countries}
                                soleCountry={defaultCountry || null}
                                errors={errors}
                            />
                        </section>
                    )}

                    <section
                        className="grid gap-3"
                        aria-labelledby="method-heading"
                    >
                        <h2
                            id="method-heading"
                            className="text-lg font-semibold"
                        >
                            {t('Shipping method')}
                        </h2>
                        {summary.requires_shipping === false ? (
                            <p className="text-sm text-muted-foreground">
                                {t(
                                    'No shipping needed — your downloads are available after payment.',
                                )}
                            </p>
                        ) : !data.shipping.country_code ? (
                            <p className="text-sm text-muted-foreground">
                                {t(
                                    'Enter your country to see shipping options.',
                                )}
                            </p>
                        ) : summary.issues.includes(
                              t(
                                  'Add your state or city to see the shipping method and amount.',
                              ),
                          ) ? (
                            <p className="text-sm text-muted-foreground">
                                {t(
                                    'Add your state or city to see the shipping method and amount.',
                                )}
                            </p>
                        ) : summary.shipping_options.length === 0 ? (
                            <p className="text-sm text-destructive">
                                {t(
                                    'We do not ship to the selected destination.',
                                )}
                            </p>
                        ) : (
                            <fieldset className="grid gap-2">
                                <legend className="sr-only">
                                    {t('Shipping method')}
                                </legend>
                                {summary.shipping_options.map((option) => (
                                    <label
                                        key={option.code}
                                        className="flex items-center justify-between gap-3 rounded-lg border p-3 text-sm has-[:checked]:border-primary"
                                    >
                                        <span className="flex items-center gap-2">
                                            <input
                                                type="radio"
                                                name="shipping_method"
                                                value={option.code}
                                                checked={
                                                    data.shipping_method ===
                                                    option.code
                                                }
                                                onChange={() =>
                                                    setData(
                                                        'shipping_method',
                                                        option.code,
                                                    )
                                                }
                                            />
                                            <span>
                                                <span>
                                                    {option.label}
                                                    {option.description && (
                                                        <span className="block text-xs text-muted-foreground">
                                                            {option.description}
                                                        </span>
                                                    )}
                                                </span>
                                                {option.description && (
                                                    <span className="block text-xs text-muted-foreground">
                                                        {option.description}
                                                    </span>
                                                )}
                                            </span>
                                        </span>
                                        <span className="tabular-nums">
                                            {Number(option.amount.amount) === 0
                                                ? t('Free')
                                                : option.amount.formatted}
                                        </span>
                                    </label>
                                ))}
                            </fieldset>
                        )}
                        <InputError message={errors.shipping_method} />
                    </section>

                    <section
                        className="grid gap-3"
                        aria-labelledby="payment-heading"
                    >
                        <h2
                            id="payment-heading"
                            className="text-lg font-semibold"
                        >
                            {t('Payment')}
                        </h2>
                        {paymentMethods.length === 0 ? (
                            <p className="text-sm text-destructive">
                                {t(
                                    'No payment method is available. The store has not enabled checkout yet.',
                                )}
                            </p>
                        ) : (
                            <fieldset className="grid gap-2">
                                <legend className="sr-only">
                                    {t('Payment method')}
                                </legend>
                                {paymentMethods.map((method) => {
                                    const selected =
                                        data.payment_method === method.code;
                                    const reason = unavailable[method.code];

                                    return (
                                        <div
                                            key={method.code}
                                            className="grid gap-2 rounded-lg border p-3 text-sm has-[:checked]:border-primary"
                                        >
                                            <label className="grid gap-1">
                                                <span className="flex items-center gap-2">
                                                    <input
                                                        type="radio"
                                                        name="payment_method"
                                                        value={method.code}
                                                        checked={selected}
                                                        disabled={
                                                            reason !== undefined
                                                        }
                                                        onChange={() =>
                                                            setData(
                                                                'payment_method',
                                                                method.code,
                                                            )
                                                        }
                                                    />
                                                    {method.label}
                                                </span>
                                                {method.instructions && (
                                                    <span className="pl-5 text-muted-foreground">
                                                        {method.instructions}
                                                    </span>
                                                )}
                                            </label>
                                            {selected &&
                                                method.fee &&
                                                Number(method.fee.amount) >
                                                    0 && (
                                                    <p
                                                        className="pl-5 text-muted-foreground"
                                                        role="status"
                                                    >
                                                        {t(
                                                            'An additional :fee cash on delivery fee will be collected by the courier.',
                                                            {
                                                                fee: method.fee
                                                                    .formatted,
                                                            },
                                                        )}
                                                    </p>
                                                )}
                                            {reason !== undefined && (
                                                <p className="pl-5 text-muted-foreground">
                                                    {reason}
                                                </p>
                                            )}
                                            <ExtensionSlot
                                                items={paymentSlots.filter(
                                                    (item) =>
                                                        item.props.method ===
                                                        method.code,
                                                )}
                                                className="pl-5"
                                                extraProps={{
                                                    checkout: {
                                                        selected,
                                                        email: data.email,
                                                        phone:
                                                            data.phone ||
                                                            data.shipping.phone,
                                                        reportAvailability: (
                                                            next: string | null,
                                                        ) =>
                                                            reportAvailability(
                                                                method.code,
                                                                next,
                                                            ),
                                                    },
                                                }}
                                            />
                                        </div>
                                    );
                                })}
                            </fieldset>
                        )}
                        <InputError message={errors.payment_method} />
                    </section>

                    {pageSettings.order_note && (
                        <FormField
                            id="customer_note"
                            label={t('Order note (optional)')}
                            error={errors.customer_note}
                        >
                            <Textarea
                                id="customer_note"
                                rows={2}
                                value={data.customer_note}
                                onChange={(e) =>
                                    setData('customer_note', e.target.value)
                                }
                            />
                        </FormField>
                    )}
                </div>

                <aside
                    className={cn(
                        'grid content-start gap-4 self-start rounded-lg border p-5',
                        pageSettings.summary_side === 'left' &&
                            'lg:order-first',
                        pageSettings.sticky_summary && 'lg:sticky lg:top-24',
                    )}
                    aria-label={t('Order summary')}
                    aria-busy={paymentQuotePending}
                >
                    <h2 className="font-semibold">{t('Order summary')}</h2>
                    <ul className="grid gap-2 text-sm">
                        {summary.lines.map((line) => (
                            <li
                                key={line.id}
                                className="flex justify-between gap-3"
                            >
                                {pageSettings.summary_images && line.image && (
                                    <img
                                        src={line.image.url}
                                        alt=""
                                        className="size-12 shrink-0 rounded border object-cover"
                                    />
                                )}
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-start justify-between gap-2">
                                        <p className="font-medium">
                                            {line.product_name}
                                        </p>
                                        <span className="shrink-0 whitespace-nowrap tabular-nums">
                                            {line.addons &&
                                            line.addons.length > 0 &&
                                            line.base_subtotal
                                                ? line.base_subtotal.formatted
                                                : line.subtotal.formatted}
                                        </span>
                                    </div>
                                    {line.addons && line.addons.length > 0 ? (
                                        <ul className="mt-1 grid gap-0.5 text-xs text-muted-foreground">
                                            {line.addons.map((addon) => (
                                                <li
                                                    key={addon.id}
                                                    className="flex items-center justify-between gap-2"
                                                >
                                                    <span>+ {addon.name}</span>
                                                    <span className="shrink-0 whitespace-nowrap tabular-nums">
                                                        +
                                                        {
                                                            addon.subtotal
                                                                .formatted
                                                        }
                                                    </span>
                                                </li>
                                            ))}
                                        </ul>
                                    ) : (
                                        line.variant_name && (
                                            <p className="mt-1 text-muted-foreground">
                                                {line.variant_name}
                                            </p>
                                        )
                                    )}
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {t('Quantity')}: {line.quantity}
                                    </p>
                                </div>
                            </li>
                        ))}
                    </ul>
                    <OrderTotals
                        totals={{
                            ...summary,
                            shipping_total: summary.shipping
                                ? summary.shipping_total
                                : null,
                        }}
                    />
                    {pageSettings.coupon && (
                        <CheckoutCoupon code={summary.coupon_code} />
                    )}
                    {summary.issues.map((issue) => (
                        <p
                            key={issue}
                            className={
                                issue ===
                                t(
                                    'Add your state or city to see the shipping method and amount.',
                                )
                                    ? 'text-sm text-muted-foreground'
                                    : 'text-sm text-destructive'
                            }
                        >
                            {issue}
                        </p>
                    ))}
                    <ExtensionSlot
                        items={slots}
                        className="grid gap-4"
                        extraProps={{ summary }}
                    />
                    {pageSettings.terms && (
                        <TermsCheckbox
                            url={termsUrl}
                            checked={data.terms}
                            onChange={(checked) => setData('terms', checked)}
                            error={errors.terms}
                        />
                    )}
                    <PlaceSlot place="checkout.before_place_order" />
                    <Button
                        type="submit"
                        className="store-button"
                        disabled={
                            form.processing ||
                            paymentQuotePending ||
                            !summary.can_checkout ||
                            !summary.shipping ||
                            paymentMethods.length === 0
                        }
                    >
                        {pageSettings.button_label === 'Place order'
                            ? t('Place order')
                            : pageSettings.button_label}
                    </Button>
                    {pageSettings.trust_badges && <TrustBadges />}
                    <p className="text-xs text-muted-foreground">
                        {t(
                            'Your order is not paid until the store confirms your payment.',
                        )}
                    </p>
                </aside>
            </form>
        </>
    );
}

function AddressInputs({
    prefix,
    values,
    onChange,
    countries,
    soleCountry,
    errors,
}: {
    prefix: string;
    values: AddressFields;
    onChange: (field: keyof AddressFields, value: string) => void;
    countries: Country[];
    soleCountry: string | null;
    errors: Record<string, string | undefined>;
}) {
    const t = useT();
    const pageSettings = useStoreSettings().checkout;
    const field = (
        name: keyof AddressFields,
        label: string,
        autoComplete: string,
        required = false,
    ) => (
        <FormField
            id={`${prefix}-${name}`}
            label={label}
            error={errors[`${prefix}.${name}`]}
        >
            <Input
                id={`${prefix}-${name}`}
                autoComplete={`${prefix === 'shipping' ? 'shipping' : 'billing'} ${autoComplete}`}
                value={values[name]}
                onChange={(e) => onChange(name, e.target.value)}
                required={required}
            />
        </FormField>
    );

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            {field('first_name', t('First name'), 'given-name', true)}
            {field('last_name', t('Last name'), 'family-name', true)}
            {pageSettings.company &&
                field('company', t('Company (optional)'), 'organization')}
            {pageSettings.phone !== 'hidden' &&
                field('phone', t('Phone (optional)'), 'tel')}
            {field('line1', t('Address'), 'address-line1', true)}
            {pageSettings.address_line2 &&
                field(
                    'line2',
                    t('Apartment, suite (optional)'),
                    'address-line2',
                )}
            {field('city', t('City'), 'address-level2', true)}
            {(() => {
                // The country that applies: a single-country store locks it in,
                // so the region list still follows the shown country.
                const effectiveCountry =
                    values.country_code ||
                    soleCountry ||
                    (countries.length === 1 ? countries[0].code : '');
                const regions = regionsFor(effectiveCountry);
                const required = regions.length > 0;

                // Typeable picker with autosuggest for countries that have a
                // region list (US states, CA provinces, AE emirates, ...).
                // Everywhere else stays free text.
                if (required) {
                    return (
                        <FormField
                            id={`${prefix}-region`}
                            label={t('State / province')}
                            error={errors[`${prefix}.region`]}
                        >
                            <RegionCombobox
                                id={`${prefix}-region`}
                                name={`${prefix}_region`}
                                value={values.region}
                                onChange={(value) => onChange('region', value)}
                                options={regions}
                                placeholder={t('Start typing…')}
                                autoComplete={`${prefix === 'shipping' ? 'shipping' : 'billing'} address-level1`}
                                required
                                invalid={Boolean(errors[`${prefix}.region`])}
                            />
                        </FormField>
                    );
                }

                return field(
                    'region',
                    t('State / province (optional)'),
                    'address-level1',
                );
            })()}
            {field('postal_code', t('Postal code'), 'postal-code')}
            {soleCountry ? (
                <FormField id={`${prefix}-country_code`} label={t('Country')}>
                    <div
                        id={`${prefix}-country_code`}
                        className="flex h-9 items-center rounded-md border border-input bg-muted px-3 text-sm"
                    >
                        {countries.find((c) => c.code === soleCountry)?.name ??
                            soleCountry}
                    </div>
                    <input
                        type="hidden"
                        name={`${prefix}_country_code`}
                        value={soleCountry}
                    />
                </FormField>
            ) : (
                <FormField
                    id={`${prefix}-country_code`}
                    label={t('Country')}
                    error={errors[`${prefix}.country_code`]}
                >
                    <select
                        id={`${prefix}-country_code`}
                        value={values.country_code}
                        autoComplete={`${prefix === 'shipping' ? 'shipping' : 'billing'} country`}
                        onChange={(e) =>
                            onChange('country_code', e.target.value)
                        }
                        required
                        className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm dark:bg-input/30"
                    >
                        <option value="" disabled>
                            {t('Choose…')}
                        </option>
                        {countries.map((c) => (
                            <option key={c.code} value={c.code}>
                                {c.name}
                            </option>
                        ))}
                    </select>
                </FormField>
            )}
        </div>
    );
}
