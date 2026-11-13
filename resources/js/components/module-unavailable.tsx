import { Head } from '@inertiajs/react';
import { PackageX } from 'lucide-react';

/**
 * Shown instead of a module screen the store cannot load: the module was
 * installed without its prebuilt screens, or they failed to load. Better
 * than a click that silently does nothing.
 */
export function moduleUnavailable(page: string) {
    const [, vendor = '', name = ''] = page.split('/');
    const module = `${vendor}/${name}`;

    function ModuleUnavailable() {
        return (
            <>
                <Head title="Screen not available" />
                <div className="mx-auto grid max-w-xl justify-items-center gap-3 px-4 py-16 text-center">
                    <span className="rounded-full bg-muted p-3">
                        <PackageX
                            className="size-6 text-muted-foreground"
                            aria-hidden
                        />
                    </span>
                    <h1 className="text-xl font-semibold">
                        This screen is not available yet
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        The module <code>{module}</code> is installed, but its
                        screens could not be loaded. Update the module from
                        Admin &rarr; Extensions or Modrix Cloud to get a version
                        that includes them. If you build the store yourself,
                        rebuild the assets with <code>npm run build</code>.
                    </p>
                </div>
            </>
        );
    }

    return { default: ModuleUnavailable };
}
