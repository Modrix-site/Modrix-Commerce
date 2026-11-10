import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import { FormField } from '@/components/admin/form-field';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type SettingsData = {
    enabled: boolean;
    heading: string;
    subheading: string;
    default_discount_type: 'percent' | 'fixed';
    default_discount_value: number;
    auto_bundle_fallback: boolean;
    cta_button_text: string;
    show_savings_badge: boolean;
};

type Props = {
    settings: SettingsData;
    updateUrl: string;
};

export default function BuyTogetherSettingsPage({ settings, updateUrl }: Props) {
    const form = useForm({
        _method: 'PUT',
        enabled: settings.enabled,
        heading: settings.heading,
        subheading: settings.subheading,
        default_discount_type: settings.default_discount_type,
        default_discount_value: settings.default_discount_value,
        auto_bundle_fallback: settings.auto_bundle_fallback,
        cta_button_text: settings.cta_button_text,
        show_savings_badge: settings.show_savings_badge,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.put(updateUrl);
    };

    return (
        <>
            <Head title="Buy Together & Save Settings" />

            <AdminPage>
                <PageHeader
                    title="Buy Together & Save Settings"
                    description="Configure global display options, fallback rules, and default discount values for product bundles."
                    actions={
                        <Link href="/admin/m/modrixcommerce/buy-together-save">
                            <Button variant="outline" size="sm">
                                <ArrowLeft className="mr-2 size-4" />
                                Back to Deals
                            </Button>
                        </Link>
                    }
                />

                <form onSubmit={handleSubmit} className="grid gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Display & Copy Customization</CardTitle>
                            <CardDescription>
                                Customize how the "Buy Together & Save" section appears on single product pages.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-2">
                            <FormField
                                id="heading"
                                label="Widget Heading"
                                error={form.errors.heading}
                                hint="Primary title displayed above the bundle offer."
                            >
                                <Input
                                    id="heading"
                                    value={form.data.heading}
                                    onChange={(e) => form.setData('heading', e.target.value)}
                                    placeholder="Buy Together & Save"
                                    required
                                />
                            </FormField>

                            <FormField
                                id="cta_button_text"
                                label="Add-to-Cart Button Text"
                                error={form.errors.cta_button_text}
                                hint="Text displayed on the primary action button."
                            >
                                <Input
                                    id="cta_button_text"
                                    value={form.data.cta_button_text}
                                    onChange={(e) => form.setData('cta_button_text', e.target.value)}
                                    placeholder="Add Bundle to Cart & Save"
                                    required
                                />
                            </FormField>

                            <FormField
                                id="subheading"
                                label="Subheading / Subtitle"
                                error={form.errors.subheading}
                                hint="Optional explanatory subtitle."
                            >
                                <Input
                                    id="subheading"
                                    value={form.data.subheading}
                                    onChange={(e) => form.setData('subheading', e.target.value)}
                                    placeholder="Purchase these items together and get exclusive bundle savings"
                                />
                            </FormField>

                            <div className="flex flex-col justify-end space-y-4">
                                <div className="flex items-center space-x-2">
                                    <Checkbox
                                        id="show_savings_badge"
                                        checked={form.data.show_savings_badge}
                                        onCheckedChange={(checked) =>
                                            form.setData('show_savings_badge', Boolean(checked))
                                        }
                                    />
                                    <label htmlFor="show_savings_badge" className="text-sm font-medium leading-none">
                                        Show highlight savings badge & strikethrough price
                                    </label>
                                </div>

                                <div className="flex items-center space-x-2">
                                    <Checkbox
                                        id="enabled"
                                        checked={form.data.enabled}
                                        onCheckedChange={(checked) => form.setData('enabled', Boolean(checked))}
                                    />
                                    <label htmlFor="enabled" className="text-sm font-medium leading-none">
                                        Enable Buy Together & Save globally on storefront
                                    </label>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Automatic Fallback Rules</CardTitle>
                            <CardDescription>
                                Automatically bundle related products when no custom curated deal is assigned to a product.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-2">
                            <div className="md:col-span-2">
                                <div className="flex items-center space-x-2">
                                    <Checkbox
                                        id="auto_bundle_fallback"
                                        checked={form.data.auto_bundle_fallback}
                                        onCheckedChange={(checked) =>
                                            form.setData('auto_bundle_fallback', Boolean(checked))
                                        }
                                    />
                                    <label htmlFor="auto_bundle_fallback" className="text-sm font-medium leading-none">
                                        Automatically create bundles from same category when no manual deal exists
                                    </label>
                                </div>
                            </div>

                            <FormField
                                id="default_discount_type"
                                label="Default Fallback Discount Type"
                                error={form.errors.default_discount_type}
                            >
                                <Select
                                    value={form.data.default_discount_type}
                                    onValueChange={(val: string) =>
                                        form.setData('default_discount_type', val as 'percent' | 'fixed')
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Discount Type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="percent">Percentage Off (%)</SelectItem>
                                        <SelectItem value="fixed">Fixed Amount Off ($ / Currency)</SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormField>

                            <FormField
                                id="default_discount_value"
                                label="Default Fallback Discount Value"
                                error={form.errors.default_discount_value}
                                hint={form.data.default_discount_type === 'percent' ? 'e.g. 10 for 10%' : 'e.g. 15.00'}
                            >
                                <Input
                                    id="default_discount_value"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    value={form.data.default_discount_value}
                                    onChange={(e) =>
                                        form.setData('default_discount_value', parseFloat(e.target.value))
                                    }
                                    required
                                />
                            </FormField>
                        </CardContent>
                    </Card>

                    <div className="flex justify-end gap-3">
                        <Link href="/admin/m/modrixcommerce/buy-together-save">
                            <Button variant="outline" type="button">
                                Cancel
                            </Button>
                        </Link>
                        <Button
                            type="submit"
                            disabled={form.processing}
                            className="bg-emerald-600 hover:bg-emerald-700 text-white"
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
