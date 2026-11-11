import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import AccountController from '@/actions/App/Http/Controllers/Storefront/AccountController';
import { FormField, NativeSelect } from '@/components/admin/form-field';
import { MapPin, Plus } from 'lucide-react';
import { AccountLayout } from '@/components/storefront/account-nav';
import RegionCombobox from '@/components/storefront/region-combobox';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { useT } from '@/lib/i18n';
import { regionsFor } from '@/lib/regions';
import type { Address, Country } from '@/types';

export default function Addresses({
    addresses,
    countries,
}: {
    addresses: Address[];
    countries: Country[];
}) {
    const t = useT();
    const [editing, setEditing] = useState<number | 'new' | null>(
        addresses.length === 0 ? 'new' : null,
    );

    return (
        <>
            <Head title={t('Addresses')} />
            <AccountLayout
                title={t('Addresses')}
                description={t(
                    'Saved addresses are filled in for you at checkout.',
                )}
                actions={
                    editing === null && (
                        <Button onClick={() => setEditing('new')}>
                            <Plus aria-hidden />
                            {t('Add address')}
                        </Button>
                    )
                }
            >
                {editing === 'new' && (
                    <AddressForm
                        countries={countries}
                        onCancel={() => setEditing(null)}
                    />
                )}

                <ul className="grid gap-4 sm:grid-cols-2">
                    {addresses.map((address) =>
                        editing === address.id ? (
                            <li key={address.id} className="sm:col-span-2">
                                <AddressForm
                                    address={address}
                                    countries={countries}
                                    onCancel={() => setEditing(null)}
                                />
                            </li>
                        ) : (
                            <li
                                key={address.id}
                                className="grid content-between gap-4 rounded-xl border bg-card p-5"
                            >
                                <div className="grid gap-3">
                                    <div className="flex items-start justify-between gap-3">
                                        <span className="flex items-center gap-2 font-medium">
                                            <MapPin
                                                className="size-4 text-muted-foreground"
                                                aria-hidden
                                            />
                                            {address.first_name}{' '}
                                            {address.last_name}
                                        </span>
                                        <span className="flex flex-wrap justify-end gap-1">
                                            {address.is_default_shipping && (
                                                <Badge variant="secondary">
                                                    {t('Default shipping')}
                                                </Badge>
                                            )}
                                            {address.is_default_billing && (
                                                <Badge variant="secondary">
                                                    {t('Default billing')}
                                                </Badge>
                                            )}
                                        </span>
                                    </div>
                                    <address className="text-sm leading-relaxed text-muted-foreground not-italic">
                                        {address.company && (
                                            <>
                                                {address.company}
                                                <br />
                                            </>
                                        )}
                                        {address.line1}
                                        {address.line2 && `, ${address.line2}`}
                                        <br />
                                        {address.city}
                                        {address.region &&
                                            `, ${address.region}`}{' '}
                                        {address.postal_code}
                                        <br />
                                        {address.country_name}
                                        {address.phone && (
                                            <>
                                                <br />
                                                {address.phone}
                                            </>
                                        )}
                                    </address>
                                </div>
                                <div className="flex gap-2 border-t pt-4">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setEditing(address.id)}
                                    >
                                        {t('Edit')}
                                    </Button>
                                    <Form
                                        {...AccountController.destroyAddress.form(
                                            address.id,
                                        )}
                                        onBefore={() =>
                                            window.confirm(
                                                t('Remove this address?'),
                                            )
                                        }
                                    >
                                        <Button
                                            type="submit"
                                            variant="ghost"
                                            size="sm"
                                        >
                                            {t('Remove')}
                                        </Button>
                                    </Form>
                                </div>
                            </li>
                        ),
                    )}
                </ul>
            </AccountLayout>
        </>
    );
}

function AddressForm({
    address,
    countries,
    onCancel,
}: {
    address?: Address;
    countries: Country[];
    onCancel: () => void;
}) {
    const form = address
        ? AccountController.updateAddress.form(address.id)
        : AccountController.storeAddress.form();
    const id = (field: string) => `${address?.id ?? 'new'}-${field}`;
    const t = useT();
    const [country, setCountry] = useState(address?.country_code ?? '');
    const [region, setRegion] = useState(address?.region ?? '');
    const regions = regionsFor(country);
    const regionRequired = regions.length > 0;

    return (
        <Form
            {...form}
            options={{ preserveScroll: true }}
            onSuccess={onCancel}
            className="grid gap-5 rounded-xl border bg-card p-5 sm:p-6"
        >
            {({ processing, errors }) => (
                <>
                    <h2 className="font-semibold">
                        {address ? t('Edit address') : t('New address')}
                    </h2>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            id={id('first_name')}
                            label={t('First name')}
                            error={errors.first_name}
                        >
                            <Input
                                id={id('first_name')}
                                name="first_name"
                                defaultValue={address?.first_name}
                                autoComplete="given-name"
                                required
                            />
                        </FormField>
                        <FormField
                            id={id('last_name')}
                            label={t('Last name')}
                            error={errors.last_name}
                        >
                            <Input
                                id={id('last_name')}
                                name="last_name"
                                defaultValue={address?.last_name}
                                autoComplete="family-name"
                                required
                            />
                        </FormField>
                        <FormField
                            id={id('company')}
                            label={t('Company (optional)')}
                            error={errors.company}
                        >
                            <Input
                                id={id('company')}
                                name="company"
                                defaultValue={address?.company ?? ''}
                                autoComplete="organization"
                            />
                        </FormField>
                        <FormField
                            id={id('phone')}
                            label={t('Phone (optional)')}
                            error={errors.phone}
                        >
                            <Input
                                id={id('phone')}
                                name="phone"
                                type="tel"
                                defaultValue={address?.phone ?? ''}
                                autoComplete="tel"
                            />
                        </FormField>
                        <FormField
                            id={id('line1')}
                            label={t('Address')}
                            error={errors.line1}
                        >
                            <Input
                                id={id('line1')}
                                name="line1"
                                defaultValue={address?.line1}
                                autoComplete="address-line1"
                                required
                            />
                        </FormField>
                        <FormField
                            id={id('line2')}
                            label={t('Apartment, suite (optional)')}
                            error={errors.line2}
                        >
                            <Input
                                id={id('line2')}
                                name="line2"
                                defaultValue={address?.line2 ?? ''}
                                autoComplete="address-line2"
                            />
                        </FormField>
                        <FormField
                            id={id('city')}
                            label={t('City')}
                            error={errors.city}
                        >
                            <Input
                                id={id('city')}
                                name="city"
                                defaultValue={address?.city}
                                autoComplete="address-level2"
                                required
                            />
                        </FormField>
                        <FormField
                            id={id('region')}
                            label={
                                regionRequired
                                    ? t('State / province')
                                    : t('State / province (optional)')
                            }
                            error={errors.region}
                        >
                            <RegionCombobox
                                id={id('region')}
                                name="region"
                                value={region}
                                onChange={setRegion}
                                options={regions}
                                placeholder={t('Start typing…')}
                                autoComplete="address-level1"
                                required={regionRequired}
                                invalid={Boolean(errors.region)}
                            />
                        </FormField>
                        <FormField
                            id={id('postal_code')}
                            label={t('Postal code')}
                            error={errors.postal_code}
                        >
                            <Input
                                id={id('postal_code')}
                                name="postal_code"
                                defaultValue={address?.postal_code ?? ''}
                                autoComplete="postal-code"
                            />
                        </FormField>
                        <FormField
                            id={id('country_code')}
                            label={t('Country')}
                            error={errors.country_code}
                        >
                            <NativeSelect
                                id={id('country_code')}
                                name="country_code"
                                defaultValue={address?.country_code ?? ''}
                                onChange={(e) => {
                                    setCountry(e.target.value);

                                    // A region for another country no longer applies.
                                    if (
                                        !regionsFor(e.target.value).includes(
                                            region,
                                        )
                                    ) {
                                        setRegion('');
                                    }
                                }}
                                required
                            >
                                <option value="" disabled>
                                    {t('Choose…')}
                                </option>
                                {countries.map((c) => (
                                    <option key={c.code} value={c.code}>
                                        {c.name}
                                    </option>
                                ))}
                            </NativeSelect>
                        </FormField>
                    </div>
                    <div className="flex flex-wrap gap-5 text-sm">
                        <label className="flex items-center gap-2">
                            <Checkbox
                                name="is_default_shipping"
                                value="1"
                                defaultChecked={address?.is_default_shipping}
                            />
                            {t('Default shipping address')}
                        </label>
                        <label className="flex items-center gap-2">
                            <Checkbox
                                name="is_default_billing"
                                value="1"
                                defaultChecked={address?.is_default_billing}
                            />
                            {t('Default billing address')}
                        </label>
                    </div>
                    <div className="flex gap-2">
                        <Button type="submit" disabled={processing}>
                            {t('Save address')}
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={onCancel}
                        >
                            {t('Cancel')}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
