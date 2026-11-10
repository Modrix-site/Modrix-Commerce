import { Head, Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import {
    Bell,
    ChevronRight,
    Download,
    MapPin,
    Package,
    ShieldCheck,
    UserRound,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { StatusBadge } from '@/components/admin/status-badge';
import {
    AccountEmpty,
    AccountLayout,
    AccountSection,
} from '@/components/storefront/account-nav';
import { formatDate } from '@/components/storefront/order-view';
import { Button } from '@/components/ui/button';
import { useT } from '@/lib/i18n';
import { home } from '@/routes';
import {
    addresses,
    dataExport,
    notifications,
    orders,
    profile,
    security,
} from '@/routes/account';
import { show as orderShow } from '@/routes/account/orders';
import type { Address, OrderSummary } from '@/types';

type Href = NonNullable<InertiaLinkProps['href']>;

type Props = {
    customer: { name: string; email: string; member_since?: string | null };
    defaultAddress: Address | null;
    stats?: { orders: number; unread_notifications: number; addresses: number };
    recentOrders?: OrderSummary[];
};

function Stat({
    href,
    icon: Icon,
    value,
    label,
}: {
    href: Href;
    icon: LucideIcon;
    value: number;
    label: string;
}) {
    return (
        <Link
            href={href}
            className="group grid gap-3 rounded-xl border bg-card p-4 transition-colors hover:border-foreground/30 sm:p-5"
        >
            <span className="grid size-9 place-items-center rounded-full bg-muted">
                <Icon className="size-4" aria-hidden />
            </span>
            <span className="grid">
                <span className="text-2xl font-semibold tabular-nums">
                    {value}
                </span>
                <span className="text-xs text-muted-foreground sm:text-sm">
                    {label}
                </span>
            </span>
        </Link>
    );
}

function Shortcut({
    href,
    icon: Icon,
    title,
    description,
}: {
    href: Href;
    icon: LucideIcon;
    title: string;
    description: string;
}) {
    return (
        <Link
            href={href}
            className="flex items-center gap-3 rounded-lg p-2 -mx-2 transition-colors hover:bg-muted"
        >
            <span className="grid size-9 shrink-0 place-items-center rounded-full bg-muted">
                <Icon className="size-4" aria-hidden />
            </span>
            <span className="grid min-w-0 flex-1">
                <span className="text-sm font-medium">{title}</span>
                <span className="truncate text-xs text-muted-foreground">
                    {description}
                </span>
            </span>
            <ChevronRight
                className="size-4 shrink-0 text-muted-foreground"
                aria-hidden
            />
        </Link>
    );
}

export default function AccountIndex({
    customer,
    defaultAddress,
    stats,
    recentOrders = [],
}: Props) {
    const t = useT();
    const firstName = customer.name.split(' ')[0] || customer.name;
    const memberSince = customer.member_since
        ? new Date(customer.member_since).toLocaleDateString(undefined, {
              month: 'long',
              year: 'numeric',
          })
        : null;

    return (
        <>
            <Head title={t('My account')} />
            <AccountLayout
                title={t('Hello, :name', { name: firstName })}
                description={
                    memberSince
                        ? t('Member since :date', { date: memberSince })
                        : customer.email
                }
            >
                {stats && (
                    <div className="grid grid-cols-3 gap-3">
                        <Stat
                            href={orders()}
                            icon={Package}
                            value={stats.orders}
                            label={t('Orders')}
                        />
                        <Stat
                            href={notifications()}
                            icon={Bell}
                            value={stats.unread_notifications}
                            label={t('Unread updates')}
                        />
                        <Stat
                            href={addresses()}
                            icon={MapPin}
                            value={stats.addresses}
                            label={t('Addresses')}
                        />
                    </div>
                )}

                <AccountSection
                    title={t('Recent orders')}
                    action={
                        recentOrders.length > 0 && (
                            <Button variant="ghost" size="sm" asChild>
                                <Link href={orders()}>
                                    {t('View all')}
                                    <ChevronRight aria-hidden />
                                </Link>
                            </Button>
                        )
                    }
                >
                    {recentOrders.length === 0 ? (
                        <AccountEmpty
                            icon={Package}
                            title={t('No orders yet')}
                            description={t(
                                'When you place an order, you can follow it here.',
                            )}
                            action={
                                <Button asChild>
                                    <Link href={home()}>
                                        {t('Start shopping')}
                                    </Link>
                                </Button>
                            }
                        />
                    ) : (
                        <ul className="-mx-2 grid">
                            {recentOrders.map((order) => (
                                <li key={order.number}>
                                    <Link
                                        href={orderShow(order.number)}
                                        className="flex items-center gap-3 rounded-lg p-2 transition-colors hover:bg-muted"
                                    >
                                        <span className="grid size-10 shrink-0 place-items-center rounded-lg bg-muted">
                                            <Package
                                                className="size-4"
                                                aria-hidden
                                            />
                                        </span>
                                        <span className="grid min-w-0 flex-1">
                                            <span className="truncate text-sm font-medium">
                                                {t('Order :number', {
                                                    number: order.number,
                                                })}
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                {formatDate(order.placed_at)}
                                            </span>
                                        </span>
                                        <span className="grid justify-items-end gap-1">
                                            <span className="text-sm font-medium tabular-nums">
                                                {order.grand_total.formatted}
                                            </span>
                                            <StatusBadge
                                                status={order.status}
                                            />
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </AccountSection>

                <div className="grid gap-6 md:grid-cols-2">
                    <AccountSection
                        title={t('Default shipping address')}
                        action={
                            <Button variant="outline" size="sm" asChild>
                                <Link href={addresses()}>
                                    {defaultAddress
                                        ? t('Manage')
                                        : t('Add address')}
                                </Link>
                            </Button>
                        }
                    >
                        {defaultAddress ? (
                            <address className="text-sm leading-relaxed text-muted-foreground not-italic">
                                <span className="font-medium text-foreground">
                                    {defaultAddress.first_name}{' '}
                                    {defaultAddress.last_name}
                                </span>
                                <br />
                                {defaultAddress.line1}
                                {defaultAddress.line2 &&
                                    `, ${defaultAddress.line2}`}
                                <br />
                                {defaultAddress.city}{' '}
                                {defaultAddress.postal_code}
                                <br />
                                {defaultAddress.country_name}
                            </address>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                {t(
                                    'Save an address to check out faster next time.',
                                )}
                            </p>
                        )}
                    </AccountSection>

                    <AccountSection title={t('Account settings')}>
                        <div className="grid gap-1">
                            <Shortcut
                                href={profile()}
                                icon={UserRound}
                                title={t('Profile')}
                                description={customer.email}
                            />
                            <Shortcut
                                href={security()}
                                icon={ShieldCheck}
                                title={t('Password & security')}
                                description={t(
                                    'Change your password and sign-in options',
                                )}
                            />
                        </div>
                    </AccountSection>
                </div>

                <AccountSection
                    title={t('Your data')}
                    description={t(
                        'Download your account details, addresses, orders and subscriptions as a JSON file.',
                    )}
                    action={
                        <Button variant="outline" size="sm" asChild>
                            <a href={dataExport().url} download>
                                <Download aria-hidden />
                                {t('Download my data')}
                            </a>
                        </Button>
                    }
                />
            </AccountLayout>
        </>
    );
}
