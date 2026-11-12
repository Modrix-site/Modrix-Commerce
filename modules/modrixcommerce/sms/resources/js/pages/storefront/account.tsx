import { Head, Link, router, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import {
    AccountLayout,
    AccountSection,
} from '@/components/storefront/account-nav';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useT } from '@/lib/i18n';

type Props = {
    phone: string | null;
    pending: string | null;
    orderUpdates: boolean;
    available: boolean;
    urls: { change: string; preferences: string; verify: string };
};

export default function SmsAccount({
    phone,
    pending,
    orderUpdates,
    available,
    urls,
}: Props) {
    const t = useT();
    const change = useForm({ phone: '' });

    return (
        <>
            <Head title={t('Mobile & text updates')} />
            <AccountLayout
                title={t('Mobile & text updates')}
                description={t('The number we text your order updates to.')}
            >
                <AccountSection
                    title={t('Mobile number')}
                    action={
                        phone ? (
                            <Badge variant="secondary">{t('Confirmed')}</Badge>
                        ) : undefined
                    }
                >
                    <p className="text-sm">
                        {phone ?? t('No confirmed number yet.')}
                    </p>
                    {pending && (
                        <p className="text-sm text-muted-foreground">
                            {t(':phone is waiting for its code.', {
                                phone: pending,
                            })}{' '}
                            <Link
                                href={urls.verify}
                                className="underline underline-offset-4"
                            >
                                {t('Enter the code')}
                            </Link>
                        </p>
                    )}
                    {available ? (
                        <form
                            className="grid gap-2 sm:max-w-md"
                            onSubmit={(e) => {
                                e.preventDefault();
                                change.post(urls.change, {
                                    preserveScroll: true,
                                });
                            }}
                        >
                            <Label htmlFor="phone">
                                {phone ? t('Change number') : t('Add a number')}
                            </Label>
                            <div className="flex gap-2">
                                <Input
                                    id="phone"
                                    type="tel"
                                    inputMode="tel"
                                    autoComplete="tel"
                                    maxLength={30}
                                    value={change.data.phone}
                                    onChange={(e) =>
                                        change.setData('phone', e.target.value)
                                    }
                                    required
                                />
                                <Button
                                    type="submit"
                                    disabled={change.processing}
                                >
                                    {t('Send code')}
                                </Button>
                            </div>
                            <InputError message={change.errors.phone} />
                            <p className="text-xs text-muted-foreground">
                                {t(
                                    'Your current number stays in use until the new one is confirmed.',
                                )}
                            </p>
                        </form>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            {t(
                                'Text messages are not available in this store right now.',
                            )}
                        </p>
                    )}
                </AccountSection>

                <AccountSection
                    title={t('Order updates by text')}
                    description={t(
                        'Order confirmation, shipping, delivery and cancellation messages.',
                    )}
                >
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={orderUpdates}
                            disabled={!phone}
                            onCheckedChange={(checked) =>
                                router.put(
                                    urls.preferences,
                                    { order_updates: checked === true },
                                    { preserveScroll: true },
                                )
                            }
                        />
                        {t('Text me when my orders change')}
                    </label>
                </AccountSection>
            </AccountLayout>
        </>
    );
}
