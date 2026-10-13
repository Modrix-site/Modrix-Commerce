import { Head, Link } from '@inertiajs/react';
import { AccountNav } from '@/components/storefront/account-nav';
import { Button } from '@/components/ui/button';
import { useT } from '@/lib/i18n';
import { addresses, dataExport } from '@/routes/account';
import type { Address } from '@/types';

type Props = {
    customer: { name: string; email: string };
    defaultAddress: Address | null;
};

export default function AccountIndex({ customer, defaultAddress }: Props) {
    const t = useT();

    return (
        <>
            <Head title={t('My account')} />
            <div className="grid gap-8 md:grid-cols-[12rem_1fr]">
                <AccountNav />
                <div className="grid content-start gap-6">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {t('Hello, :name', { name: customer.name })}
                        </h1>
                        <p className="text-muted-foreground">
                            {customer.email}
                        </p>
                    </div>
                    <section className="grid gap-2 rounded-lg border p-5">
                        <h2 className="font-semibold">
                            {t('Default shipping address')}
                        </h2>
                        {defaultAddress ? (
                            <address className="text-sm text-muted-foreground not-italic">
                                {defaultAddress.first_name}{' '}
                                {defaultAddress.last_name}
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
                                {t('No address saved yet.')}
                            </p>
                        )}
                        <div>
                            <Button variant="outline" size="sm" asChild>
                                <Link href={addresses()}>
                                    {t('Manage addresses')}
                                </Link>
                            </Button>
                        </div>
                    </section>
                    <section className="grid gap-2 rounded-lg border p-5">
                        <h2 className="font-semibold">{t('Your data')}</h2>
                        <p className="text-sm text-muted-foreground">
                            {t(
                                'Download your account details, addresses, orders and subscriptions as a JSON file.',
                            )}
                        </p>
                        <div>
                            <Button variant="outline" size="sm" asChild>
                                <a href={dataExport().url} download>
                                    {t('Download my data')}
                                </a>
                            </Button>
                        </div>
                    </section>
                </div>
            </div>
        </>
    );
}
