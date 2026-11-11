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
    require_login: boolean;
    allow_sharing: boolean;
    button_label: string;
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

export default function WishlistSettingsPage({ settings, updateUrl }: Props) {
    const form = useForm({
        enabled: settings.enabled,
        require_login: settings.require_login,
        allow_sharing: settings.allow_sharing,
        button_label: settings.button_label,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        form.put(updateUrl);
    };

    return (
        <>
            <Head title="Wishlist Settings" />

            <AdminPage>
                <PageHeader
                    title="Wishlist Settings"
                    description="Configure guest permissions, sharing links, and button copy for customer wishlists."
                    actions={
                        <Link href="/admin/m/modrixcommerce/wishlist">
                            <Button variant="outline" size="sm">
                                <ArrowLeft className="mr-2 size-4" />
                                Back to Analytics
                            </Button>
                        </Link>
                    }
                />

                <form onSubmit={handleSubmit} className="grid gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Storefront Options</CardTitle>
                            <CardDescription>
                                Manage how customers interact with wishlists on
                                your store.
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
                                    Enable Wishlist functionality across the
                                    store
                                </Label>
                            </div>

                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="require_login"
                                    checked={form.data.require_login}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'require_login',
                                            Boolean(checked),
                                        )
                                    }
                                />
                                <Label
                                    htmlFor="require_login"
                                    className="text-sm font-medium"
                                >
                                    Require customer login before saving items
                                    (disables guest sessions)
                                </Label>
                            </div>

                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="allow_sharing"
                                    checked={form.data.allow_sharing}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'allow_sharing',
                                            Boolean(checked),
                                        )
                                    }
                                />
                                <Label
                                    htmlFor="allow_sharing"
                                    className="text-sm font-medium"
                                >
                                    Allow shoppers to generate public shareable
                                    links for their wishlist
                                </Label>
                            </div>

                            <FormField
                                id="button_label"
                                label="Wishlist Button Label"
                                error={form.errors.button_label}
                                hint="Text displayed on product pages before an item is saved"
                            >
                                <Input
                                    id="button_label"
                                    value={form.data.button_label}
                                    onChange={(e) =>
                                        form.setData(
                                            'button_label',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                            </FormField>
                        </CardContent>
                    </Card>

                    <div className="flex justify-end gap-3">
                        <Link href="/admin/m/modrixcommerce/wishlist">
                            <Button variant="outline" type="button">
                                Cancel
                            </Button>
                        </Link>
                        <Button
                            type="submit"
                            disabled={form.processing}
                            className="bg-rose-600 text-white hover:bg-rose-700"
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

WishlistSettingsPage.layout = {
    breadcrumbs: [
        {
            title: 'Wishlist Analytics',
            href: '/admin/m/modrixcommerce/wishlist',
        },
    ],
};
