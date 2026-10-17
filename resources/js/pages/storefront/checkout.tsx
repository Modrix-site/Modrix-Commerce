import { Head, Link, router, useForm } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useRef } from 'react';
import CheckoutController from '@/actions/App/Http/Controllers/Storefront/CheckoutController';
import { FormField } from '@/components/admin/form-field';
import { PlaceSlot } from '@/components/extensions/place-slot';
import InputError from '@/components/input-error';
import { OrderTotals } from '@/components/storefront/order-totals';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useT } from '@/lib/i18n';
import { show as cartShow } from '@/routes/cart';
import type { Address, Country, PricedCart } from '@/types';

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
    }[];
    addresses: Address[];
    email: string | null;
    countries: Country[];
    checkoutToken: string;
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
    addresses,
    email,
    countries,
    checkoutToken,
}: Props) {
    const t = useT();
    const defaultAddress =
        addresses.find((a) => a.is_default_shipping) ?? addresses[0];
    const form = useForm({
        email: email ?? '',
        phone: '',
        shipping: defaultAddress ? fromSaved(defaultAddress) : emptyAddress,
        billing_same: true,
        billing: emptyAddress,
        shipping_method: '',
        payment_method: paymentMethods[0]?.code ?? '',
        customer_note: '',
        save_address: addresses.length === 0,
        checkout_token: checkoutToken,
        expected_total: '',
    });
    const { data, setData } = form;
    const errors = form.errors as Record<string, string | undefined>;
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
                only: ['summary'],
                data: {
                    country: data.shipping.country_code || undefined,
                    region: data.shipping.region || undefined,
                    postal_code: data.shipping.postal_code || undefined,
                    shipping_method: data.shipping_method || undefined,
                },
            });
        }, 300);

        return () => window.clearTimeout(timer);
    }, [
        data.shipping.country_code,
        data.shipping.region,
        data.shipping.postal_code,
        data.shipping_method,
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
        setData('shipping', { ...data.shipping, [field]: value });
    const setBilling = (field: keyof AddressFields, value: string) =>
        setData('billing', { ...data.billing, [field]: value });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        // The total shown is sent only so the server can detect a price change; it is never charged.
        form.transform((d) => ({
            ...d,
            expected_total: summary.grand_total.amount,
        }));
        form.submit(CheckoutController.store(), { preserveScroll: true });
    };

    const generalError = errors.cart ?? errors.total ?? errors.coupon;

    return (
        <>
            <Head title={t('Checkout')} />
            <h1 className="mb-6 text-2xl font-semibold tracking-tight">
                {t('Checkout')}
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
                            <FormField
                                id="phone"
                                label={t('Phone (optional)')}
                                error={errors.phone}
                            >
                                <Input
                                    id="phone"
                                    type="tel"
                                    autoComplete="tel"
                                    value={data.phone}
                                    onChange={(e) =>
                                        setData('phone', e.target.value)
                                    }
                                />
                            </FormField>
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
                        {addresses.length > 0 && (
                            <label className="grid gap-2 text-sm">
                                {t('Use a saved address')}
                                <select
                                    className="h-9 rounded-md border border-input bg-transparent px-2 dark:bg-input/30"
                                    defaultValue={defaultAddress?.id}
                                    onChange={(e) => {
                                        const chosen = addresses.find(
                                            (a) =>
                                                a.id === Number(e.target.value),
                                        );
                                        setData(
                                            'shipping',
                                            chosen
                                                ? fromSaved(chosen)
                                                : emptyAddress,
                                        );
                                    }}
                                >
                                    {addresses.map((a) => (
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
                        ) : summary.shipping_options.length === 0 ? (
                            <p className="text-sm text-destructive">
                                {t('We do not ship to the selected country.')}
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
                                            {option.label}
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
                                {paymentMethods.map((method) => (
                                    <label
                                        key={method.code}
                                        className="grid gap-1 rounded-lg border p-3 text-sm has-[:checked]:border-primary"
                                    >
                                        <span className="flex items-center gap-2">
                                            <input
                                                type="radio"
                                                name="payment_method"
                                                value={method.code}
                                                checked={
                                                    data.payment_method ===
                                                    method.code
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
                                ))}
                            </fieldset>
                        )}
                        <InputError message={errors.payment_method} />
                    </section>

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
                </div>

                <aside
                    className="grid content-start gap-4 rounded-lg border p-5"
                    aria-label={t('Order summary')}
                >
                    <h2 className="font-semibold">{t('Order summary')}</h2>
                    <ul className="grid gap-2 text-sm">
                        {summary.lines.map((line) => (
                            <li
                                key={line.id}
                                className="flex justify-between gap-3"
                            >
                                <span>
                                    {line.product_name}
                                    {line.variant_name && (
                                        <span className="text-muted-foreground">
                                            {' '}
                                            · {line.variant_name}
                                        </span>
                                    )}
                                    <span className="text-muted-foreground">
                                        {' '}
                                        × {line.quantity}
                                    </span>
                                </span>
                                <span className="tabular-nums">
                                    {line.subtotal.formatted}
                                </span>
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
                    {summary.issues.map((issue) => (
                        <p key={issue} className="text-sm text-destructive">
                            {issue}
                        </p>
                    ))}
                    <PlaceSlot place="checkout.before_place_order" />
                    <Button
                        type="submit"
                        className="store-button"
                        disabled={
                            form.processing ||
                            !summary.can_checkout ||
                            !summary.shipping ||
                            paymentMethods.length === 0
                        }
                    >
                        {t('Place order')}
                    </Button>
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
    errors,
}: {
    prefix: string;
    values: AddressFields;
    onChange: (field: keyof AddressFields, value: string) => void;
    countries: Country[];
    errors: Record<string, string | undefined>;
}) {
    const t = useT();
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
            {field('company', t('Company (optional)'), 'organization')}
            {field('phone', t('Phone (optional)'), 'tel')}
            {field('line1', t('Address'), 'address-line1', true)}
            {field('line2', t('Apartment, suite (optional)'), 'address-line2')}
            {field('city', t('City'), 'address-level2', true)}
            {field(
                'region',
                t('State / province (optional)'),
                'address-level1',
            )}
            {field('postal_code', t('Postal code'), 'postal-code')}
            <FormField
                id={`${prefix}-country_code`}
                label={t('Country')}
                error={errors[`${prefix}.country_code`]}
            >
                <select
                    id={`${prefix}-country_code`}
                    value={values.country_code}
                    autoComplete={`${prefix === 'shipping' ? 'shipping' : 'billing'} country`}
                    onChange={(e) => onChange('country_code', e.target.value)}
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
        </div>
    );
}
