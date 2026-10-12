import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';

/**
 * Minimal storefront entry point. The themed storefront (catalog, cart,
 * checkout) replaces this page in Phases 2 and 5.
 */
export default function Welcome({
    storeName,
    canRegister = true,
}: {
    storeName: string;
    canRegister?: boolean;
}) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title={storeName} />
            <div className="flex min-h-screen flex-col bg-background text-foreground">
                <header className="border-b">
                    <div className="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
                        <span className="text-lg font-semibold">
                            {storeName}
                        </span>
                        <nav className="flex items-center gap-2">
                            {auth.user ? (
                                <Button asChild variant="outline">
                                    <Link href={dashboard()}>
                                        {auth.isStaff ? 'Admin' : 'My account'}
                                    </Link>
                                </Button>
                            ) : (
                                <>
                                    <Button asChild variant="ghost">
                                        <Link href={login()}>Log in</Link>
                                    </Button>
                                    {canRegister && (
                                        <Button asChild variant="outline">
                                            <Link href={register()}>
                                                Create account
                                            </Link>
                                        </Button>
                                    )}
                                </>
                            )}
                        </nav>
                    </div>
                </header>
                <main className="mx-auto flex w-full max-w-6xl flex-1 flex-col justify-center px-4 py-16">
                    <h1 className="text-3xl font-semibold tracking-tight">
                        {storeName}
                    </h1>
                    <p className="mt-3 max-w-xl text-muted-foreground">
                        The catalog is not open yet. Create an account to be
                        ready when it is.
                    </p>
                </main>
                <footer className="border-t py-6 text-center text-xs text-muted-foreground">
                    Powered by EcoModular, open-source commerce under the MIT
                    License.
                </footer>
            </div>
        </>
    );
}
