import {
    VariantSelector,
    type VariantOption,
} from '@/components/storefront/variant-selector';
import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import AttributeController from '@/actions/App/Http/Controllers/Admin/Catalog/AttributeController';
import { FormField } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { index } from '@/routes/admin/attributes';

type AttributeData = {
    id: number;
    name: string;
    display_type: VariantOption['display_type'];
    values: { id: number; value: string; color: string | null }[];
};

type FormData = {
    name: string;
    display_type: NonNullable<VariantOption['display_type']>;
    values: { id: number | null; value: string; color: string | null }[];
};

export default function AttributeForm({
    attribute,
}: {
    attribute: AttributeData | null;
}) {
    const [previewValue, setPreviewValue] = useState<number>(1);
    const form = useForm<FormData>({
        name: attribute?.name ?? '',
        display_type: attribute?.display_type ?? 'buttons',
        values: attribute?.values ?? [
            { id: null, value: '', color: '#000000' },
        ],
    });
    const errors = form.errors as Record<string, string | undefined>;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(
            attribute
                ? AttributeController.update(attribute.id)
                : AttributeController.store(),
        );
    };

    const remove = () => {
        if (
            attribute &&
            window.confirm(`Delete the attribute “${attribute.name}”?`)
        ) {
            form.submit(AttributeController.destroy(attribute.id));
        }
    };

    return (
        <>
            <Head
                title={attribute ? `Edit ${attribute.name}` : 'Add attribute'}
            />
            <AdminPage>
                <PageHeader
                    title={attribute ? attribute.name : 'Add attribute'}
                    actions={
                        attribute && (
                            <Button variant="destructive" onClick={remove}>
                                Delete
                            </Button>
                        )
                    }
                />
                <InputError message={errors.attribute} />
                <form onSubmit={submit} className="grid gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Attribute</CardTitle>
                            <CardDescription>
                                Values that are used by product variants cannot
                                be removed.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <FormField
                                id="name"
                                label="Name"
                                error={errors.name}
                            >
                                <Input
                                    id="name"
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData('name', e.target.value)
                                    }
                                    placeholder="e.g. Size"
                                    required
                                />
                            </FormField>
                            <FormField
                                id="display_type"
                                label="Storefront selection style"
                                error={errors.display_type}
                            >
                                <select
                                    id="display_type"
                                    className="h-10 rounded-md border bg-background px-3 text-sm"
                                    value={form.data.display_type}
                                    onChange={(event) => {
                                        const display_type = event.target
                                            .value as FormData['display_type'];
                                        form.setData((current) => ({
                                            ...current,
                                            display_type,
                                            values: current.values.map(
                                                (row) => ({
                                                    ...row,
                                                    color:
                                                        display_type ===
                                                        'swatches'
                                                            ? row.color ||
                                                              '#000000'
                                                            : row.color,
                                                }),
                                            ),
                                        }));
                                    }}
                                >
                                    <option value="buttons">
                                        Text buttons / pills
                                    </option>
                                    <option value="circles">
                                        Circular choices
                                    </option>
                                    <option value="swatches">
                                        Color swatches
                                    </option>
                                    <option value="dropdown">Dropdown</option>
                                    <option value="radio">Radio list</option>
                                </select>
                            </FormField>
                            <div className="grid gap-2">
                                <span className="text-sm font-medium">
                                    Values
                                </span>
                                {form.data.values.map((value, i) => (
                                    <div
                                        key={value.id ?? `new-${i}`}
                                        className="flex flex-wrap items-start gap-2"
                                    >
                                        <Input
                                            aria-label={`Value ${i + 1}`}
                                            value={value.value}
                                            onChange={(e) =>
                                                form.setData(
                                                    'values',
                                                    form.data.values.map(
                                                        (v, j) =>
                                                            j === i
                                                                ? {
                                                                      ...v,
                                                                      value: e
                                                                          .target
                                                                          .value,
                                                                  }
                                                                : v,
                                                    ),
                                                )
                                            }
                                            required
                                        />
                                        {form.data.display_type ===
                                            'swatches' && (
                                            <div className="flex items-start gap-2">
                                                <input
                                                    type="color"
                                                    aria-label={`Color for value ${i + 1}`}
                                                    value={
                                                        /^#[0-9a-fA-F]{6}$/.test(
                                                            value.color ?? '',
                                                        )
                                                            ? value.color!
                                                            : '#000000'
                                                    }
                                                    onChange={(event) =>
                                                        form.setData(
                                                            'values',
                                                            form.data.values.map(
                                                                (row, j) =>
                                                                    j === i
                                                                        ? {
                                                                              ...row,
                                                                              color: event
                                                                                  .target
                                                                                  .value,
                                                                          }
                                                                        : row,
                                                            ),
                                                        )
                                                    }
                                                    className="h-9 w-12 cursor-pointer rounded border"
                                                />
                                                <Input
                                                    aria-label={`Hex color for value ${i + 1}`}
                                                    placeholder="#000000"
                                                    value={value.color ?? ''}
                                                    maxLength={7}
                                                    className="w-28"
                                                    onChange={(event) =>
                                                        form.setData(
                                                            'values',
                                                            form.data.values.map(
                                                                (row, j) =>
                                                                    j === i
                                                                        ? {
                                                                              ...row,
                                                                              color: event
                                                                                  .target
                                                                                  .value,
                                                                          }
                                                                        : row,
                                                            ),
                                                        )
                                                    }
                                                />
                                            </div>
                                        )}
                                        <InputError
                                            message={
                                                errors[`values.${i}.value`] ??
                                                errors[`values.${i}.color`]
                                            }
                                        />
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            aria-label={`Remove value ${i + 1}`}
                                            disabled={
                                                form.data.values.length === 1
                                            }
                                            onClick={() =>
                                                form.setData(
                                                    'values',
                                                    form.data.values.filter(
                                                        (_, j) => j !== i,
                                                    ),
                                                )
                                            }
                                        >
                                            <Trash2 />
                                        </Button>
                                    </div>
                                ))}
                                <InputError message={errors.values} />
                                <div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            form.setData('values', [
                                                ...form.data.values,
                                                {
                                                    id: null,
                                                    value: '',
                                                    color: '#000000',
                                                },
                                            ])
                                        }
                                    >
                                        <Plus /> Add value
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Live storefront preview</CardTitle>
                            <CardDescription>
                                Changes apply to all products using this
                                attribute after saving.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <VariantSelector
                                option={{
                                    id: 0,
                                    name: form.data.name || 'Attribute',
                                    display_type: form.data.display_type,
                                    values: form.data.values.map((row, i) => ({
                                        ...row,
                                        id: i + 1,
                                        value: row.value || `Value ${i + 1}`,
                                    })),
                                }}
                                value={previewValue}
                                onChange={setPreviewValue}
                            />
                        </CardContent>
                    </Card>
                    <div className="flex gap-2">
                        <Button type="submit" disabled={form.processing}>
                            {attribute ? 'Save attribute' : 'Create attribute'}
                        </Button>
                        <Button variant="ghost" asChild>
                            <Link href={index()}>Cancel</Link>
                        </Button>
                    </div>
                </form>
            </AdminPage>
        </>
    );
}

AttributeForm.layout = {
    breadcrumbs: [{ title: 'Variant attributes', href: index() }],
};
