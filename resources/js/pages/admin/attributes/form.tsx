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
    values: { id: number; value: string }[];
};

type FormData = {
    name: string;
    values: { id: number | null; value: string }[];
};

export default function AttributeForm({
    attribute,
}: {
    attribute: AttributeData | null;
}) {
    const form = useForm<FormData>({
        name: attribute?.name ?? '',
        values: attribute?.values ?? [{ id: null, value: '' }],
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
            window.confirm(`Delete the option “${attribute.name}”?`)
        ) {
            form.submit(AttributeController.destroy(attribute.id));
        }
    };

    return (
        <>
            <Head title={attribute ? `Edit ${attribute.name}` : 'Add option'} />
            <AdminPage>
                <PageHeader
                    title={attribute ? attribute.name : 'Add option'}
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
                            <CardTitle>Option</CardTitle>
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
                            <div className="grid gap-2">
                                <span className="text-sm font-medium">
                                    Values
                                </span>
                                {form.data.values.map((value, i) => (
                                    <div
                                        key={value.id ?? `new-${i}`}
                                        className="flex gap-2"
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
                                                { id: null, value: '' },
                                            ])
                                        }
                                    >
                                        <Plus /> Add value
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <div className="flex gap-2">
                        <Button type="submit" disabled={form.processing}>
                            {attribute ? 'Save option' : 'Create option'}
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
    breadcrumbs: [{ title: 'Options', href: index() }],
};
