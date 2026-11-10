import { Head, Link } from '@inertiajs/react';
import { Heart, ShoppingBag } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type WishlistItem = {
    id: number;
    product_id: number;
    variant_id: number | null;
    name: string;
    slug: string;
    image: string | null;
    sku: string;
    price: number;
    formatted_price: string;
    is_active: boolean;
};

type Props = {
    wishlist: {
        name: string;
        items_count: number;
    };
    items: WishlistItem[];
};

export default function SharedWishlistPage({ wishlist, items }: Props) {
    return (
        <div className="min-h-screen bg-background py-8 px-4 sm:px-6 lg:px-8">
            <Head title={`${wishlist.name} - Shared Wishlist`} />

            <div className="mx-auto max-w-5xl">
                {/* Header */}
                <div className="border-b pb-6 text-center sm:text-left">
                    <div className="flex items-center justify-center sm:justify-start gap-2">
                        <Heart className="size-6 text-rose-600 fill-rose-600" />
                        <h1 className="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                            {wishlist.name}
                        </h1>
                        <Badge variant="secondary" className="ml-2">
                            {items.length} {items.length === 1 ? 'item' : 'items'}
                        </Badge>
                    </div>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Shared wishlist. Browse the items below and visit the store to place an order.
                    </p>
                </div>

                {items.length === 0 ? (
                    <div className="my-16 text-center">
                        <p className="text-sm text-muted-foreground">This shared wishlist has no items.</p>
                        <Link href="/" className="mt-4 inline-block">
                            <Button className="bg-rose-600 hover:bg-rose-700 text-white">
                                <ShoppingBag className="mr-2 size-4" />
                                Browse Store
                            </Button>
                        </Link>
                    </div>
                ) : (
                    <div className="mt-8 grid gap-4 sm:grid-cols-2 md:grid-cols-3">
                        {items.map((item) => (
                            <Card key={item.id} className="overflow-hidden">
                                <CardContent className="p-4">
                                    <Link href={`/products/${item.slug}`} className="block">
                                        <div className="aspect-square w-full overflow-hidden rounded-lg bg-muted/40">
                                            {item.image ? (
                                                <img
                                                    src={item.image}
                                                    alt={item.name}
                                                    className="size-full object-cover object-center transition-transform hover:scale-105"
                                                />
                                            ) : (
                                                <div className="flex size-full items-center justify-center text-xs text-muted-foreground">
                                                    No Image
                                                </div>
                                            )}
                                        </div>
                                    </Link>

                                    <div className="mt-3">
                                        <Link
                                            href={`/products/${item.slug}`}
                                            className="line-clamp-2 text-sm font-semibold text-foreground hover:underline"
                                        >
                                            {item.name}
                                        </Link>
                                        <div className="mt-2 flex items-center justify-between">
                                            <span className="text-base font-bold text-foreground">
                                                {item.formatted_price}
                                            </span>
                                            <Link href={`/products/${item.slug}`}>
                                                <Button size="sm" variant="outline">
                                                    View Item
                                                </Button>
                                            </Link>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}
