import { Form, Head, Link } from '@inertiajs/react';
import { Eye } from 'lucide-react';
import ThemeController from '@/actions/App/Http/Controllers/Admin/ThemeController';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import {
    CustomizerTabs,
    CUSTOMIZER_PANEL_ID,
    useCustomizerTab,
    groupsForTab,
} from '@/components/admin/themes/customizer-tabs';
import { ExtensionBlocksCard } from '@/components/admin/themes/extension-blocks-card';
import { ExtensionSettingsCard } from '@/components/admin/themes/extension-settings-card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import type { ThemeCustomizerProps } from '@/lib/theme-customizer';
import { index } from '@/routes/admin/themes';

/** Circuit owns this editor; platform controls keep saves and uploads consistent. */
export default function CircuitCustomizer({
    theme,
    design,
    sections,
    images,
    blocks = [],
    extensionSettings = {},
    customizer,
    controls,
}: ThemeCustomizerProps) {
    const [tab, setTab] = useCustomizerTab();
    const { Design, Sections, Images, Reset } = controls;

    return (
        <>
            <Head title={`${theme.display_name} studio`} />
            <AdminPage>
                <PageHeader
                    title={`${theme.display_name} studio`}
                    description="Style your electronics store, tune product displays and build your campaigns."
                    actions={
                        <div className="flex gap-2">
                            <Form {...ThemeController.preview.form(theme.id)}>
                                <Button variant="outline">
                                    <Eye /> Preview storefront
                                </Button>
                            </Form>
                            <Button variant="ghost" asChild>
                                <Link href={index()}>All themes</Link>
                            </Button>
                        </div>
                    }
                />
                <section className="rounded-xl border bg-muted/30 p-5">
                    <p className="text-xs font-semibold tracking-widest uppercase">
                        Circuit theme studio
                    </p>
                    <p className="mt-2 text-sm text-muted-foreground">
                        {theme.state === 'active'
                            ? 'Saved changes appear on your live storefront.'
                            : 'Preview your changes before activating this theme.'}{' '}
                        Edit campaign images and tiles in Homepage; manage
                        shared links in Navigation.
                    </p>
                </section>
                {design.ignored.length > 0 && (
                    <Alert>
                        <AlertTitle>
                            Settings retained from another version
                        </AlertTitle>
                        <AlertDescription>
                            {design.ignored.join(', ')}
                        </AlertDescription>
                    </Alert>
                )}
                <CustomizerTabs
                    fields={design.fields}
                    overridden={design.overridden}
                    value={tab}
                    onChange={setTab}
                />
                <div
                    id={CUSTOMIZER_PANEL_ID}
                    role="tabpanel"
                    aria-labelledby={`customizer-tab-${tab}`}
                    className="grid gap-5"
                >
                    <div
                        hidden={
                            tab !== 'home' || customizer?.sections === false
                        }
                    >
                        <Sections
                            themeId={theme.id}
                            sections={sections}
                            images={images}
                        />
                    </div>
                    <div hidden={groupsForTab(design.fields, tab).length === 0}>
                        {[
                            'header',
                            'catalog',
                            'product',
                            'cart',
                            'checkout',
                        ].includes(tab) && (
                            <p className="mb-3 text-sm text-muted-foreground">
                                Page settings apply to your whole store,
                                whichever theme is active.
                            </p>
                        )}
                        <Design
                            themeId={theme.id}
                            design={design}
                            images={images}
                            tab={tab}
                        />
                    </div>
                    {(tab === 'general' || tab === 'home') && (
                        <Images images={images} />
                    )}
                    {tab === 'general' && <Reset theme={theme} />}
                    <ExtensionBlocksCard key={tab} blocks={blocks} tab={tab} />
                    <ExtensionSettingsCard groups={extensionSettings[tab]} />
                </div>
            </AdminPage>
        </>
    );
}
