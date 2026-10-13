import { Form, router } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Trash2 } from 'lucide-react';
import ProductImageController from '@/actions/App/Http/Controllers/Admin/Catalog/ProductImageController';
import { MediaPicker } from '@/components/admin/media-picker';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useCan } from '@/hooks/use-can';
import { fromLibrary } from '@/routes/admin/products/images';

type Image = { id: number; url: string; alt: string | null };

/**
 * Upload, order, describe and remove product images. The first image is the
 * main image on the storefront.
 */
export function ProductImages({
    productId,
    images,
}: {
    productId: number;
    images: Image[];
}) {
    const can = useCan();
    const move = (index: number, direction: -1 | 1) => {
        const order = images.map((i) => i.id);
        const target = index + direction;
        [order[index], order[target]] = [order[target], order[index]];
        router.visit(ProductImageController.reorder(productId), {
            data: { order },
            preserveScroll: true,
        });
    };

    const remove = (image: Image) => {
        if (window.confirm('Remove this image? This cannot be undone.')) {
            router.visit(
                ProductImageController.destroy({
                    product: productId,
                    image: image.id,
                }),
                { preserveScroll: true },
            );
        }
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>Images</CardTitle>
                <CardDescription>
                    JPEG, PNG or WebP, up to 8 MB each. Images are re-encoded on
                    upload, which removes embedded metadata such as GPS
                    location. The first image is the main image. Images can also
                    be chosen from the media library.
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-4">
                {images.length > 0 && (
                    <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {images.map((image, index) => (
                            <li
                                key={image.id}
                                className="grid gap-2 rounded-lg border p-3"
                            >
                                <img
                                    src={image.url}
                                    alt={image.alt ?? ''}
                                    className="aspect-square w-full rounded object-cover"
                                />
                                <Form
                                    {...ProductImageController.update.form({
                                        product: productId,
                                        image: image.id,
                                    })}
                                    options={{ preserveScroll: true }}
                                    className="flex gap-2"
                                >
                                    <Input
                                        name="alt"
                                        defaultValue={image.alt ?? ''}
                                        aria-label="Alternative text"
                                        placeholder="Alternative text"
                                    />
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        size="sm"
                                    >
                                        Save
                                    </Button>
                                </Form>
                                <div className="flex gap-1">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        disabled={index === 0}
                                        onClick={() => move(index, -1)}
                                        aria-label="Move image earlier"
                                    >
                                        <ArrowUp />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        disabled={index === images.length - 1}
                                        onClick={() => move(index, 1)}
                                        aria-label="Move image later"
                                    >
                                        <ArrowDown />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="ml-auto text-destructive"
                                        onClick={() => remove(image)}
                                        aria-label="Remove image"
                                    >
                                        <Trash2 />
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}

                <Form
                    {...ProductImageController.store.form(productId)}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="flex flex-col gap-2 sm:flex-row sm:items-center"
                >
                    {({ processing, errors }) => (
                        <>
                            <Input
                                type="file"
                                name="images[]"
                                accept="image/jpeg,image/png,image/webp"
                                multiple
                                required
                                aria-label="Choose images"
                            />
                            <Button type="submit" disabled={processing}>
                                Upload
                            </Button>
                            {can('media.manage') && (
                                <MediaPicker
                                    action={fromLibrary.url(productId)}
                                />
                            )}
                            <InputError
                                message={
                                    errors.images ??
                                    errors['images.0'] ??
                                    errors.image
                                }
                            />
                        </>
                    )}
                </Form>
            </CardContent>
        </Card>
    );
}
