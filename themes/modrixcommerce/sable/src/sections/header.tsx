import { useState } from 'react';
import { usePage } from '@inertiajs/react';
import {
    Search,
    UserRound,
    ShoppingBag,
    Menu,
    ChevronDown,
} from 'lucide-react';
import { SearchToggle } from '@/components/storefront/search-toggle';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { StoreMenuLink as Link } from '@/components/storefront/store-menu-link';
import {
    blockAttributes,
    type ThemeSectionProps,
} from '@/components/storefront/theme-sections';
import { str } from '../utils';
export default function Header({ section }: ThemeSectionProps) {
    const { component, props } = usePage();
    const [open, setOpen] = useState(false);
    const s = section.settings;
    const brand = str(s.brand);
    const overlay = component === 'storefront/home' && s.overlay === true;
    const links = section.blocks.filter((b) => str(b.settings.link));
    return (
        <header className={'sa-header ' + (overlay ? 'sa-header-overlay' : '')}>
            {str(s.announcement) && (
                <p className="sa-announcement">{str(s.announcement)}</p>
            )}
            <div className="sa-header-row">
                <Sheet open={open} onOpenChange={setOpen}>
                    <SheetTrigger
                        className="sa-icon sa-mobile"
                        aria-label="Open menu"
                    >
                        <Menu size={20} />
                    </SheetTrigger>
                    <SheetContent side="left" className="sa-menu-panel">
                        <SheetTitle>{brand}</SheetTitle>
                        <SheetDescription>
                            Explore the collection
                        </SheetDescription>
                        <nav>
                            {links.map((b) => (
                                <Link
                                    key={b.id}
                                    onClick={() => setOpen(false)}
                                    href={str(b.settings.link)}
                                    {...blockAttributes(b)}
                                >
                                    {str(b.settings.label)}
                                </Link>
                            ))}
                        </nav>
                    </SheetContent>
                </Sheet>
                <Link href="/" className="sa-wordmark">
                    {str(s.logo) ? (
                        <img src={str(s.logo)} alt={brand} />
                    ) : (
                        brand
                    )}
                </Link>
                <nav className="sa-desktop-nav" aria-label="Main navigation">
                    {links.slice(0, 1).map((b) => (
                        <Link
                            key={b.id}
                            href={str(b.settings.link)}
                            {...blockAttributes(b)}
                        >
                            {str(b.settings.label)}
                        </Link>
                    ))}
                    {s.show_collections !== false &&
                        Boolean(props.storefront?.categories.length) && (
                            <details className="sa-collections-menu">
                                <summary>
                                    {str(s.collections_label)}
                                    <ChevronDown size={12} />
                                </summary>
                                <div>
                                    {props.storefront?.categories.map((c) => (
                                        <Link
                                            key={c.id}
                                            href={'/c/' + c.slug}
                                            onClick={(e) => {
                                                const details =
                                                    e.currentTarget.closest(
                                                        'details',
                                                    );
                                                if (details)
                                                    details.open = false;
                                            }}
                                        >
                                            {c.name}
                                        </Link>
                                    ))}
                                </div>
                            </details>
                        )}
                    {links.slice(1).map((b) => (
                        <Link
                            key={b.id}
                            href={str(b.settings.link)}
                            {...blockAttributes(b)}
                        >
                            {str(b.settings.label)}
                        </Link>
                    ))}
                </nav>
                <div className="sa-actions">
                    <SearchToggle className="sa-icon">
                        <Search size={18} />
                    </SearchToggle>
                    <Link
                        className="sa-icon sa-account"
                        href="/account"
                        aria-label="My account"
                    >
                        <UserRound size={18} />
                    </Link>
                    <Link
                        className="sa-icon"
                        href="/cart"
                        aria-label={
                            'Shopping bag, ' +
                            (props.storefront?.cartCount ?? 0) +
                            ' items'
                        }
                    >
                        <ShoppingBag size={18} />
                        <span>{props.storefront?.cartCount ?? 0}</span>
                    </Link>
                </div>
            </div>
        </header>
    );
}
