import { Form, Head, Link } from '@inertiajs/react';
import { AlertTriangle, Check, Eye, Palette } from 'lucide-react';
import ThemeController from '@/actions/App/Http/Controllers/Admin/ThemeController';
import { EmptyState } from '@/components/admin/empty-state';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { PackageUpload } from '@/components/admin/package-upload';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import { customize, editor, index } from '@/routes/admin/themes';
import type { ThemeSummary } from '@/types';

const FONT_SAMPLE: Record<string, string> = {
    serif: 'ui-serif, Georgia, serif',
    didone: "Didot, 'Bodoni MT', serif",
    mono: 'ui-monospace, monospace',
    rounded: 'ui-rounded, sans-serif',
};

export default function ThemesIndex({
    themes,
    previewing,
    licensedThemes = [],
}: {
    themes: ThemeSummary[];
    previewing: string | null;
    defaultTheme: string;
    licensedThemes?: string[];
}) {
    return (
        <>
            <Head title="Appearance" />
            <AdminPage>
                <PageHeader
                    title="Appearance"
                    actions={<PackageUpload type="theme" />}
                    description="Choose how your store looks. Themes only change presentation; prices, checkout and payments always work the same. Your changes to each theme are kept when you switch or update."
                />

                {previewing && (
                    <Alert>
                        <Eye />
                        <AlertTitle>You are previewing {previewing}</AlertTitle>
                        <AlertDescription className="flex flex-wrap items-center gap-3">
                            Only you see it on the storefront.
                            <Form {...ThemeController.exitPreview.form()}>
                                <Button size="sm" variant="outline">
                                    Exit preview
                                </Button>
                            </Form>
                        </AlertDescription>
                    </Alert>
                )}

                {themes.length === 0 ? (
                    <EmptyState
                        title="No themes found"
                        description="Add a theme to themes/<vendor>/<name>/ with a theme.json manifest, then reload this page."
                    />
                ) : (
                    <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        {themes.map((theme) => (
                            <li key={theme.id}>
                                <ThemeCard
                                    theme={theme}
                                    licensed={licensedThemes.includes(
                                        theme.name,
                                    )}
                                />
                            </li>
                        ))}
                    </ul>
                )}
            </AdminPage>
        </>
    );
}

function ThemeCard({
    theme,
    licensed,
}: {
    theme: ThemeSummary;
    licensed: boolean;
}) {
    const usable = theme.state === 'active' || theme.state === 'available';

    return (
        <Card
            id={`theme-${theme.name.replaceAll('/', '-')}`}
            className="h-full scroll-mt-6 gap-0 overflow-hidden py-0 target:ring-2 target:ring-primary"
        >
            <ThemeThumbnail theme={theme} />
            <CardContent className="grid gap-3 py-4">
                <div className="flex flex-wrap items-center gap-2">
                    <span className="font-medium">{theme.display_name}</span>
                    {licensed && (
                        <Badge variant="outline">Modrix Cloud licensed</Badge>
                    )}
                    {theme.state === 'active' && (
                        <Badge className="status-badge" data-tone="success">
                            <Check /> Active
                        </Badge>
                    )}
                    {theme.state === 'disabled' && (
                        <Badge variant="secondary">Disabled</Badge>
                    )}
                    {theme.state === 'invalid' && (
                        <Badge variant="destructive">Invalid</Badge>
                    )}
                    {theme.state === 'failed' && (
                        <Badge variant="destructive">Files missing</Badge>
                    )}
                    {theme.update_available && (
                        <Badge variant="outline">
                            Update to v{theme.available_version}
                        </Badge>
                    )}
                    {theme.customized && (
                        <Badge variant="secondary">Customized</Badge>
                    )}
                </div>
                <p className="font-mono text-xs text-muted-foreground">
                    {theme.name} · v{theme.version ?? '?'}
                    {theme.author && ` · ${theme.author}`}
                </p>
                {theme.description && (
                    <p className="text-sm text-muted-foreground">
                        {theme.description}
                    </p>
                )}
                {(theme.errors.length > 0 || theme.failure) && (
                    <Alert variant="destructive">
                        <AlertTriangle />
                        <AlertTitle>
                            {theme.state === 'active'
                                ? 'New files on disk cannot be used'
                                : 'This theme cannot be used'}
                        </AlertTitle>
                        <AlertDescription>
                            <ul className="list-disc pl-4">
                                {theme.failure && <li>{theme.failure}</li>}
                                {theme.errors.slice(0, 5).map((e, i) => (
                                    <li key={i}>{e}</li>
                                ))}
                            </ul>
                        </AlertDescription>
                    </Alert>
                )}
                <div className="flex flex-wrap gap-2">
                    {(theme.state === 'available' ||
                        theme.state === 'disabled') && (
                        <ActivateDialog theme={theme} />
                    )}
                    {usable && (
                        <Form
                            action={`/admin/themes/${theme.id}/disable`}
                            method="post"
                        >
                            <Button size="sm" variant="outline">
                                Disable
                            </Button>
                        </Form>
                    )}
                    {theme.state !== 'active' && (
                        <DeleteThemeDialog theme={theme} />
                    )}
                    {usable && (
                        <Form {...ThemeController.preview.form(theme.id)}>
                            <Button size="sm" variant="outline">
                                <Eye /> Preview
                            </Button>
                        </Form>
                    )}
                    {usable && (
                        <Button size="sm" variant="outline" asChild>
                            <Link
                                href={
                                    theme.has_sections
                                        ? editor(theme.id)
                                        : customize(theme.id)
                                }
                            >
                                <Palette /> Customize
                            </Link>
                        </Button>
                    )}
                    {theme.update_available && <UpdateDialog theme={theme} />}
                    {usable && theme.has_demo && <DemoDialog theme={theme} />}
                </div>
            </CardContent>
        </Card>
    );
}

function ThemeThumbnail({ theme }: { theme: ThemeSummary }) {
    if (theme.screenshot) {
        return (
            <img
                src={theme.screenshot}
                alt={`${theme.display_name} demo preview`}
                className="aspect-[16/10] w-full border-b object-cover object-top"
            />
        );
    }

    const [background, primary, accent, foreground] = theme.swatches;
    const heading = theme.fonts?.[0] ?? 'sans';

    return (
        <div
            className="flex aspect-[16/9] items-end gap-3 border-b p-5"
            style={{ background, color: foreground }}
            aria-hidden
        >
            <span
                className="text-4xl leading-none font-semibold"
                style={{ fontFamily: FONT_SAMPLE[heading] }}
            >
                Aa
            </span>
            <span className="ml-auto flex gap-1.5">
                {[primary, accent, foreground].map((color, i) => (
                    <span
                        key={i}
                        className="size-6 rounded-full border border-black/10"
                        style={{ background: color }}
                    />
                ))}
            </span>
        </div>
    );
}

function ActivateDialog({ theme }: { theme: ThemeSummary }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button size="sm">
                    {theme.state === 'disabled' ? 'Enable' : 'Activate'}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>
                    {theme.state === 'disabled' ? 'Enable' : 'Activate'}{' '}
                    {theme.display_name}?
                </DialogTitle>
                <DialogDescription>
                    Shoppers will see this theme immediately. Your products,
                    orders and settings are not changed, and your customizations
                    of every theme are kept, so you can switch back at any time.
                    Preview first if you have not yet.
                </DialogDescription>
                <Form
                    action={`/admin/themes/${theme.id}/${theme.state === 'disabled' ? 'enable' : 'activate'}`}
                    method="post"
                >
                    {({ processing, errors }) => (
                        <>
                            <InputError message={errors.theme} />
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {theme.state === 'disabled'
                                        ? 'Enable theme'
                                        : 'Activate theme'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function DeleteThemeDialog({ theme }: { theme: ThemeSummary }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    Delete
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Delete {theme.display_name}?</DialogTitle>
                <DialogDescription>
                    The theme will be removed from Appearance. Its package is
                    archived on this server and your customizations are kept.
                    Products, images and orders are unaffected. You can restore
                    the theme by uploading its ZIP again.
                </DialogDescription>
                <Form action={`/admin/themes/${theme.id}`} method="delete">
                    {({ processing, errors }) => (
                        <>
                            <InputError message={errors.theme} />
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>
                                <Button
                                    variant="destructive"
                                    disabled={processing}
                                >
                                    Delete theme
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function UpdateDialog({ theme }: { theme: ThemeSummary }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    Update
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>
                    Update {theme.display_name} to v{theme.available_version}?
                </DialogTitle>
                <DialogDescription>
                    The new version's defaults replace the old ones. Everything
                    you changed is kept; anything the new version no longer
                    supports is listed after the update.
                </DialogDescription>
                {theme.changelog.length > 0 && (
                    <ul className="grid gap-2 text-sm">
                        {theme.changelog.map((entry) => (
                            <li key={entry.version}>
                                <span className="font-medium">
                                    v{entry.version}
                                </span>{' '}
                                <span className="text-muted-foreground">
                                    ({entry.date})
                                </span>
                                <p className="text-muted-foreground">
                                    {entry.notes}
                                </p>
                            </li>
                        ))}
                    </ul>
                )}
                <Form {...ThemeController.update.form(theme.id)}>
                    {({ processing }) => (
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button variant="secondary">Cancel</Button>
                            </DialogClose>
                            <Button type="submit" disabled={processing}>
                                Apply update
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function DemoDialog({ theme }: { theme: ThemeSummary }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button size="sm" variant="ghost">
                    Import demo content
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>
                    Import {theme.display_name} demo content?
                </DialogTitle>
                <DialogDescription>
                    Adds sample categories and published products to your
                    catalog. Items whose web address or SKU already exists are
                    skipped; nothing you already have is changed. You can delete
                    the demo products later.
                </DialogDescription>
                <Form
                    {...ThemeController.demo.form(theme.id)}
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <label className="flex items-start gap-2 text-sm">
                                <Checkbox
                                    name="confirmed"
                                    value="1"
                                    className="mt-0.5"
                                />
                                Add the demo products and categories to my
                                store.
                            </label>
                            <InputError
                                message={errors.confirmed ?? errors.theme}
                            />
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    Import
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

ThemesIndex.layout = {
    breadcrumbs: [{ title: 'Appearance', href: index() }],
};
