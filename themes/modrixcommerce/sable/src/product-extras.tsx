import { router } from "@inertiajs/react";
import { useEffect, useState } from "react";
import { Gift } from "lucide-react";
import type { SlotItem } from "@/components/extensions/extension-slot";
import type { MoneyValue } from "@/types";
type Addon = {
    id: number;
    name: string;
    description: string | null;
    type: string;
    price: MoneyValue;
    selected: boolean;
};
type AddonProps = { productId: number; toggleUrl: string; addons: Addon[] };
export function isFreeServiceSlot(item: SlotItem): boolean {
    const addons = item.props.addons as Addon[] | undefined;
    return (
        item.component.endsWith("/ProductAddons") &&
        Array.isArray(addons) &&
        addons.length > 0 &&
        addons.every(
            (a) =>
                a.type === "service" && Number(a.price.display_amount ?? a.price.amount ?? 0) === 0,
        )
    );
}
export function GiftOptions({ item }: { item: SlotItem }) {
    const { productId, toggleUrl, addons } = item.props as unknown as AddonProps;
    const [pending, setPending] = useState<number | null>(null);
    const [selected, setSelected] = useState<Record<number, boolean>>(() =>
        Object.fromEntries(addons.map((a) => [a.id, a.selected])),
    );
    useEffect(
        () => setSelected(Object.fromEntries(addons.map((a) => [a.id, a.selected]))),
        [addons],
    );
    return (
        <div className="sa-gift-options">
            {addons.map((addon) => (
                <label className="sa-gift-option" key={addon.id}>
                    <input
                        type="checkbox"
                        checked={Boolean(selected[addon.id])}
                        disabled={pending !== null}
                        onChange={(e) => {
                            const next = e.target.checked;
                            setSelected({ ...selected, [addon.id]: next });
                            setPending(addon.id);
                            router.post(
                                toggleUrl,
                                { product_id: productId, addon_id: addon.id },
                                {
                                    preserveScroll: true,
                                    preserveState: true,
                                    onError: () => setSelected({ ...selected, [addon.id]: !next }),
                                    onFinish: () => setPending(null),
                                },
                            );
                        }}
                    />
                    <span>
                        <strong>{addon.name}</strong>
                        {addon.description && <small>{addon.description}</small>}
                    </span>
                    <Gift size={43} strokeWidth={1} />
                </label>
            ))}
        </div>
    );
}
