import { Link, usePage } from '@inertiajs/react';
import type { ComponentProps } from 'react';
export function StoreMenuLink(props: ComponentProps<typeof Link>) {
    return typeof props.href === 'string' &&
        props.href.startsWith('https://') ? (
        <a href={props.href} className={props.className}>
            {props.children}
        </a>
    ) : (
        <Link {...props} />
    );
}
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
                    {menu.links.map((link, i) => (
                        <StoreMenuLink
                            key={i}
                            href={link.url}
                            className="text-muted-foreground hover:text-foreground"
                        >
                            {link.label}
                        </StoreMenuLink>
                    ))}
                </nav>
            ))}
        </div>
    );
}
