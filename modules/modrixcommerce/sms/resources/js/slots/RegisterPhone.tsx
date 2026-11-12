import { usePage } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useT } from '@/lib/i18n';

/**
 * Mobile number inside the registration form. Submitted with the form as
 * "phone" and "sms_updates"; the server texts a code to confirm it.
 */
export default function RegisterPhone({
    defaultCountryCode,
}: {
    defaultCountryCode: string;
}) {
    const t = useT();
    const errors = (usePage().props.errors ?? {}) as Record<string, string>;

    return (
        <div className="grid gap-3">
            <div className="grid gap-2">
                <Label htmlFor="phone">{t('Mobile number')}</Label>
                <Input
                    id="phone"
                    name="phone"
                    type="tel"
                    inputMode="tel"
                    required
                    autoComplete="tel"
                    maxLength={30}
                    placeholder={`+${defaultCountryCode} …`}
                    aria-describedby="phone-hint"
                />
                <p id="phone-hint" className="text-xs text-muted-foreground">
                    {t(
                        'We text a code to confirm it. Numbers without a + are treated as +:code.',
                        { code: defaultCountryCode },
                    )}
                </p>
                <InputError message={errors.phone} />
            </div>
            <label className="flex items-start gap-2 text-sm">
                <Checkbox
                    name="sms_updates"
                    value="1"
                    defaultChecked
                    className="mt-0.5"
                />
                <span>{t('Text me updates about my orders')}</span>
            </label>
        </div>
    );
}
