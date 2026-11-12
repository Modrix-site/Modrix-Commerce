import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import ExtensionBlocksController from '@/actions/App/Http/Controllers/Admin/ExtensionBlocksController';
import { NativeSelect } from '@/components/admin/form-field';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type Value = string | number | boolean | null;

export type ExtensionSettingField = {
    key: string;
    type: 'boolean' | 'string' | 'text' | 'integer' | 'select' | 'email';
    label: string;
    help?: string;
    min?: number;
    max?: number;
    options?: { value: string; label: string }[];
};

export type ExtensionSettingsGroup = {
    module: string;
    title: string;
    fields: ExtensionSettingField[];
    values: Record<string, Value>;
};

/** "Extension settings": module settings that belong to this tab's page. */
export function ExtensionSettingsCard({
    groups,
}: {
    groups: ExtensionSettingsGroup[] | undefined;
}) {
    if (!groups || groups.length === 0) {
        return null;
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>Extension settings</CardTitle>
                <CardDescription>
                    Settings of your extensions for this page. They are the same
                    as on each extension's settings page.
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-6">
                {groups.map((group) => (
                    <ModuleSettings key={group.module} group={group} />
                ))}
            </CardContent>
        </Card>
    );
}

function ModuleSettings({ group }: { group: ExtensionSettingsGroup }) {
    const form = useForm<{ settings: Record<string, Value> }>({
        settings: group.values,
    });
    const set = (key: string, value: Value) =>
        form.setData('settings', { ...form.data.settings, [key]: value });
    const errors = form.errors as Record<string, string>;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(ExtensionBlocksController.settings.url(group.module), {
            preserveScroll: true,
        });
    };

    return (
        <form onSubmit={submit} className="grid gap-4">
            <fieldset className="grid gap-4">
                <legend className="mb-1 text-sm font-semibold">
                    {group.title}
                </legend>
                {group.fields.map((field) => {
                    const id = `${group.module}-${field.key}`;
                    const value = form.data.settings[field.key];

                    return (
                        <div key={field.key} className="grid gap-1.5">
                            {field.type === 'boolean' ? (
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id={id}
                                        checked={value === true}
                                        onCheckedChange={(checked) =>
                                            set(field.key, checked === true)
                                        }
                                    />
                                    <Label htmlFor={id}>{field.label}</Label>
                                </div>
                            ) : (
                                <>
                                    <Label htmlFor={id}>{field.label}</Label>
                                    {field.type === 'select' ? (
                                        <NativeSelect
                                            id={id}
                                            value={String(value ?? '')}
                                            onChange={(e) =>
                                                set(field.key, e.target.value)
                                            }
                                        >
                                            {field.options?.map((option) => (
                                                <option
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                    ) : field.type === 'text' ? (
                                        <Textarea
                                            id={id}
                                            rows={3}
                                            value={String(value ?? '')}
                                            onChange={(e) =>
                                                set(field.key, e.target.value)
                                            }
                                        />
                                    ) : (
                                        <Input
                                            id={id}
                                            type={
                                                field.type === 'integer'
                                                    ? 'number'
                                                    : field.type === 'email'
                                                      ? 'email'
                                                      : 'text'
                                            }
                                            min={field.min}
                                            max={field.max}
                                            value={String(value ?? '')}
                                            onChange={(e) =>
                                                set(
                                                    field.key,
                                                    field.type === 'integer' &&
                                                        e.target.value !== ''
                                                        ? Number(e.target.value)
                                                        : e.target.value,
                                                )
                                            }
                                        />
                                    )}
                                </>
                            )}
                            {field.help && (
                                <p className="text-xs text-muted-foreground">
                                    {field.help}
                                </p>
                            )}
                            <InputError message={errors[field.key]} />
                        </div>
                    );
                })}
            </fieldset>
            <InputError message={errors.settings} />
            <div>
                <Button
                    type="submit"
                    variant="outline"
                    disabled={form.processing}
                >
                    Save {group.title} settings
                </Button>
            </div>
        </form>
    );
}
