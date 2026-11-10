import { Form, Head } from '@inertiajs/react';
import VendorController from '@/actions/App/Http/Controllers/Marketplace/VendorController';
import { FormField, NativeSelect } from '@/components/admin/form-field';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

type Props = {
    application: {
        status: 'pending' | 'approved' | 'rejected';
        publisher_name: string;
        review_notes: string | null;
        created_at: string | null;
    } | null;
    countries: { code: string; name: string }[];
};

export default function VendorApply({ application, countries }: Props) {
    const canApply = application === null || application.status === 'rejected';

    return (
        <>
            <Head title="Sell on the marketplace" />
            <div className="mx-auto grid max-w-2xl gap-6">
                <div className="grid gap-2">
                    <h1 className="text-3xl font-semibold tracking-tight">
                        Sell on the marketplace
                    </h1>
                    <p className="text-muted-foreground">
                        Publish modules and themes for Modrix Commerce stores. Every
                        seller is verified, every release is reviewed and signed
                        before it is offered, and you are paid your share of
                        each sale.
                    </p>
                </div>

                {application && (
                    <div className="grid gap-1 rounded-[var(--radius)] border bg-card p-4">
                        <span className="flex items-center gap-2 font-medium">
                            Your application for {application.publisher_name}
                            <Badge
                                variant={
                                    application.status === 'rejected'
                                        ? 'destructive'
                                        : 'outline'
                                }
                            >
                                {application.status}
                            </Badge>
                        </span>
                        <span className="text-sm text-muted-foreground">
                            Sent {application.created_at}
                            {application.status === 'pending' &&
                                '. We usually answer within a few working days.'}
                        </span>
                        {application.review_notes && (
                            <p className="text-sm">
                                {application.review_notes}
                            </p>
                        )}
                    </div>
                )}

                {canApply && (
                    <Form
                        {...VendorController.apply.form()}
                        className="grid gap-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <FormField
                                        id="publisher_name"
                                        label="Seller name (shown to buyers)"
                                        error={errors.publisher_name}
                                    >
                                        <Input
                                            id="publisher_name"
                                            name="publisher_name"
                                            required
                                        />
                                    </FormField>
                                    <FormField
                                        id="vendor_slug"
                                        label="Vendor name for packages"
                                        error={errors.vendor_slug}
                                        hint="Lowercase, e.g. acme: your packages are named acme/your-package."
                                    >
                                        <Input
                                            id="vendor_slug"
                                            name="vendor_slug"
                                            className="font-mono"
                                            required
                                        />
                                    </FormField>
                                    <FormField
                                        id="legal_name"
                                        label="Legal name"
                                        error={errors.legal_name}
                                    >
                                        <Input
                                            id="legal_name"
                                            name="legal_name"
                                            required
                                        />
                                    </FormField>
                                    <FormField
                                        id="country"
                                        label="Country"
                                        error={errors.country}
                                    >
                                        <NativeSelect
                                            id="country"
                                            name="country"
                                            defaultValue=""
                                            required
                                        >
                                            <option value="" disabled>
                                                Choose…
                                            </option>
                                            {countries.map((c) => (
                                                <option
                                                    key={c.code}
                                                    value={c.code}
                                                >
                                                    {c.name}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                    </FormField>
                                    <FormField
                                        id="website"
                                        label="Website"
                                        error={errors.website}
                                    >
                                        <Input
                                            id="website"
                                            name="website"
                                            type="url"
                                            placeholder="https://"
                                        />
                                    </FormField>
                                    <FormField
                                        id="payout_email"
                                        label="Payout email"
                                        error={errors.payout_email}
                                    >
                                        <Input
                                            id="payout_email"
                                            name="payout_email"
                                            type="email"
                                            required
                                        />
                                    </FormField>
                                    <FormField
                                        id="support_email"
                                        label="Support email for buyers"
                                        error={errors.support_email}
                                    >
                                        <Input
                                            id="support_email"
                                            name="support_email"
                                            type="email"
                                            required
                                        />
                                    </FormField>
                                </div>
                                <FormField
                                    id="description"
                                    label="What will you sell?"
                                    error={errors.description}
                                    hint="Your experience, the extensions you plan, and links to earlier work."
                                >
                                    <Textarea
                                        id="description"
                                        name="description"
                                        rows={5}
                                        required
                                    />
                                </FormField>
                                <label className="flex items-start gap-2 text-sm">
                                    <Checkbox name="terms" value="1" />
                                    I agree that my releases are reviewed before
                                    publication, that I support my buyers, and
                                    that the platform keeps the agreed
                                    commission on each sale.
                                </label>
                                <InputError message={errors.terms} />
                                <div>
                                    <Button type="submit" disabled={processing}>
                                        Send application
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                )}
            </div>
        </>
    );
}
