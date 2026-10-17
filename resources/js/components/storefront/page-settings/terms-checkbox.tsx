import InputError from '@/components/input-error';
import { useT } from '@/lib/i18n';

/** Required "I agree to the terms" box at checkout (sent as terms). */
export function TermsCheckbox({
    url,
    checked,
    onChange,
    error,
}: {
    url: string | null | undefined;
    checked: boolean;
    onChange: (checked: boolean) => void;
    error?: string;
}) {
    const t = useT();

    return (
        <div className="grid gap-1">
            <label className="flex items-start gap-2 text-sm">
                <input
                    type="checkbox"
                    checked={checked}
                    onChange={(event) => onChange(event.target.checked)}
                    required
                    aria-invalid={error ? true : undefined}
                    className="mt-0.5 size-4 accent-primary"
                />
                <span>
                    {t('I agree to the terms and conditions')}
                    {url && (
                        <>
                            {' '}
                            (
                            <a
                                href={url}
                                target="_blank"
                                rel="noreferrer"
                                className="underline"
                            >
                                {t('Terms and conditions')}
                            </a>
                            )
                        </>
                    )}
                </span>
            </label>
            <InputError message={error} />
        </div>
    );
}
