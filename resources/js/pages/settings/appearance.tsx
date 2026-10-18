import { Head } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import { adminAccents, useAdminAccent } from '@/hooks/use-admin-accent';
import { useSidebarAppearance } from '@/hooks/use-sidebar-appearance';
import { cn } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';

export default function Appearance() {
    const { accent, updateAccent } = useAdminAccent();
    const { sidebarAppearance, updateSidebarAppearance } =
        useSidebarAppearance();
    return (
        <>
            <Head title="Appearance settings" />

            <h1 className="sr-only">Appearance settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Appearance settings"
                    description="Update the appearance settings for your account"
                />
                <AppearanceTabs />
                <Heading
                    variant="small"
                    title="Sidebar"
                    description="Choose the sidebar background independently of the page appearance. Black is the default."
                />
                <div
                    className="flex flex-wrap gap-3"
                    role="group"
                    aria-label="Sidebar appearance"
                >
                    {(
                        [
                            {
                                value: 'dark',
                                label: 'Black sidebar (default)',
                                color: '#090909',
                            },
                            {
                                value: 'light',
                                label: 'White sidebar',
                                color: '#ffffff',
                            },
                        ] as const
                    ).map((item) => (
                        <button
                            key={item.value}
                            type="button"
                            aria-pressed={sidebarAppearance === item.value}
                            onClick={() => updateSidebarAppearance(item.value)}
                            className={cn(
                                'flex items-center gap-2 rounded-lg border px-3 py-2 text-sm transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                sidebarAppearance === item.value
                                    ? 'border-primary bg-accent text-accent-foreground'
                                    : 'hover:bg-muted',
                            )}
                        >
                            <span
                                className="size-4 rounded border border-neutral-300"
                                style={{ backgroundColor: item.color }}
                            />
                            {item.label}
                        </button>
                    ))}
                </div>
                <Heading
                    variant="small"
                    title="Accent color"
                    description="Choose the color used for buttons, navigation and highlights across your workspace."
                />
                <div
                    className="flex flex-wrap gap-3"
                    role="group"
                    aria-label="Accent color"
                >
                    {adminAccents.map((item) => (
                        <button
                            key={item.value}
                            type="button"
                            aria-pressed={accent === item.value}
                            onClick={() => updateAccent(item.value)}
                            className={cn(
                                'flex items-center gap-2 rounded-lg border px-3 py-2 text-sm transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                accent === item.value
                                    ? 'border-primary bg-accent text-accent-foreground'
                                    : 'hover:bg-muted',
                            )}
                        >
                            <span
                                className="size-3 rounded-full"
                                style={{ backgroundColor: item.color }}
                            />
                            {item.label}
                        </button>
                    ))}
                </div>
            </div>
        </>
    );
}

Appearance.layout = {
    breadcrumbs: [
        {
            title: 'Appearance settings',
            href: editAppearance(),
        },
    ],
};
