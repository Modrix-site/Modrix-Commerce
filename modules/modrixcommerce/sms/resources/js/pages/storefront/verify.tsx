import { Head, Link, useForm } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { MessageSquare } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useT } from '@/lib/i18n';

type Props = {
    phone: string;
    changing: boolean;
    urls: { confirm: string; resend: string; change: string; logout: string };
};

export default function VerifyPhone({ phone, changing, urls }: Props) {
    const t = useT();
    const confirm = useForm({ code: '' });
    const resend = useForm({});
    const change = useForm({ phone: '' });
    const [editing, setEditing] = useState(false);

    return (
        <>
            <Head title={t('Confirm your mobile number')} />
            <div className="mx-auto w-full max-w-md py-6 sm:py-12">
                <div className="grid gap-6 rounded-xl border bg-card p-6 shadow-xs sm:p-8">
                    <div className="grid justify-items-center gap-2 text-center">
                        <span className="grid size-11 place-items-center rounded-full bg-muted">
                            <MessageSquare className="size-5" aria-hidden />
                        </span>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {t('Confirm your mobile number')}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {t('Enter the 6-digit code we texted to :phone.', {
                                phone,
                            })}
                        </p>
                    </div>

                    <form
                        className="grid justify-items-center gap-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            confirm.post(urls.confirm, {
                                preserveScroll: true,
                                onError: () => confirm.reset('code'),
                            });
                        }}
                    >
                        <InputOTP
                            name="code"
                            maxLength={6}
                            value={confirm.data.code}
                            onChange={(value) => confirm.setData('code', value)}
                            pattern={REGEXP_ONLY_DIGITS}
                            autoComplete="one-time-code"
                            disabled={confirm.processing}
                            autoFocus
                        >
                            <InputOTPGroup>
                                {Array.from({ length: 6 }, (_, index) => (
                                    <InputOTPSlot key={index} index={index} />
                                ))}
                            </InputOTPGroup>
                        </InputOTP>
                        <InputError message={confirm.errors.code} />
                        <Button
                            type="submit"
                            className="w-full"
                            disabled={
                                confirm.processing ||
                                confirm.data.code.length !== 6
                            }
                        >
                            {confirm.processing && <Spinner />}
                            {t('Confirm')}
                        </Button>
                    </form>

                    <div className="flex flex-wrap items-center justify-center gap-x-4 gap-y-2 text-sm">
                        <button
                            type="button"
                            className="underline underline-offset-4 disabled:opacity-50"
                            disabled={resend.processing}
                            onClick={() =>
                                resend.post(urls.resend, {
                                    preserveScroll: true,
                                    onError: (errors) =>
                                        confirm.setError(
                                            'code',
                                            errors.code ?? '',
                                        ),
                                })
                            }
                        >
                            {t('Send a new code')}
                        </button>
                        <button
                            type="button"
                            className="underline underline-offset-4"
                            onClick={() => setEditing((v) => !v)}
                        >
                            {t('Use a different number')}
                        </button>
                    </div>

                    {editing && (
                        <form
                            className="grid gap-2"
                            onSubmit={(e) => {
                                e.preventDefault();
                                change.post(urls.change, {
                                    preserveScroll: true,
                                    onSuccess: () => {
                                        setEditing(false);
                                        change.reset();
                                    },
                                });
                            }}
                        >
                            <Label htmlFor="new-phone">
                                {t('Mobile number')}
                            </Label>
                            <div className="flex gap-2">
                                <Input
                                    id="new-phone"
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
                                    variant="secondary"
                                    disabled={change.processing}
                                >
                                    {t('Send code')}
                                </Button>
                            </div>
                            <InputError message={change.errors.phone} />
                        </form>
                    )}

                    {!changing && (
                        <p className="text-center text-xs text-muted-foreground">
                            {t('Not now?')}{' '}
                            <Link
                                href={urls.logout}
                                method="post"
                                as="button"
                                className="underline underline-offset-4"
                            >
                                {t('Log out')}
                            </Link>
                        </p>
                    )}
                </div>
            </div>
        </>
    );
}
