import { navIcon } from '@/components/admin/nav-icon';
import { cn } from '@/lib/utils';

/**
 * The branded mark shown for a module: its declared icon on a tile tinted with
 * the module's brand colour. Falls back to a neutral puzzle on a muted tile
 * when a module declares no branding.
 */
export function ExtensionIcon({
    icon,
    accent,
    className,
    logo,
    name,
}: {
    name?: string;
    logo?: string | null;
    icon?: string | null;
    accent?: string | null;
    className?: string;
}) {
    const brandLogo =
        logo ||
        (name?.startsWith('ecomodular/tabby:')
            ? '/images/extensions/tabby.svg'
            : name?.startsWith('ecomodular/tamara:')
              ? '/images/extensions/tamara.svg'
              : null);
    const Icon = navIcon(icon || 'puzzle');
    const brand = accent && /^#[0-9a-fA-F]{6}$/.test(accent) ? accent : null;

    return (
        <span
            aria-hidden
            className={cn(
                'grid size-10 shrink-0 place-items-center rounded-lg',
                !brand && 'bg-muted text-muted-foreground',
                className,
            )}
            style={
                brandLogo
                    ? { backgroundColor: '#ffffff' }
                    : brand
                      ? { backgroundColor: `${brand}1f`, color: brand }
                      : undefined
            }
        >
            {brandLogo ? (
                <img
                    src={brandLogo}
                    alt=""
                    className="h-full w-full object-contain p-1.5"
                />
            ) : (
                <Icon className="size-5" />
            )}
        </span>
    );
}
