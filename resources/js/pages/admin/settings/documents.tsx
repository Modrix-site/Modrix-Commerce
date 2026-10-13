import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import DocumentSettingsController from '@/actions/App/Http/Controllers/Admin/DocumentSettingsController';
import { FormField, NativeSelect } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { edit } from '@/routes/admin/settings/documents';

type Props = {
    settings: {
        invoice_prefix: string;
        invoice_padding: number;
        next_invoice_number: number;
        tax_id: string | null;
        seller_address: string | null;
        footer: string | null;
        logo_media_id: number | null;
    };
    preview: string;
    logos: { id: number; filename: string; url: string }[];
};

function formatNumber(prefix: string, padding: number, next: number): string {
    const year = String(new Date().getFullYear());

    return (
        prefix.replaceAll('{year}', year) +
        String(Math.max(1, next || 1)).padStart(
            Math.min(8, Math.max(3, padding || 3)),
            '0',
        )
    );
}

export default function DocumentSettings({ settings, preview, logos }: Props) {
    const [prefix, setPrefix] = useState(settings.invoice_prefix);
    const [padding, setPadding] = useState(settings.invoice_padding);
    const [next, setNext] = useState(settings.next_invoice_number);
    const [logo, setLogo] = useState<string>(
        settings.logo_media_id ? String(settings.logo_media_id) : '',
    );
    const selectedLogo = logos.find((l) => String(l.id) === logo);
    const changed =
        prefix !== settings.invoice_prefix ||
        padding !== settings.invoice_padding ||
        next !== settings.next_invoice_number;

    return (
        <>
            <Head title="Invoices & documents" />
            <AdminPage>
                <PageHeader
                    title="Invoices & documents"
                    description="Numbering and the business details printed on invoices, payment receipts and packing slips. Issued invoices never change: new settings apply to the next invoice."
                />

                <Form
                    {...DocumentSettingsController.update.form()}
                    options={{ preserveScroll: true }}
                    className="grid gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <Card>
                                <CardHeader>
                                    <CardTitle>Invoice numbering</CardTitle>
                                    <CardDescription>
                                        Every paid order gets the next number
                                        automatically, with no gaps.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-4 sm:grid-cols-3">
                                    <FormField
                                        id="invoice_prefix"
                                        label="Prefix"
                                        error={errors.invoice_prefix}
                                        hint="{year} becomes the current year."
                                    >
                                        <Input
                                            id="invoice_prefix"
                                            name="invoice_prefix"
                                            value={prefix}
                                            onChange={(e) =>
                                                setPrefix(e.target.value)
                                            }
                                            maxLength={20}
                                            required
                                        />
                                    </FormField>
                                    <FormField
                                        id="invoice_padding"
                                        label="Digits"
                                        error={errors.invoice_padding}
                                    >
                                        <NativeSelect
                                            id="invoice_padding"
                                            name="invoice_padding"
                                            value={padding}
                                            onChange={(e) =>
                                                setPadding(
                                                    Number(e.target.value),
                                                )
                                            }
                                        >
                                            {[3, 4, 5, 6, 7, 8].map((n) => (
                                                <option key={n} value={n}>
                                                    {n}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                    </FormField>
                                    <FormField
                                        id="next_invoice_number"
                                        label="Next number"
                                        error={errors.next_invoice_number}
                                        hint={`Can only go up (currently ${settings.next_invoice_number}), so an existing series can continue.`}
                                    >
                                        <Input
                                            id="next_invoice_number"
                                            name="next_invoice_number"
                                            type="number"
                                            min={settings.next_invoice_number}
                                            value={next}
                                            onChange={(e) =>
                                                setNext(Number(e.target.value))
                                            }
                                            required
                                        />
                                    </FormField>
                                    <p
                                        className="text-sm text-muted-foreground sm:col-span-3"
                                        aria-live="polite"
                                    >
                                        Next invoice:{' '}
                                        <span className="font-mono font-medium text-foreground">
                                            {changed
                                                ? formatNumber(
                                                      prefix,
                                                      padding,
                                                      next,
                                                  )
                                                : preview}
                                        </span>
                                    </p>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Business details</CardTitle>
                                    <CardDescription>
                                        Shown as the seller on every document,
                                        with the store name and email from Store
                                        settings.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-4 sm:grid-cols-2">
                                    <FormField
                                        id="seller_address"
                                        label="Business address"
                                        error={errors.seller_address}
                                    >
                                        <Textarea
                                            id="seller_address"
                                            name="seller_address"
                                            rows={4}
                                            maxLength={500}
                                            defaultValue={
                                                settings.seller_address ?? ''
                                            }
                                        />
                                    </FormField>
                                    <div className="grid content-start gap-4">
                                        <FormField
                                            id="tax_id"
                                            label="Tax / VAT number"
                                            error={errors.tax_id}
                                        >
                                            <Input
                                                id="tax_id"
                                                name="tax_id"
                                                maxLength={60}
                                                defaultValue={
                                                    settings.tax_id ?? ''
                                                }
                                            />
                                        </FormField>
                                        <FormField
                                            id="logo_media_id"
                                            label="Logo"
                                            error={errors.logo_media_id}
                                            hint="PNG, JPEG or GIF from the media library."
                                        >
                                            <NativeSelect
                                                id="logo_media_id"
                                                name="logo_media_id"
                                                value={logo}
                                                onChange={(e) =>
                                                    setLogo(e.target.value)
                                                }
                                            >
                                                <option value="">
                                                    No logo (store name)
                                                </option>
                                                {logos.map((l) => (
                                                    <option
                                                        key={l.id}
                                                        value={l.id}
                                                    >
                                                        {l.filename}
                                                    </option>
                                                ))}
                                            </NativeSelect>
                                        </FormField>
                                        {selectedLogo && (
                                            <img
                                                src={selectedLogo.url}
                                                alt=""
                                                className="h-12 w-auto self-start rounded border bg-white object-contain p-1"
                                            />
                                        )}
                                    </div>
                                    <div className="sm:col-span-2">
                                        <FormField
                                            id="footer"
                                            label="Footer text"
                                            error={errors.footer}
                                            hint="Payment terms, registration details or a thank-you note."
                                        >
                                            <Textarea
                                                id="footer"
                                                name="footer"
                                                rows={3}
                                                maxLength={1000}
                                                defaultValue={
                                                    settings.footer ?? ''
                                                }
                                            />
                                        </FormField>
                                    </div>
                                </CardContent>
                            </Card>

                            <div>
                                <Button type="submit" disabled={processing}>
                                    Save
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </AdminPage>
        </>
    );
}

DocumentSettings.layout = {
    breadcrumbs: [{ title: 'Invoices & documents', href: edit() }],
};
