import { Head, Link, usePage } from '@inertiajs/react';
import { ProductGrid } from '@/components/storefront/product-card';
import { Button } from '@/components/ui/button';
import { category, search } from '@/routes/storefront';
import type { ProductCard } from '@/types';

type Props = {
    products: ProductCard[];
    categories: {
        id: number;
        name: string;
        slug: string;
        description: string | null;
    }[];
};

export default function Home({ products, categories }: Props) {
    const { storefront } = usePage().props;

    return (
        <>
            <Head title={storefront?.storeName ?? 'Home'} />
            <section className="grid gap-3 border-b pb-10">
                <h1 className="text-3xl font-semibold tracking-tight">
                    {storefront?.storeName}
                </h1>
                <p className="max-w-xl text-muted-foreground">
                    Browse the latest arrivals or shop by category.
                </p>
                <div>
                    <Button asChild>
                        <Link href={search()}>Shop all products</Link>
                    </Button>
                </div>
            </section>

            {categories.length > 0 && (
                <section
                    className="grid gap-4 py-10"
                    aria-labelledby="categories-heading"
                >
                    <h2
                        id="categories-heading"
                        className="text-xl font-semibold"
                    >
                        Categories
                    </h2>
                    <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {categories.map((c) => (
                            <li key={c.id}>
                                <Link
                                    href={category(c.slug)}
                                    className="block rounded-lg border p-4 hover:bg-muted/50"
                                >
                                    <span className="font-medium">
                                        {c.name}
                                    </span>
                                    {c.description && (
                                        <span className="mt-1 line-clamp-2 block text-sm text-muted-foreground">
                                            {c.description}
                                        </span>
                                    )}
                                </Link>
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            <section
                className="grid gap-4 py-10"
                aria-labelledby="latest-heading"
            >
                <h2 id="latest-heading" className="text-xl font-semibold">
                    New arrivals
                </h2>
                {products.length > 0 ? (
                    <ProductGrid products={products} />
                ) : (
                    <p className="text-muted-foreground">
                        No products are available yet. Please check back soon.
                    </p>
                )}
            </section>
        </>
    );
}
