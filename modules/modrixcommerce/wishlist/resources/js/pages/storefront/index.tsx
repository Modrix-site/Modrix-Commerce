import { Head, Link, router } from '@inertiajs/react';
import {
    Check,
    Copy,
    Heart,
    ShoppingBag,
    ShoppingCart,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
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
    created_at: string;
};

type Props = {
    wishlist: {
        id: number;
        name: string;
        share_url: string | null;
        items_count: number;
    };
    items: WishlistItem[];
    settings: {
        enabled: boolean;
        allow_sharing: boolean;
        button_label: string;
    };
};

export default function WishlistStorefrontPage({
    wishlist,
    items,
    settings,
}: Props) {
    const [copied, setCopied] = useState(false);

    const handleCopyShareLink = () => {
        if (!wishlist.share_url) {
            return;
        }
        void navigator.clipboard.writeText(wishlist.share_url);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    const handleMoveToCart = (itemId: number) => {
        router.post(
            `/m/modrixcommerce/wishlist/wishlist/items/${itemId}/move-to-cart`,
            {},
            { preserveScroll: true },
        );
    };

    const handleRemove = (itemId: number) => {
        router.delete(`/m/modrixcommerce/wishlist/wishlist/items/${itemId}`, {
            preserveScroll: true,
        });
    };

    return (
        <div className="min-h-screen bg-background px-4 py-8 sm:px-6 lg:px-8">
            <Head title="My Wishlist" />

            <div className="mx-auto max-w-5xl">
                {/* Header */}
                <div className="flex flex-col gap-4 border-b pb-6 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <Heart className="size-6 fill-rose-600 text-rose-600" />
                            <h1 className="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                                {wishlist.name}
                            </h1>
                            <Badge variant="secondary" className="ml-2">
                                {items.length}{' '}
                                {items.length === 1 ? 'item' : 'items'}
                            </Badge>
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Products you have saved for later. Move them to your
                            cart when you are ready to purchase.
                        </p>
                    </div>

                    {settings.allow_sharing &&
                        wishlist.share_url &&
                        items.length > 0 && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={handleCopyShareLink}
                                className="w-full sm:w-auto"
                            >
                                {copied ? (
                                    <>
                                        <Check className="mr-2 size-4 text-emerald-600" />
                                        Link Copied!
                                    </>
                                ) : (
                                    <>
                                        <Copy className="mr-2 size-4" />
                                        Share Wishlist
                                    </>
                                )}
                            </Button>
                        )}
                </div>

                {/* Items List */}
                {items.length === 0 ? (
                    <div className="my-16 flex flex-col items-center justify-center text-center">
                        <div className="flex size-16 items-center justify-center rounded-full bg-rose-50 dark:bg-rose-950/30">
                            <Heart className="size-8 text-rose-500" />
                        </div>
                        <h2 className="mt-4 text-lg font-semibold text-foreground">
                            Your wishlist is currently empty
                        </h2>
                        <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                            Browse the store and tap the heart icon on any
                            product to save it here for later.
                        </p>
                        <Link href="/" className="mt-6">
                            <Button className="bg-rose-600 text-white hover:bg-rose-700">
                                <ShoppingBag className="mr-2 size-4" />
                                Discover Products
                            </Button>
                        </Link>
                    </div>
                ) : (
                    <div className="mt-8 grid gap-4 sm:grid-cols-2 md:grid-cols-3">
                        {items.map((item) => (
                            <Card
                                key={item.id}
                                className="flex flex-col justify-between overflow-hidden"
                            >
                                <CardContent className="flex h-full flex-col justify-between p-4">
                                    <div>
                                        {/* Product Image */}
                                        <Link
                                            href={`/products/${item.slug}`}
                                            className="block"
                                        >
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

                                        {/* Info */}
                                        <div className="mt-3">
                                            <Link
                                                href={`/products/${item.slug}`}
                                                className="line-clamp-2 text-sm font-semibold text-foreground hover:underline"
                                            >
                                                {item.name}
                                            </Link>
                                            <div className="mt-1 flex items-baseline justify-between">
                                                <span className="text-base font-bold text-foreground">
                                                    {item.formatted_price}
                                                </span>
                                                <span className="text-[11px] text-muted-foreground">
                                                    Saved {item.created_at}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Action buttons */}
                                    <div className="mt-4 flex items-center gap-2 border-t pt-2">
                                        <Button
                                            size="sm"
                                            className="flex-1 bg-rose-600 text-white hover:bg-rose-700"
                                            onClick={() =>
                                                handleMoveToCart(item.id)
                                            }
                                        >
                                            <ShoppingCart className="mr-1.5 size-4" />
                                            Move to Cart
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                handleRemove(item.id)
                                            }
                                            className="text-muted-foreground hover:text-destructive"
                                            aria-label="Remove item"
                                        >
                                            <Trash2 className="size-4" />
                                        </Button>
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
