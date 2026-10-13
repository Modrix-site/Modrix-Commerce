import { Form, Head, Link } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import VendorController from '@/actions/App/Http/Controllers/Marketplace/VendorController';
import { FormField, NativeSelect } from '@/components/admin/form-field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { index } from '@/routes/vendor';

type Product = {
    slug: string;
    name: string;
    extension_name: string;
    type: 'module' | 'theme';
    summary: string | null;
    description: string | null;
    documentation_url: string | null;
    support_email: string | null;
    support_url: string | null;
    price: string;
    status: string;
    updates_months: number;
    activation_limit: number | null;
};

type Props = {
    vendor: string;
    product: Product | null;
    releases: {
        id: number;
        version: string;
        status: string;
        review_notes: string | null;
        created_at: string | null;
        scan: Record<'critical' | 'high' | 'medium' | 'info', number> | null;
    }[];
};

export default function VendorProduct({ vendor, product, releases }: Props) {
    const form = product
        ? VendorController.update.form(product.slug)
        : VendorController.store.form();

    return (
        <>
            <Head title={product ? product.name : 'New product'} />
            <div className="grid gap-8">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {product ? product.name : 'New product'}
                    </h1>
                    <Button variant="outline" asChild>
                        <Link href={index()}>Back to your dashboard</Link>
                    </Button>
                </div>

                <Form
                    {...form}
                    options={{ preserveScroll: true }}
                    className="grid gap-4 sm:grid-cols-2"
                >
                    {({ processing, errors }) => (
                        <>
                            <FormField
                                id="name"
                                label="Name"
                                error={errors.name}
                            >
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={product?.name}
                                    required
                                />
                            </FormField>
                            <FormField
                                id="slug"
                                label="Slug"
                                error={errors.slug}
                                hint="Used in the product URL."
                            >
                                <Input
                                    id="slug"
                                    name="slug"
                                    defaultValue={product?.slug}
                                    required
                                />
                            </FormField>
                            <FormField
                                id="extension_name"
                                label="Package name"
                                error={errors.extension_name}
                                hint={`Must start with ${vendor}/ and match the manifest.`}
                            >
                                <Input
                                    id="extension_name"
                                    name="extension_name"
                                    defaultValue={
                                        product?.extension_name ?? `${vendor}/`
                                    }
                                    className="font-mono"
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
                                    defaultValue={product?.type ?? 'module'}
                                >
                                    <option value="module">Module</option>
                                    <option value="theme">Theme</option>
                                </NativeSelect>
                            </FormField>
                            <div className="sm:col-span-2">
                                <FormField
                                    id="summary"
                                    label="Summary"
                                    error={errors.summary}
                                >
                                    <Input
                                        id="summary"
                                        name="summary"
                                        defaultValue={product?.summary ?? ''}
                                    />
                                </FormField>
                            </div>
                            <div className="sm:col-span-2">
                                <FormField
                                    id="description"
                                    label="Description"
                                    error={errors.description}
                                >
                                    <Textarea
                                        id="description"
                                        name="description"
                                        rows={6}
                                        defaultValue={
                                            product?.description ?? ''
                                        }
                                    />
                                </FormField>
                            </div>
                            <FormField
                                id="documentation_url"
                                label="Documentation URL"
                                error={errors.documentation_url}
                            >
                                <Input
                                    id="documentation_url"
                                    name="documentation_url"
                                    type="url"
                                    defaultValue={
                                        product?.documentation_url ?? ''
                                    }
                                />
                            </FormField>
                            <FormField
                                id="support_url"
                                label="Support URL"
                                error={errors.support_url}
                            >
                                <Input
                                    id="support_url"
                                    name="support_url"
                                    type="url"
                                    defaultValue={product?.support_url ?? ''}
                                />
                            </FormField>
                            <FormField
                                id="support_email"
                                label="Support email"
                                error={errors.support_email}
                            >
                                <Input
                                    id="support_email"
                                    name="support_email"
                                    type="email"
                                    defaultValue={product?.support_email ?? ''}
                                />
                            </FormField>
                            <FormField
                                id="price"
                                label="Price"
                                error={errors.price}
                                hint="0 for a free product."
                            >
                                <Input
                                    id="price"
                                    name="price"
                                    inputMode="decimal"
                                    defaultValue={product?.price ?? '0'}
                                    required
                                />
                            </FormField>
                            <FormField
                                id="updates_months"
                                label="Months of updates"
                                error={errors.updates_months}
                            >
                                <Input
                                    id="updates_months"
                                    name="updates_months"
                                    type="number"
                                    min={0}
                                    max={120}
                                    defaultValue={product?.updates_months ?? 12}
                                    required
                                />
                            </FormField>
                            <FormField
                                id="activation_limit"
                                label="Stores per license"
                                error={errors.activation_limit}
                                hint="Empty for unlimited."
                            >
                                <Input
                                    id="activation_limit"
                                    name="activation_limit"
                                    type="number"
                                    min={1}
                                    defaultValue={
                                        product?.activation_limit ?? ''
                                    }
                                />
                            </FormField>
                            {product && (
                                <FormField
                                    id="status"
                                    label="Status"
                                    error={errors.status}
                                    hint="The marketplace team lists the product after reviewing a release."
                                >
                                    <NativeSelect
                                        id="status"
                                        name="status"
                                        defaultValue={product.status}
                                    >
                                        {product.status === 'published' && (
                                            <option value="published">
                                                Published
                                            </option>
                                        )}
                                        <option value="draft">Draft</option>
                                        <option value="retired">Retired</option>
                                    </NativeSelect>
                                </FormField>
                            )}
                            <div className="sm:col-span-2">
                                <Button type="submit" disabled={processing}>
                                    {product
                                        ? 'Save product'
                                        : 'Create product'}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>

                {product && (
                    <section className="grid gap-4">
                        <h2 className="text-lg font-semibold">Releases</h2>
                        <Form
                            {...VendorController.upload.form(product.slug)}
                            options={{ preserveScroll: true }}
                            resetOnSuccess
                            className="grid gap-3 rounded-[var(--radius)] border border-dashed p-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <FormField
                                        id="package"
                                        label="Package (.zip)"
                                        error={errors.package}
                                        hint="Build it with php artisan ecomodular:package. It is validated, scanned and then reviewed."
                                    >
                                        <Input
                                            id="package"
                                            name="package"
                                            type="file"
                                            accept=".zip,application/zip"
                                            required
                                        />
                                    </FormField>
                                    <FormField
                                        id="changelog"
                                        label="Changelog"
                                        error={errors.changelog}
                                    >
                                        <Textarea
                                            id="changelog"
                                            name="changelog"
                                            rows={3}
                                        />
                                    </FormField>
                                    <div>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            <Upload /> Submit for review
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                        <ul className="grid gap-2">
                            {releases.map((r) => (
                                <li
                                    key={r.id}
                                    className="grid gap-1 rounded-[var(--radius)] border p-3 text-sm"
                                >
                                    <span className="flex flex-wrap items-center gap-2">
                                        <span className="font-medium">
                                            v{r.version}
                                        </span>
                                        <Badge variant="outline">
                                            {r.status.replace('_', ' ')}
                                        </Badge>
                                        <span className="text-xs text-muted-foreground">
                                            {r.created_at}
                                        </span>
                                    </span>
                                    {r.scan && (
                                        <span className="text-xs text-muted-foreground">
                                            Code scan: {r.scan.critical}{' '}
                                            critical, {r.scan.high} high,{' '}
                                            {r.scan.medium} medium
                                        </span>
                                    )}
                                    {r.review_notes && (
                                        <span>Reviewer: {r.review_notes}</span>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </div>
        </>
    );
}
