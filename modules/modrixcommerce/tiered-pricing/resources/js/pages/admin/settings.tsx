import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import { FormEvent } from 'react';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
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

type SettingsData = {
    enabled: boolean;
    table_heading: string;
    table_subheading: string;
    show_savings_badge: boolean;
    highlight_active_tier: boolean;
};

type Props = {
    settings: SettingsData;
    updateUrl: string;
};

function FormField({
    id,
    label,
    error,
    hint,
    children,
}: {
    id: string;
    label: string;
    error?: string;
    hint?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id} className="text-sm font-medium">
                {label}
            </Label>
            {children}
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
            {error && <p className="text-xs text-destructive">{error}</p>}
        </div>
    );
}

export default function TieredPricingSettingsPage({
    settings,
    updateUrl,
}: Props) {
    const form = useForm({
        enabled: settings.enabled,
        table_heading: settings.table_heading,
        table_subheading: settings.table_subheading,
        show_savings_badge: settings.show_savings_badge,
        highlight_active_tier: settings.highlight_active_tier,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        form.put(updateUrl);
    };

    return (
        <>
            <Head title="Tiered Pricing Settings" />

            <AdminPage>
                <PageHeader
                    title="Tiered Pricing Settings"
                    description="Configure storefront display options, headings, and active tier highlighting."
                    actions={
                        <Link href="/admin/m/modrixcommerce/tiered-pricing">
                            <Button variant="outline" size="sm">
                                <ArrowLeft className="mr-2 size-4" />
                                Back to Rules
                            </Button>
                        </Link>
                    }
                />

                <form onSubmit={handleSubmit} className="grid gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>General Display Settings</CardTitle>
                            <CardDescription>
                                Manage how quantity-break tables appear on your
                                product pages.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="enabled"
                                    checked={form.data.enabled}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'enabled',
                                            Boolean(checked),
                                        )
                                    }
                                />
                                <Label
                                    htmlFor="enabled"
                                    className="text-sm font-medium"
                                >
                                    Enable Tiered Pricing on the storefront
                                </Label>
                            </div>

                            <FormField
                                id="table_heading"
                                label="Table Heading"
                                error={form.errors.table_heading}
                                hint="Appears above the tier grid on single product pages"
                            >
                                <Input
                                    id="table_heading"
                                    value={form.data.table_heading}
                                    onChange={(e) =>
                                        form.setData(
                                            'table_heading',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                            </FormField>

                            <FormField
                                id="table_subheading"
                                label="Table Subheading"
                                error={form.errors.table_subheading}
                                hint="Optional explanatory text"
                            >
                                <Input
                                    id="table_subheading"
                                    value={form.data.table_subheading}
                                    onChange={(e) =>
                                        form.setData(
                                            'table_subheading',
                                            e.target.value,
                                        )
                                    }
                                />
                            </FormField>

                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="show_savings_badge"
                                    checked={form.data.show_savings_badge}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'show_savings_badge',
                                            Boolean(checked),
                                        )
                                    }
                                />
                                <Label
                                    htmlFor="show_savings_badge"
                                    className="text-sm font-medium"
                                >
                                    Display savings percentage badges on each
                                    tier box
                                </Label>
                            </div>

                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="highlight_active_tier"
                                    checked={form.data.highlight_active_tier}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'highlight_active_tier',
                                            Boolean(checked),
                                        )
                                    }
                                />
                                <Label
                                    htmlFor="highlight_active_tier"
                                    className="text-sm font-medium"
                                >
                                    Dynamically highlight the tier matching the
                                    shopper's quantity input
                                </Label>
                            </div>
                        </CardContent>
                    </Card>

                    <div className="flex justify-end gap-3">
                        <Link href="/admin/m/modrixcommerce/tiered-pricing">
                            <Button variant="outline" type="button">
                                Cancel
                            </Button>
                        </Link>
                        <Button
                            type="submit"
                            disabled={form.processing}
                            className="bg-blue-600 text-white hover:bg-blue-700"
                        >
                            <Save className="mr-2 size-4" />
                            Save Settings
                        </Button>
                    </div>
                </form>
            </AdminPage>
        </>
    );
}

TieredPricingSettingsPage.layout = {
    breadcrumbs: [
        {
            title: 'Tiered Pricing',
            href: '/admin/m/modrixcommerce/tiered-pricing',
        },
    ],
};
