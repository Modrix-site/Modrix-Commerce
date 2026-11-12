import { usePage } from '@inertiajs/react';
import { StoreMenuTree } from './store-menu';
export { StoreMenuLink } from './store-menu-link';
export function StoreFooterMenus() {
    const { storefront } = usePage().props;
    return (
        <div className="mx-auto grid w-full max-w-[var(--store-container,72rem)] gap-6 px-4 py-4 text-left sm:grid-cols-3">
            {storefront?.navigation?.footer.map((menu, index) => (
                <nav
                    key={index}
                    aria-label={menu.title}
                    className="grid content-start gap-2"
                >
                    <h3 className="font-semibold">{menu.title}</h3>
                    <StoreMenuTree
                        menu={menu}
                        orientation={menu.layout ?? 'vertical'}
                    />
                </nav>
            ))}
        </div>
    );
}
