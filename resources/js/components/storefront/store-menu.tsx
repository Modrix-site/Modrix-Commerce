import { useId, useRef, useState } from 'react';
import { ChevronDown } from 'lucide-react';
import type { StoreMenu, StoreMenuItem, StoreMenus } from '@/types/storefront';
import { StoreMenuLink } from './store-menu-link';
import '../../../css/store-navigation.css';

type Props = {
    menu?: StoreMenu;
    links?: StoreMenuItem[];
    orientation?: 'horizontal' | 'vertical';
    className?: string;
    linkClassName?: string;
    onNavigate?: () => void;
};

/**
 * Admin → Navigation menus for section themes, by a section's "menu_source"
 * setting: "auto" (default) uses them once the merchant saved menus there,
 * "store" always, "theme" never. A menu is undefined when the theme should
 * keep its own links (hidden, empty, or not in use).
 */
export function themeMenus(
    navigation: StoreMenus | undefined,
    source: unknown,
): {
    header?: StoreMenu;
    sidebar?: StoreMenu;
    mobile?: StoreMenu;
    footer: StoreMenu[];
} {
    const on =
        source === 'store' ||
        (source !== 'theme' && navigation?.custom === true);
    const pick = (menu?: StoreMenu) =>
        on && menu && menu.enabled !== false && menu.links.length
            ? menu
            : undefined;
    return {
        header: pick(navigation?.header),
        sidebar: pick(navigation?.sidebar),
        mobile: pick(navigation?.mobile),
        footer: on
            ? (navigation?.footer ?? []).filter(
                  (m) => m.enabled !== false && m.links.length,
              )
            : [],
    };
}

/** Shared by every theme: desktop dropdowns and mobile/sidebar accordion trees. */
export function StoreMenuTree({
    menu,
    links,
    orientation,
    className = '',
    linkClassName,
    onNavigate,
}: Props) {
    const items = links ?? menu?.links ?? [];
    if (menu?.enabled === false || !items.length) return null;
    return (
        <ul
            className={`store-menu-tree ${className}`}
            data-orientation={orientation ?? menu?.layout ?? 'horizontal'}
        >
            {items.map((item, index) => (
                <MenuBranch
                    key={`${index}:${item.url}`}
                    item={item}
                    depth={0}
                    onNavigate={onNavigate}
                    linkClassName={linkClassName}
                />
            ))}
        </ul>
    );
}

function MenuBranch({
    item,
    depth,
    onNavigate,
    linkClassName,
}: {
    item: StoreMenuItem;
    depth: number;
    onNavigate?: () => void;
    linkClassName?: string;
}) {
    const [open, setOpen] = useState(false);
    const id = useId();
    const toggle = useRef<HTMLButtonElement>(null);
    const children = item.children ?? [];
    return (
        <li
            className="store-menu-item"
            data-depth={depth}
            onKeyDown={(event) => {
                if (event.key === 'Escape' && open) {
                    event.stopPropagation();
                    setOpen(false);
                    toggle.current?.focus();
                }
            }}
        >
            <div className="store-menu-row">
                <StoreMenuLink
                    className={`store-menu-link ${linkClassName ?? ''}`}
                    href={item.url}
                    onClick={() => {
                        setOpen(false);
                        onNavigate?.();
                    }}
                >
                    {item.label}
                </StoreMenuLink>
                {children.length > 0 && (
                    <button
                        type="button"
                        className="store-menu-toggle"
                        ref={toggle}
                        aria-label={`${open ? 'Close' : 'Open'} ${item.label} submenu`}
                        aria-expanded={open}
                        aria-controls={id}
                        onClick={() => setOpen(!open)}
                    >
                        <ChevronDown size={14} aria-hidden />
                    </button>
                )}
            </div>
            {children.length > 0 && (
                <ul id={id} className="store-menu-submenu" hidden={!open}>
                    {children.map((child, index) => (
                        <MenuBranch
                            key={`${index}:${child.url}`}
                            item={child}
                            depth={depth + 1}
                            onNavigate={onNavigate}
                        />
                    ))}
                </ul>
            )}
        </li>
    );
}
