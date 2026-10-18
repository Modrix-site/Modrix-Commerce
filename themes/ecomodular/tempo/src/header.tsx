import { router, usePage } from '@inertiajs/react';
import { Menu, Search, User, X } from 'lucide-react';
import { useRef } from 'react';
import { PlaceSlot } from '@/components/extensions/place-slot';
import { CustomerNotificationBell } from '@/components/storefront/customer-notification-bell';
import { CartIcon, SearchIconLink, useShowAccountLink, useShowCartCount } from '@/components/storefront/page-settings/header-parts';
import { StoreMenuLink as Link } from '@/components/storefront/footer-menus';
import { useTempo } from './components';
import './style.css';

export default function TempoHeader() {
    const { storefront, text, show } = useTempo();
    const page = usePage();
    const { auth } = page.props;
    const menu = useRef<HTMLDialogElement>(null);
    const showAccount = useShowAccountLink();
    const showCount = useShowCartCount();
    const links = storefront?.navigation?.header.links ?? [];
    const number = (key: string, fallback: number, min: number, max: number) => Math.max(min, Math.min(max, Number(text(key)) || fallback));
    const change = (key: string, value: string) => {
        const target = new URL(page.url, window.location.origin);
        target.searchParams.set(key, value);
        router.visit(target.pathname + target.search, { preserveScroll: true });
    };
    const search = <form className="tp-outlet-search" role="search" action="/search" method="get">
        <input name="q" type="search" aria-label="Search for items or brands" placeholder={text('search_placeholder')} />
        {storefront?.locale && <input type="hidden" name="lang" value={storefront.locale} />}
        {storefront?.displayCurrency && <input type="hidden" name="currency" value={storefront.displayCurrency} />}
        <button type="submit" aria-label="Search"><Search size={18} /></button>
    </form>;
    const region = <>
        {(storefront?.currencies?.length ?? 0) > 1 ? <select aria-label="Currency" disabled={!storefront?.canSwitchCurrency} value={storefront?.displayCurrency} onChange={e => change('currency', e.target.value)}>{storefront?.currencies?.map(c => <option key={c.code} value={c.code}>{c.code}</option>)}</select> : <span>{storefront?.displayCurrency ?? storefront?.currency}</span>}
        {(storefront?.locales.length ?? 0) > 1 && <select aria-label="Language" value={storefront?.locale} onChange={e => change('lang', e.target.value)}>{storefront?.locales.map(l => <option key={l.code} value={l.code}>{l.name}</option>)}</select>}
    </>;
    const bag = <Link href="/cart" className="tp-bag" aria-label={`Shopping bag, ${storefront?.cartCount ?? 0} items`}><CartIcon size={18} /><span>Bag{showCount && ` (${storefront?.cartCount ?? 0})`}</span></Link>;
    return <header className="tp-outlet-header">
        <style>{`[data-theme="ecomodular/tempo"]{--tp-width:${number('page_width',1280,960,1800)}px;--tp-gap:${number('section_gap',26,12,80)}px;--tp-hero-height:${number('hero_height',360,280,800)}px;--tp-hero-mobile:${number('hero_mobile_height',300,260,650)}px;--tp-hero-title:${number('hero_title_size',56,28,90)}px;--tp-hero-title-mobile:${number('hero_mobile_title_size',32,24,56)}px;--tp-overlay:${number('hero_overlay',15,0,70)/100};--tp-nav-bg:${text('nav_background','#050505')};--tp-nav-ink:${text('nav_foreground','#ffffff')};--tp-feature-bg:${text('feature_background','#ef1321')};--tp-feature-ink:${text('feature_foreground','#ffffff')};--tp-campaign-bg:${text('campaign_background','#600e14')};--tp-campaign-ink:${text('campaign_foreground','#f3e8c3')};--tp-columns:${number('catalog_columns',4,3,6)};--tp-footer-bg:${text('footer_background','#eeeeee')};--tp-footer-ink:${text('footer_foreground','#333333')}}`}</style>
        {storefront?.theme.announcement && <div className="tp-announcement">{storefront.theme.announcement}</div>}
        <div className="tp-outlet-top"><div className="tp-header-inner">
            <button type="button" className="tp-mobile-menu" aria-label="Open menu" aria-haspopup="dialog" onClick={() => menu.current?.showModal()}><Menu size={20} /></button>
            <Link href="/" className="tp-logo" aria-label="Home">{text('logo_image') || storefront?.theme.logo ? <img src={text('logo_image') || storefront?.theme.logo || ''} alt={text('brand','Tempo')} /> : text('brand','Tempo')}</Link>
            {search}<SearchIconLink />
            <div className="tp-outlet-utility">{region}</div>
            {/* Phones: search opens the menu's search box; account and bag stay one tap away. */}
            <div className="tp-mobile-actions">
                <button type="button" aria-label="Search" aria-haspopup="dialog" onClick={() => { menu.current?.showModal(); menu.current?.querySelector<HTMLInputElement>('input[name="q"]')?.focus(); }}><Search size={20} /></button>
                {showAccount && <Link href={auth.user ? '/account' : '/login'} aria-label={auth.user ? 'My account' : 'Sign in'}><User size={20} /></Link>}
                {bag}
            </div>
        </div></div>
        <div className="tp-outlet-nav"><div className="tp-header-inner">
            <nav className="tp-outlet-links" aria-label="Main navigation">{links.map((item,i) => <Link href={item.url} key={i}>{item.label}</Link>)}{text('featured_label') && <Link className="tp-outlet-feature" href={text('featured_link','/search')}>{text('featured_label')}</Link>}</nav>
            <div className="tp-icons"><PlaceSlot place="header.actions" />{showAccount && <Link href={auth.user ? '/account' : '/login'} aria-label={auth.user ? 'My account' : 'Sign in'}><User size={18} /></Link>}<CustomerNotificationBell />{bag}</div>
        </div></div>
        {show('show_benefits') && <div className="tp-outlet-benefits">{text('benefits')}</div>}
        <dialog ref={menu} className="tp-menu-dialog" aria-label="Store menu" onClick={e => { if (e.target === menu.current) menu.current?.close(); }}>
            <div className="tp-menu-title"><strong>{text('brand','Tempo')}</strong><button type="button" aria-label="Close menu" onClick={() => menu.current?.close()}><X size={22} /></button></div>
            {search}
            <nav aria-label="Mobile navigation">{links.map((item,i) => <Link href={item.url} key={i} onClick={() => menu.current?.close()}>{item.label}</Link>)}<Link href="/cart" onClick={() => menu.current?.close()}>Shopping bag</Link>{showAccount && <Link href={auth.user ? '/account' : '/login'} onClick={() => menu.current?.close()}>{auth.user ? 'My account' : 'Sign in'}</Link>}</nav>
            <div className="tp-region">{region}</div>
        </dialog>
    </header>;
}
