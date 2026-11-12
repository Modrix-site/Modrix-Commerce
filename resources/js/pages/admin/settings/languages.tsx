import { Form, Head, Link } from '@inertiajs/react';
import { Input } from '@/components/ui/input';
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

type Phrase = { group: string; text: string };

type Props = {
    available: { code: string; name: string }[];
    default: string;
    additional: string[];
    mode: 'automatic' | 'manual';
    phrases: Phrase[];
    translations: Record<string, Record<string, string>>;
};

export default function LanguageSettings({
    available,
    default: defaultLocale,
    additional,
    mode: initialMode,
    phrases,
    translations,
}: Props) {
    const [search, setSearch] = useState('');
    const [base, setBase] = useState(defaultLocale);
    const [extra, setExtra] = useState<string[]>(additional);
    const [mode, setMode] = useState(initialMode);
    const [copy, setCopy] = useState(translations);
    const [phraseLocale, setPhraseLocale] = useState(
        additional.find((code) => code !== defaultLocale) ?? '',
    );

    const toggle = (code: string, on: boolean) =>
        setExtra((current) =>
            on ? [...current, code] : current.filter((c) => c !== code),
        );
    const phraseLanguages = available.filter(
        (language) => extra.includes(language.code) && language.code !== base,
    );
    const selectedPhrase =
        phraseLanguages.find((language) => language.code === phraseLocale)
            ?.code ??
        phraseLanguages[0]?.code ??
        '';

    return (
        <>
            <Head title="Languages" />
            <AdminPage>
                <PageHeader
                    title="Languages"
                    description="Products and categories are written in the default language. Shoppers can switch to any additional language. Homepage, footer and theme text can follow that language automatically, or you can write those translations yourself."
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
                            transform={(data) => ({
                                ...data,
                                mode,
                                translations:
                                    mode === 'manual'
                                        ? Object.entries(copy).flatMap(
                                              ([locale, rows]) =>
                                                  Object.entries(rows).map(
                                                      ([source, value]) => ({
                                                          locale,
                                                          source,
                                                          value,
                                                      }),
                                                  ),
                                          )
                                        : [],
                            })}
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
                                        <Input
                                            aria-label="Search languages"
                                            placeholder="Search languages by name or code..."
                                            value={search}
                                            onChange={(e) =>
                                                setSearch(e.target.value)
                                            }
                                        />
                                        <div className="grid max-h-96 gap-2 overflow-auto sm:grid-cols-3">
                                            {available
                                                .filter(
                                                    (l) =>
                                                        l.code !== base &&
                                                        `${l.name} ${l.code}`
                                                            .toLowerCase()
                                                            .includes(
                                                                search.toLowerCase(),
                                                            ),
                                                )
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
                                        <div className="flex flex-wrap gap-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() =>
                                                    setExtra(
                                                        available
                                                            .filter(
                                                                (l) =>
                                                                    l.code !==
                                                                    base,
                                                            )
                                                            .map((l) => l.code),
                                                    )
                                                }
                                            >
                                                Select all languages
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                onClick={() => setExtra([])}
                                            >
                                                Clear additional languages
                                            </Button>
                                        </div>
                                        <InputError
                                            message={errors.additional}
                                        />
                                    </fieldset>
                                    <fieldset className="grid gap-3">
                                        <legend className="text-sm font-medium">
                                            Homepage, footer and theme text
                                        </legend>
                                        <p className="text-sm text-muted-foreground">
                                            This applies to every theme. Choose
                                            whether that text is translated
                                            automatically when a shopper picks a
                                            language, or you write the
                                            translations.
                                        </p>
                                        <label className="flex items-start gap-2 text-sm">
                                            <input
                                                type="radio"
                                                name="mode"
                                                value="automatic"
                                                checked={mode === 'automatic'}
                                                onChange={() =>
                                                    setMode('automatic')
                                                }
                                            />
                                            <span>
                                                Translate automatically when a
                                                shopper selects a language
                                            </span>
                                        </label>
                                        <label className="flex items-start gap-2 text-sm">
                                            <input
                                                type="radio"
                                                name="mode"
                                                value="manual"
                                                checked={mode === 'manual'}
                                                onChange={() =>
                                                    setMode('manual')
                                                }
                                            />
                                            <span>
                                                I will translate the homepage,
                                                footer and theme text
                                            </span>
                                        </label>
                                        <InputError message={errors.mode} />
                                        {mode === 'manual' &&
                                            phraseLanguages.length > 0 && (
                                                <div className="grid gap-3 rounded-lg border p-3">
                                                    <FormField
                                                        id="phrase-locale"
                                                        label="Language to translate"
                                                    >
                                                        <NativeSelect
                                                            id="phrase-locale"
                                                            value={
                                                                selectedPhrase
                                                            }
                                                            onChange={(e) =>
                                                                setPhraseLocale(
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            className="sm:max-w-xs"
                                                        >
                                                            {phraseLanguages.map(
                                                                (language) => (
                                                                    <option
                                                                        key={
                                                                            language.code
                                                                        }
                                                                        value={
                                                                            language.code
                                                                        }
                                                                    >
                                                                        {
                                                                            language.name
                                                                        }
                                                                    </option>
                                                                ),
                                                            )}
                                                        </NativeSelect>
                                                    </FormField>
                                                    {phrases.length === 0 ? (
                                                        <p className="text-sm text-muted-foreground">
                                                            The active theme has
                                                            no homepage or
                                                            footer text to
                                                            translate yet.
                                                        </p>
                                                    ) : (
                                                        phrases.map(
                                                            (phrase, index) => (
                                                                <FormField
                                                                    key={`${selectedPhrase}:${phrase.text}`}
                                                                    id={`phrase-${index}`}
                                                                    label={`${phrase.group}: ${phrase.text}`}
                                                                >
                                                                    <Input
                                                                        value={
                                                                            copy[
                                                                                selectedPhrase
                                                                            ]?.[
                                                                                phrase
                                                                                    .text
                                                                            ] ??
                                                                            ''
                                                                        }
                                                                        onChange={(
                                                                            e,
                                                                        ) =>
                                                                            setCopy(
                                                                                (
                                                                                    current,
                                                                                ) => ({
                                                                                    ...current,
                                                                                    [selectedPhrase]:
                                                                                        {
                                                                                            ...current[
                                                                                                selectedPhrase
                                                                                            ],
                                                                                            [phrase.text]:
                                                                                                e
                                                                                                    .target
                                                                                                    .value,
                                                                                        },
                                                                                }),
                                                                            )
                                                                        }
                                                                    />
                                                                </FormField>
                                                            ),
                                                        )
                                                    )}
                                                </div>
                                            )}
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
