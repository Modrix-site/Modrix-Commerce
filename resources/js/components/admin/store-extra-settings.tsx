import { FormField } from '@/components/admin/form-field';
import {
    Card,
    CardHeader,
    CardTitle,
    CardDescription,
    CardContent,
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
type Option = { code: string; name: string };
type Rate = { code: string; rate: string };
type Props = {
    section?: 'business' | 'seo' | 'currencies';
    profile: Record<string, string | null>;
    countries: Option[];
    currencies: Option[];
    rates: Rate[];
    setRates: (rates: Rate[]) => void;
    baseCurrency: string;
    errors: Record<string, string>;
};
export function StoreExtraSettings(p: Props) {
    const selectClass =
        'h-9 w-full rounded-md border bg-background px-3 text-sm';
    return (
        <>
            {(!p.section || p.section === 'business') && (
                <Card>
                    <CardHeader>
                        <CardTitle>Business and contact details</CardTitle>
                        <CardDescription>
                            Store contact information and public business
                            profile.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        {[
                            ['phone', 'Contact phone'],
                            ['business_hours', 'Business hours'],
                            ['tagline', 'Store tagline'],
                        ].map(([key, label]) => (
                            <FormField
                                key={key}
                                id={`profile-${key}`}
                                label={label}
                                error={p.errors[`profile.${key}`]}
                            >
                                <Input
                                    id={`profile-${key}`}
                                    name={`profile[${key}]`}
                                    defaultValue={p.profile[key] ?? ''}
                                />
                            </FormField>
                        ))}
                        <p className="text-sm text-muted-foreground sm:col-span-2">
                            Store country and location are managed in Regional
                            settings.
                        </p>
                    </CardContent>
                </Card>
            )}
            {(!p.section || p.section === 'seo') && (
                <Card>
                    <CardHeader>
                        <CardTitle>Search engine settings</CardTitle>
                        <CardDescription>
                            Default metadata for your storefront.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4">
                        <FormField
                            id="meta-title"
                            label="SEO title"
                            error={p.errors['profile.meta_title']}
                        >
                            <Input
                                id="meta-title"
                                name="profile[meta_title]"
                                defaultValue={p.profile.meta_title ?? ''}
                            />
                        </FormField>
                        <FormField
                            id="meta-description"
                            label="SEO description"
                            error={p.errors['profile.meta_description']}
                        >
                            <Textarea
                                id="meta-description"
                                name="profile[meta_description]"
                                defaultValue={p.profile.meta_description ?? ''}
                            />
                        </FormField>
                    </CardContent>
                </Card>
            )}
            {(!p.section || p.section === 'currencies') && (
                <Card>
                    <CardHeader>
                        <CardTitle>Storefront currencies</CardTitle>
                        <CardDescription>
                            Optional currencies shoppers can browse in, on top
                            of {p.baseCurrency}. Leave this empty and the
                            storefront shows only {p.baseCurrency}. Set how many
                            units equal 1 {p.baseCurrency}. Checkout and
                            payments stay in {p.baseCurrency}. Changing the
                            default currency clears these rates.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4">
                        <p className="text-sm">
                            Base currency: <strong>{p.baseCurrency}</strong>{' '}
                            (rate 1)
                        </p>
                        {p.rates.map((row, i) => (
                            <div
                                key={i}
                                className="grid gap-3 rounded-lg border p-3 sm:grid-cols-[1fr_1fr_auto]"
                            >
                                <FormField
                                    id={`currency-${i}`}
                                    label="Display currency"
                                    error={
                                        p.errors[`display_currencies.${i}.code`]
                                    }
                                >
                                    <select
                                        id={`currency-${i}`}
                                        className={selectClass}
                                        value={row.code}
                                        onChange={(e) =>
                                            p.setRates(
                                                p.rates.map((r, n) =>
                                                    n === i
                                                        ? {
                                                              ...r,
                                                              code: e.target
                                                                  .value,
                                                          }
                                                        : r,
                                                ),
                                            )
                                        }
                                    >
                                        <option value="">
                                            Choose currency
                                        </option>
                                        {p.currencies
                                            .filter(
                                                (c) =>
                                                    c.code !== p.baseCurrency,
                                            )
                                            .map((c) => (
                                                <option
                                                    key={c.code}
                                                    value={c.code}
                                                >
                                                    {c.code} — {c.name}
                                                </option>
                                            ))}
                                    </select>
                                </FormField>
                                <FormField
                                    id={`rate-${i}`}
                                    label={`Exchange rate per 1 ${p.baseCurrency}`}
                                    error={
                                        p.errors[`display_currencies.${i}.rate`]
                                    }
                                >
                                    <Input
                                        id={`rate-${i}`}
                                        inputMode="decimal"
                                        value={row.rate}
                                        onChange={(e) =>
                                            p.setRates(
                                                p.rates.map((r, n) =>
                                                    n === i
                                                        ? {
                                                              ...r,
                                                              rate: e.target
                                                                  .value,
                                                          }
                                                        : r,
                                                ),
                                            )
                                        }
                                    />
                                </FormField>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    className="self-end"
                                    onClick={() =>
                                        p.setRates(
                                            p.rates.filter((_, n) => n !== i),
                                        )
                                    }
                                >
                                    Remove
                                </Button>
                            </div>
                        ))}
                        {p.errors.display_currencies && (
                            <p className="text-sm text-destructive">
                                {p.errors.display_currencies}
                            </p>
                        )}
                        <Button
                            type="button"
                            variant="outline"
                            className="w-fit"
                            disabled={p.rates.length >= p.currencies.length - 1}
                            onClick={() =>
                                p.setRates([...p.rates, { code: '', rate: '' }])
                            }
                        >
                            Add display currency
                        </Button>
                    </CardContent>
                </Card>
            )}
        </>
    );
}
