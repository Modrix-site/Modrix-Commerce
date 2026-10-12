import { Form, Head, Link } from '@inertiajs/react';
import { ImageOff } from 'lucide-react';
import { useMemo, useState } from 'react';
import CartController from '@/actions/App/Http/Controllers/Storefront/CartController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { home } from '@/routes';
import { category } from '@/routes/storefront';
import type { MoneyValue } from '@/types';

type Variant = {
    id: number;
    name: string | null;
    sku: string;
    price: MoneyValue | null;
    compare_at_price: MoneyValue | null;
    option_value_ids: number[];
    purchasable: boolean;
    available: number | null;
    message: string | null;
};

type Props = {
    product: {
        id: number;
        name: string;
        slug: string;
        short_description: string | null;
        description: string | null;
        meta_title: string;
        meta_description: string | null;
        images: { id: number; url: string; alt: string | null }[];
        options: {
            id: number;
            name: string;
            values: { id: number; value: string }[];
        }[];
        variants: Variant[];
    };
    breadcrumbs: { name: string; slug: string }[];
};

export default function ProductPage({ product, breadcrumbs }: Props) {
    const [imageIndex, setImageIndex] = useState(0);
    const firstPurchasable =
        product.variants.find((v) => v.purchasable) ?? product.variants[0];
    const [selected, setSelected] = useState<Record<number, number>>(() => {
        const initial: Record<number, number> = {};
        product.options.forEach((option) => {
            const match = option.values.find((v) =>
                firstPurchasable?.option_value_ids.includes(v.id),
            );
            if (match) {
                initial[option.id] = match.id;
            }
        });

        return initial;
    });

    const variant = useMemo(() => {
        if (product.options.length === 0) {
            return product.variants[0];
        }

        const chosen = Object.values(selected);

        return product.variants.find(
            (v) =>
                chosen.length === product.options.length &&
                chosen.every((id) => v.option_value_ids.includes(id)),
        );
    }, [selected, product]);

    const image = product.images[imageIndex];

    return (
        <>
            <Head title={product.meta_title}>
                {product.meta_description && (
                    <meta
                        name="description"
                        content={product.meta_description}
                    />
                )}
            </Head>

            <nav
                aria-label="Breadcrumb"
                className="mb-6 text-sm text-muted-foreground"
            >
                <ol className="flex flex-wrap gap-1">
                    <li>
                        <Link href={home()} className="hover:text-foreground">
                            Home
                        </Link>
                    </li>
                    {breadcrumbs.map((b) => (
                        <li
                            key={b.slug}
                            className="before:mr-1 before:content-['/']"
                        >
                            <Link
                                href={category(b.slug)}
                                className="hover:text-foreground"
                            >
                                {b.name}
                            </Link>
                        </li>
                    ))}
                </ol>
            </nav>

            <div className="grid gap-10 md:grid-cols-2">
                <div className="grid content-start gap-3">
                    <div className="aspect-square overflow-hidden rounded-lg border bg-muted">
                        {image ? (
                            <img
                                src={image.url}
                                alt={image.alt ?? product.name}
                                className="size-full object-cover"
                            />
                        ) : (
                            <div className="flex size-full items-center justify-center text-muted-foreground">
                                <ImageOff className="size-8" aria-hidden />
                                <span className="sr-only">
                                    No image available
                                </span>
                            </div>
                        )}
                    </div>
                    {product.images.length > 1 && (
                        <ul
                            className="flex gap-2 overflow-x-auto"
                            aria-label="Product images"
                        >
                            {product.images.map((img, i) => (
                                <li key={img.id}>
                                    <button
                                        type="button"
                                        onClick={() => setImageIndex(i)}
                                        aria-label={`Show image ${i + 1}`}
                                        aria-current={i === imageIndex}
                                        className={`size-16 overflow-hidden rounded border ${i === imageIndex ? 'ring-2 ring-ring' : ''}`}
                                    >
                                        <img
                                            src={img.url}
                                            alt=""
                                            className="size-full object-cover"
                                        />
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                <div className="grid content-start gap-5">
                    <div className="grid gap-2">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {product.name}
                        </h1>
                        {variant?.price ? (
                            <p className="text-xl tabular-nums">
                                {variant.price.formatted}
                                {variant.compare_at_price && (
                                    <s className="ml-3 text-base text-muted-foreground">
                                        <span className="sr-only">Was </span>
                                        {variant.compare_at_price.formatted}
                                    </s>
                                )}
                            </p>
                        ) : (
                            <p className="text-muted-foreground">
                                Choose options to see the price.
                            </p>
                        )}
                        {product.short_description && (
                            <p className="text-muted-foreground">
                                {product.short_description}
                            </p>
                        )}
                    </div>

                    {product.options.map((option) => (
                        <fieldset key={option.id} className="grid gap-2">
                            <legend className="mb-2 text-sm font-medium">
                                {option.name}
                            </legend>
                            <div className="flex flex-wrap gap-2">
                                {option.values.map((value) => {
                                    const active =
                                        selected[option.id] === value.id;

                                    return (
                                        <Button
                                            key={value.id}
                                            type="button"
                                            size="sm"
                                            variant={
                                                active ? 'default' : 'outline'
                                            }
                                            aria-pressed={active}
                                            onClick={() =>
                                                setSelected((s) => ({
                                                    ...s,
                                                    [option.id]: value.id,
                                                }))
                                            }
                                        >
                                            {value.value}
                                        </Button>
                                    );
                                })}
                            </div>
                        </fieldset>
                    ))}

                    {variant ? (
                        <Form
                            {...CartController.store.form()}
                            options={{ preserveScroll: true }}
                            className="grid gap-3"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <input
                                        type="hidden"
                                        name="variant_id"
                                        value={variant.id}
                                    />
                                    <div className="flex items-end gap-3">
                                        <div className="grid w-24 gap-2">
                                            <Label htmlFor="quantity">
                                                Quantity
                                            </Label>
                                            <Input
                                                id="quantity"
                                                name="quantity"
                                                type="number"
                                                min={1}
                                                max={variant.available ?? 999}
                                                defaultValue={1}
                                                disabled={!variant.purchasable}
                                            />
                                        </div>
                                        <Button
                                            type="submit"
                                            disabled={
                                                processing ||
                                                !variant.purchasable
                                            }
                                        >
                                            {variant.purchasable
                                                ? 'Add to cart'
                                                : 'Unavailable'}
                                        </Button>
                                    </div>
                                    <InputError
                                        message={
                                            errors.quantity ?? errors.variant_id
                                        }
                                    />
                                    {variant.message && (
                                        <p className="text-sm text-muted-foreground">
                                            {variant.message}
                                        </p>
                                    )}
                                    {variant.purchasable &&
                                        variant.available !== null &&
                                        variant.available <= 5 && (
                                            <p className="text-sm text-muted-foreground">
                                                Only {variant.available} left.
                                            </p>
                                        )}
                                    <p className="text-xs text-muted-foreground">
                                        SKU {variant.sku}
                                    </p>
                                </>
                            )}
                        </Form>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            This combination is not available.
                        </p>
                    )}

                    {product.description && (
                        <section className="grid gap-2 border-t pt-5">
                            <h2 className="font-semibold">Description</h2>
                            <p className="whitespace-pre-line text-muted-foreground">
                                {product.description}
                            </p>
                        </section>
                    )}
                </div>
            </div>
        </>
    );
}
