import { Form, Head } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import MarketplaceAdminController from '@/actions/App/Http/Controllers/Admin/MarketplaceAdminController';
import { FormField, NativeSelect } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { index } from '@/routes/admin/marketplace/products';

type Product = {
    slug: string;
    name: string;
    extension_name: string;
    type: 'module' | 'theme';
    summary: string | null;
    description: string | null;
    documentation_url: string | null;
    price: string;
    status: string;
    updates_months: number;
    activation_limit: number | null;
};

type Release = {
    id: number;
    version: string;
    platform_version: string;
    status: 'pending_review' | 'published' | 'rejected' | 'withdrawn';
    sha256: string;
    size: number;
    changelog: string | null;
    review_notes: string | null;
    reviewer: string | null;
    created_at: string | null;
    published_at: string | null;
    capabilities: string[];
};

type Props = {
    product: Product | null;
    currency: string;
    releases: Release[];
};

export default function MarketplaceProductForm({
    product,
    currency,
    releases,
}: Props) {
    const form = product
        ? MarketplaceAdminController.update.form(product.slug)
        : MarketplaceAdminController.store.form();

    return (
        <>
            <Head title={product ? product.name : 'New marketplace product'} />
            <AdminPage>
                <PageHeader
                    title={product ? product.name : 'New marketplace product'}
                />

                <Form
                    {...form}
                    className="grid gap-6"
                    options={{ preserveScroll: true }}
                >
                    {({ processing, errors }) => (
                        <Card>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
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
                                    hint="vendor/name, must match the manifest. Fixed once a release exists."
                                >
                                    <Input
                                        id="extension_name"
                                        name="extension_name"
                                        defaultValue={product?.extension_name}
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
                                            defaultValue={
                                                product?.summary ?? ''
                                            }
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
                                    id="price"
                                    label={`Price (${currency})`}
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
                                    hint="0 means updates without an end date."
                                >
                                    <Input
                                        id="updates_months"
                                        name="updates_months"
                                        type="number"
                                        min={0}
                                        max={120}
                                        defaultValue={
                                            product?.updates_months ?? 12
                                        }
                                        required
                                    />
                                </FormField>
                                <FormField
                                    id="activation_limit"
                                    label="Stores per license"
                                    error={errors.activation_limit}
                                    hint="Leave empty for unlimited."
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
                                <FormField
                                    id="status"
                                    label="Status"
                                    error={errors.status}
                                    hint="A product needs a published release before it can be published."
                                >
                                    <NativeSelect
                                        id="status"
                                        name="status"
                                        defaultValue={
                                            product?.status ?? 'draft'
                                        }
                                    >
                                        <option value="draft">Draft</option>
                                        <option value="published">
                                            Published
                                        </option>
                                        <option value="retired">Retired</option>
                                    </NativeSelect>
                                </FormField>
                                <div className="sm:col-span-2">
                                    <Button type="submit" disabled={processing}>
                                        {product
                                            ? 'Save product'
                                            : 'Create product'}
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </Form>

                {product && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Releases</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-6">
                            <Form
                                {...MarketplaceAdminController.upload.form(
                                    product.slug,
                                )}
                                options={{ preserveScroll: true }}
                                resetOnSuccess
                                className="grid gap-3 rounded-md border border-dashed p-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <FormField
                                            id="package"
                                            label="Package (.zip)"
                                            error={errors.package}
                                            hint="The archive is scanned and its manifest validated; the version must be higher than the latest release."
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
                                                <Upload /> Upload for review
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </Form>

                            {releases.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No releases yet.
                                </p>
                            ) : (
                                <ul className="grid gap-4">
                                    {releases.map((r) => (
                                        <ReleaseRow key={r.id} release={r} />
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                )}
            </AdminPage>
        </>
    );
}

function ReleaseRow({ release: r }: { release: Release }) {
    return (
        <li className="grid gap-2 border-b pb-4 last:border-b-0 last:pb-0">
            <div className="flex flex-wrap items-center gap-2">
                <span className="font-medium">v{r.version}</span>
                <Badge
                    variant={
                        r.status === 'published'
                            ? 'default'
                            : r.status === 'pending_review'
                              ? 'secondary'
                              : 'outline'
                    }
                >
                    {r.status.replace('_', ' ')}
                </Badge>
                <span className="text-xs text-muted-foreground">
                    platform {r.platform_version} · {(r.size / 1024).toFixed(1)}{' '}
                    KB
                </span>
                <div className="ml-auto flex gap-2">
                    {r.status === 'pending_review' && (
                        <ReviewDialog release={r} />
                    )}
                    {r.status === 'published' && <WithdrawDialog release={r} />}
                </div>
            </div>
            <code className="truncate text-xs text-muted-foreground">
                SHA-256 {r.sha256}
            </code>
            {r.capabilities.length > 0 && (
                <p className="text-xs">
                    Capabilities: {r.capabilities.join(', ')}
                </p>
            )}
            {r.changelog && (
                <p className="text-sm whitespace-pre-line text-muted-foreground">
                    {r.changelog}
                </p>
            )}
            {r.reviewer && (
                <p className="text-xs text-muted-foreground">
                    Reviewed by {r.reviewer}
                    {r.review_notes && `: ${r.review_notes}`}
                </p>
            )}
        </li>
    );
}

function ReviewDialog({ release }: { release: Release }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button size="sm">Review</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Review v{release.version}</DialogTitle>
                <DialogDescription>
                    Approving signs this release with the marketplace key and
                    offers it to every license holder. The archive is hashed
                    again before signing.
                </DialogDescription>
                <Form
                    {...MarketplaceAdminController.review.form(release.id)}
                    options={{ preserveScroll: true }}
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            {release.capabilities.length > 0 && (
                                <p className="text-sm">
                                    Requested capabilities:{' '}
                                    <strong>
                                        {release.capabilities.join(', ')}
                                    </strong>
                                </p>
                            )}
                            <FormField
                                id="notes"
                                label="Notes"
                                error={errors.notes}
                            >
                                <Textarea id="notes" name="notes" rows={3} />
                            </FormField>
                            <label className="flex items-start gap-2 text-sm">
                                <Checkbox name="reviewed" value="1" />
                                I reviewed the code, its migrations and the
                                requested capabilities.
                            </label>
                            <InputError
                                message={errors.reviewed ?? errors.decision}
                            />
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary" type="button">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    name="decision"
                                    value="reject"
                                    variant="outline"
                                    disabled={processing}
                                >
                                    Reject
                                </Button>
                                <Button
                                    type="submit"
                                    name="decision"
                                    value="approve"
                                    disabled={processing}
                                >
                                    Approve and sign
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function WithdrawDialog({ release }: { release: Release }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    Withdraw
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Withdraw v{release.version}?</DialogTitle>
                <DialogDescription>
                    Stores stop being offered this version. Stores that already
                    installed it keep it.
                </DialogDescription>
                <Form
                    {...MarketplaceAdminController.withdraw.form(release.id)}
                    options={{ preserveScroll: true }}
                >
                    {({ processing }) => (
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button variant="secondary" type="button">
                                    Cancel
                                </Button>
                            </DialogClose>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={processing}
                            >
                                Withdraw
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

MarketplaceProductForm.layout = {
    breadcrumbs: [{ title: 'Marketplace products', href: index() }],
};
