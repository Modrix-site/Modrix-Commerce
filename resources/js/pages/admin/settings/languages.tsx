import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import LanguageSettingsController from '@/actions/App/Http/Controllers/Admin/LanguageSettingsController';
import { FormField, NativeSelect } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { index as interfaceText } from '@/routes/admin/settings/interface-text';
import { edit } from '@/routes/admin/settings/languages';

type Props = {
    available: { code: string; name: string }[];
    default: string;
    additional: string[];
};

export default function LanguageSettings({
    available,
    default: defaultLocale,
    additional,
}: Props) {
    const [base, setBase] = useState(defaultLocale);
    const [extra, setExtra] = useState<string[]>(additional);

    const toggle = (code: string, on: boolean) =>
        setExtra((current) =>
            on ? [...current, code] : current.filter((c) => c !== code),
        );

    return (
        <>
            <Head title="Languages" />
            <AdminPage>
                <PageHeader
                    title="Languages"
                    description="Products and categories are written in the default language. Shoppers can switch to any additional language; untranslated texts fall back to the default."
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={interfaceText()}>Storefront text</Link>
                        </Button>
                    }
                />
                <Card>
                    <CardContent>
                        <Form
                            {...LanguageSettingsController.update.form()}
                            options={{ preserveScroll: true }}
                            className="grid gap-6"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <FormField
                                        id="default"
                                        label="Default language"
                                        error={errors.default}
                                        hint="The language your product and category texts are written in."
                                    >
                                        <NativeSelect
                                            id="default"
                                            name="default"
                                            value={base}
                                            onChange={(e) =>
                                                setBase(e.target.value)
                                            }
                                            className="sm:max-w-xs"
                                        >
                                            {available.map((l) => (
                                                <option
                                                    key={l.code}
                                                    value={l.code}
                                                >
                                                    {l.name}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                    </FormField>
                                    <fieldset className="grid gap-2">
                                        <legend className="mb-2 text-sm font-medium">
                                            Additional languages
                                        </legend>
                                        <div className="grid gap-2 sm:grid-cols-3">
                                            {available
                                                .filter((l) => l.code !== base)
                                                .map((l) => (
                                                    <label
                                                        key={l.code}
                                                        className="flex items-center gap-2 text-sm"
                                                    >
                                                        <Checkbox
                                                            checked={extra.includes(
                                                                l.code,
                                                            )}
                                                            onCheckedChange={(
                                                                v,
                                                            ) =>
                                                                toggle(
                                                                    l.code,
                                                                    v === true,
                                                                )
                                                            }
                                                        />
                                                        {l.name}
                                                    </label>
                                                ))}
                                        </div>
                                        {extra
                                            .filter((c) => c !== base)
                                            .map((code) => (
                                                <input
                                                    key={code}
                                                    type="hidden"
                                                    name="additional[]"
                                                    value={code}
                                                />
                                            ))}
                                        <InputError
                                            message={errors.additional}
                                        />
                                    </fieldset>
                                    <div>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Save languages
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </AdminPage>
        </>
    );
}

LanguageSettings.layout = {
    breadcrumbs: [{ title: 'Languages', href: edit() }],
};
