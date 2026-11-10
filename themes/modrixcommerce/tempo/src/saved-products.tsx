import { Heart, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { StoreMenuLink as Link } from '@/components/storefront/footer-menus';

export const savedKey = 'tempo.saved-products';
const detailsKey = 'tempo.saved-details';
type SavedProduct = { id: number; name: string; slug: string };

export function readSaved(): number[] {
    try {
        const value: unknown = JSON.parse(
            localStorage.getItem(savedKey) || '[]',
        );
        return Array.isArray(value)
            ? value.filter((id): id is number => Number.isInteger(id) && id > 0)
            : [];
    } catch {
        return [];
    }
}

function readDetails(): SavedProduct[] {
    try {
        const value: unknown = JSON.parse(
            localStorage.getItem(detailsKey) || '[]',
        );
        return Array.isArray(value)
            ? value.filter(
                  (item): item is SavedProduct =>
                      item &&
                      Number.isInteger(item.id) &&
                      typeof item.name === 'string' &&
                      typeof item.slug === 'string',
              )
            : [];
    } catch {
        return [];
    }
}

export function rememberSaved(product: SavedProduct) {
    const details = readDetails().filter((item) => item.id !== product.id);
    localStorage.setItem(
        detailsKey,
        JSON.stringify([...details, product].slice(-500)),
    );
}

export function SavedProducts() {
    const dialog = useRef<HTMLDialogElement>(null);
    const [items, setItems] = useState<SavedProduct[]>([]);
    useEffect(() => {
        const refresh = () => {
            const ids = readSaved();
            const details = readDetails();
            setItems(
                ids.map(
                    (id) =>
                        details.find((item) => item.id === id) ?? {
                            id,
                            name: 'Saved product',
                            slug: '',
                        },
                ),
            );
        };
        refresh();
        window.addEventListener('storage', refresh);
        window.addEventListener('tempo:saved', refresh);
        return () => {
            window.removeEventListener('storage', refresh);
            window.removeEventListener('tempo:saved', refresh);
        };
    }, []);
    return (
        <>
            <button
                type="button"
                aria-label={`Saved products on this device, ${items.length} items`}
                aria-haspopup="dialog"
                onClick={() => dialog.current?.showModal()}
            >
                <Heart size={18} />
            </button>
            <dialog
                ref={dialog}
                className="tp-saved-dialog"
                aria-label="Saved products"
                onClick={(event) => {
                    if (event.target === dialog.current) dialog.current.close();
                }}
            >
                <div className="tp-menu-title">
                    <h2>Saved for later ({items.length})</h2>
                    <button
                        type="button"
                        aria-label="Close saved products"
                        onClick={() => dialog.current?.close()}
                    >
                        <X size={20} />
                    </button>
                </div>
                <p>
                    Saved on this device. Current prices and availability appear
                    on each product page.
                </p>
                {items.length ? (
                    <ul>
                        {items.map((item) => (
                            <li key={item.id}>
                                <Link
                                    href={
                                        item.slug
                                            ? `/p/${encodeURIComponent(item.slug)}`
                                            : '/search'
                                    }
                                    onClick={() => dialog.current?.close()}
                                >
                                    {item.name}
                                </Link>
                                <button
                                    type="button"
                                    aria-label={`Remove ${item.name} from saved products`}
                                    onClick={() => {
                                        try {
                                            localStorage.setItem(
                                                savedKey,
                                                JSON.stringify(
                                                    readSaved().filter(
                                                        (id) => id !== item.id,
                                                    ),
                                                ),
                                            );
                                            window.dispatchEvent(
                                                new Event('tempo:saved'),
                                            );
                                        } catch {
                                            /* Device storage is optional. */
                                        }
                                    }}
                                >
                                    Remove
                                </button>
                            </li>
                        ))}
                    </ul>
                ) : (
                    <p>Use the heart on a product to save it here.</p>
                )}
                <Link
                    className="store-button tp-button tp-button-primary"
                    href="/search"
                    onClick={() => dialog.current?.close()}
                >
                    Continue shopping
                </Link>
            </dialog>
        </>
    );
}
