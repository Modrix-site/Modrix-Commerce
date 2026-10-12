import { Form, Link, usePage } from '@inertiajs/react';
import { Search, ShoppingBag, User } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useFlashToast } from '@/hooks/use-flash-toast';
import { dashboard, home, login } from '@/routes';
import { show as cartShow } from '@/routes/cart';
import { category, search } from '@/routes/storefront';

/**
 * Default storefront chrome. Theme packages will replace this presentation
 * in Phase 5; cart, checkout and account behaviour stay in the core.
 */
export default function StorefrontLayout({
    children,
}: {
    children: ReactNode;
}) {
    const { storefront, auth } = usePage().props;
    useFlashToast();

    const storeName = storefront?.storeName ?? '';
    const cartCount = storefront?.cartCount ?? 0;

    return (
        <div className="flex min-h-screen flex-col bg-background text-foreground">
            <a
                href="#main"
                className="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded focus:bg-background focus:px-3 focus:py-2"
            >
                Skip to content
            </a>
            <header className="border-b">
                <div className="mx-auto flex max-w-6xl flex-wrap items-center gap-3 px-4 py-3">
                    <Link
                        href={home()}
                        className="mr-auto text-lg font-semibold"
                    >
                        {storeName}
                    </Link>
                    <Form
                        {...search.form()}
                        className="order-last flex w-full items-center gap-2 sm:order-none sm:w-72"
                        role="search"
                    >
                        <Input
                            type="search"
                            name="q"
                            placeholder="Search products"
                            aria-label="Search products"
                        />
                        <Button
                            type="submit"
                            variant="outline"
                            size="icon"
                            aria-label="Search"
                        >
                            <Search />
                        </Button>
                    </Form>
                    <nav
                        className="flex items-center gap-1"
                        aria-label="Account"
                    >
                        <Button variant="ghost" size="sm" asChild>
                            <Link href={auth.user ? dashboard() : login()}>
                                <User />
                                <span className="hidden sm:inline">
                                    {auth.user
                                        ? auth.isStaff
                                            ? 'Admin'
                                            : 'Account'
                                        : 'Log in'}
                                </span>
                            </Link>
                        </Button>
                        <Button variant="outline" size="sm" asChild>
                            <Link
                                href={cartShow()}
                                aria-label={`Cart, ${cartCount} items`}
                            >
                                <ShoppingBag />
                                <span className="tabular-nums">
                                    {cartCount}
                                </span>
                            </Link>
                        </Button>
                    </nav>
                </div>
                {storefront && storefront.categories.length > 0 && (
                    <nav aria-label="Categories" className="border-t">
                        <ul className="mx-auto flex max-w-6xl gap-5 overflow-x-auto px-4 py-2 text-sm">
                            <li>
                                <Link
                                    href={search()}
                                    className="whitespace-nowrap text-muted-foreground hover:text-foreground"
                                >
                                    All products
                                </Link>
                            </li>
                            {storefront.categories.map((c) => (
                                <li key={c.id}>
                                    <Link
                                        href={category(c.slug)}
                                        className="whitespace-nowrap text-muted-foreground hover:text-foreground"
                                    >
                                        {c.name}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </nav>
                )}
            </header>
            <main
                id="main"
                className="mx-auto w-full max-w-6xl flex-1 px-4 py-8"
            >
                {children}
            </main>
            <footer className="border-t py-6 text-center text-xs text-muted-foreground">
                © {new Date().getFullYear()} {storeName}. Powered by EcoModular,
                open-source commerce (MIT).
            </footer>
        </div>
    );
}
