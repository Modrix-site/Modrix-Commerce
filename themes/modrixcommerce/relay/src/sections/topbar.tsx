import { usePage, router } from '@inertiajs/react';
import { UserRound } from 'lucide-react';
import { StoreMenuLink as Link } from '@/components/storefront/store-menu-link';
import {
    blockAttributes,
    type ThemeSectionProps,
} from '@/components/storefront/theme-sections';
import { str } from '../components';
export default function Topbar({ section }: ThemeSectionProps) {
    const { storefront, auth } = usePage().props;
    const s = section.settings;
    const localize = (key: string, value: string) => {
        const u = new URL(window.location.href);
        u.searchParams.set(key, value);
        router.get(u.pathname + u.search, {}, { preserveScroll: true });
    };
    return (
        <div
            className="re-topbar"
            style={{ background: str(s.background), color: str(s.foreground) }}
        >
            <div className="re-frame">
                <p>{str(s.greeting)}</p>
                <nav aria-label="Store information">
                    {section.blocks.map((b) => (
                        <Link
                            key={b.id}
                            href={str(b.settings.link) || '/account/orders'}
                            {...blockAttributes(b)}
                        >
                            {str(b.settings.label)}
                        </Link>
                    ))}
                    {s.show_currency !== false &&
                        (storefront?.currencies?.length ?? 0) > 1 && (
                            <select
                                aria-label="Display currency"
                                value={
                                    storefront?.displayCurrency ??
                                    storefront?.currency
                                }
                                onChange={(e) =>
                                    localize('currency', e.target.value)
                                }
                            >
                                {storefront?.currencies?.map((c) => (
                                    <option key={c.code} value={c.code}>
                                        {c.code}
                                    </option>
                                ))}
                            </select>
                        )}
                    {s.show_language !== false &&
                        (storefront?.locales?.length ?? 0) > 1 && (
                            <select
                                aria-label="Language"
                                value={storefront?.locale}
                                onChange={(e) =>
                                    localize('lang', e.target.value)
                                }
                            >
                                {storefront?.locales.map((l) => (
                                    <option key={l.code} value={l.code}>
                                        {l.name}
                                    </option>
                                ))}
                            </select>
                        )}
                    <span className="re-top-account">
                        <UserRound size={14} />
                        {auth?.user ? (
                            <Link href="/account">{str(s.account_label)}</Link>
                        ) : (
                            <>
                                <Link href="/login">{str(s.login_label)}</Link>
                                <span>/</span>
                                <Link href="/register">
                                    {str(s.register_label)}
                                </Link>
                            </>
                        )}
                    </span>
                </nav>
            </div>
        </div>
    );
}
