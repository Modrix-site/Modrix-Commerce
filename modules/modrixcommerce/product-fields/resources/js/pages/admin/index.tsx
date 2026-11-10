import { Head, router, useForm } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ListPlus,
    Pencil,
    Plus,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/admin/empty-state';
import { FormField, NativeSelect } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';

type FieldRow = {
    id: number;
    key: string;
    label: string;
    type: string;
    options: string[];
    help: string | null;
    required: boolean;
    show_on_storefront: boolean;
    products_count: number;
    update_url: string;
    delete_url: string;
};

type Props = {
    types: Record<string, string>;
    fields: FieldRow[];
    storeUrl: string;
    reorderUrl: string;
};

type FormData = {
    label: string;
    type: string;
    options: string[];
    help: string;
    required: boolean;
    show_on_storefront: boolean;
};

/**
 * Products → Product fields: the extra fields every product can have.
 * Values are filled in from the "Product fields" card on each product.
 */
export default function ProductFieldsIndex({
    types,
    fields,
    storeUrl,
    reorderUrl,
}: Props) {
    const [editing, setEditing] = useState<FieldRow | 'new' | null>(null);

    const move = (index: number, by: -1 | 1) => {
        const ids = fields.map((f) => f.id);
        const [id] = ids.splice(index, 1);
        ids.splice(index + by, 0, id);
        router.post(reorderUrl, { ids }, { preserveScroll: true });
    };

    return (
        <>
            <Head title="Product fields" />
            <AdminPage>
                <PageHeader
                    title="Product fields"
                    description="Extra fields every product can have, such as Material, Care instructions or Warranty. Fill them in from the Product fields card on a product; switch on “Show on product page” to list them for shoppers."
                    actions={
                        <Button onClick={() => setEditing('new')}>
                            <Plus /> Add field
                        </Button>
                    }
                />

                {fields.length === 0 ? (
                    <EmptyState
                        icon={ListPlus}
                        title="No product fields yet"
                        description="Add a field, then fill it in on your products."
                        action={
                            <Button onClick={() => setEditing('new')}>
                                <Plus /> Add field
                            </Button>
                        }
                    />
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Field</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead>Shown to shoppers</TableHead>
                                <TableHead className="text-right">
                                    Products
                                </TableHead>
                                <TableHead className="text-right">
                                    <span className="sr-only">Actions</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {fields.map((field, i) => (
                                <TableRow key={field.id}>
                                    <TableCell>
                                        <div className="flex items-center gap-2 font-medium">
                                            {field.label}
                                            {field.required && (
                                                <Badge
                                                    className="status-badge"
                                                    data-tone="neutral"
                                                >
                                                    Required
                                                </Badge>
                                            )}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {field.key}
                                            {field.type === 'select' &&
                                                ` · ${field.options.join(', ')}`}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        {types[field.type] ?? field.type}
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            className="status-badge"
                                            data-tone={
                                                field.show_on_storefront
                                                    ? 'success'
                                                    : 'neutral'
                                            }
                                        >
                                            {field.show_on_storefront
                                                ? 'Shown'
                                                : 'Staff only'}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {field.products_count}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex justify-end gap-1">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                disabled={i === 0}
                                                onClick={() => move(i, -1)}
                                                aria-label={`Move ${field.label} up`}
                                            >
                                                <ArrowUp />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                disabled={
                                                    i === fields.length - 1
                                                }
                                                onClick={() => move(i, 1)}
                                                aria-label={`Move ${field.label} down`}
                                            >
                                                <ArrowDown />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() =>
                                                    setEditing(field)
                                                }
                                                aria-label={`Edit ${field.label}`}
                                            >
                                                <Pencil />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`Delete ${field.label}`}
                                                onClick={() => {
                                                    if (
                                                        window.confirm(
                                                            `Delete “${field.label}”? Its values are removed from ${field.products_count} product(s).`,
                                                        )
                                                    ) {
                                                        router.delete(
                                                            field.delete_url,
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        );
                                                    }
                                                }}
                                            >
                                                <Trash2 />
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </AdminPage>

            {editing !== null && (
                <FieldDialog
                    key={editing === 'new' ? 'new' : editing.id}
                    field={editing === 'new' ? null : editing}
                    types={types}
                    url={editing === 'new' ? storeUrl : editing.update_url}
                    onClose={() => setEditing(null)}
                />
            )}
        </>
    );
}

function FieldDialog({
    field,
    types,
    url,
    onClose,
}: {
    field: FieldRow | null;
    types: Record<string, string>;
    url: string;
    onClose: () => void;
}) {
    const form = useForm<FormData>({
        label: field?.label ?? '',
        type: field?.type ?? 'text',
        options: field?.options ?? [],
        help: field?.help ?? '',
        required: field?.required ?? false,
        show_on_storefront: field?.show_on_storefront ?? true,
    });
    const [choices, setChoices] = useState((field?.options ?? []).join('\n'));

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            options: choices
                .split('\n')
                .map((c) => c.trim())
                .filter(Boolean),
        }));
        const options = { preserveScroll: true, onSuccess: onClose };

        if (field) {
            form.put(url, options);
        } else {
            form.post(url, options);
        }
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-lg">
                <form onSubmit={submit} className="grid gap-4">
                    <div>
                        <DialogTitle>
                            {field ? `Edit “${field.label}”` : 'Add field'}
                        </DialogTitle>
                        <DialogDescription>
                            {field
                                ? `Key: ${field.key}`
                                : 'The key used by themes is made from the name.'}
                        </DialogDescription>
                    </div>
                    <FormField
                        id="label"
                        label="Name"
                        error={form.errors.label}
                    >
                        <Input
                            id="label"
                            value={form.data.label}
                            onChange={(e) =>
                                form.setData('label', e.target.value)
                            }
                            maxLength={120}
                            autoFocus
                            required
                        />
                    </FormField>
                    <FormField id="type" label="Type" error={form.errors.type}>
                        <NativeSelect
                            id="type"
                            value={form.data.type}
                            onChange={(e) =>
                                form.setData('type', e.target.value)
                            }
                        >
                            {Object.entries(types).map(([value, label]) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ))}
                        </NativeSelect>
                    </FormField>
                    {form.data.type === 'select' && (
                        <FormField
                            id="options"
                            label="Choices"
                            error={form.errors.options}
                            hint="One per line."
                        >
                            <Textarea
                                id="options"
                                rows={4}
                                value={choices}
                                onChange={(e) => setChoices(e.target.value)}
                            />
                        </FormField>
                    )}
                    <FormField
                        id="help"
                        label="Help text (optional)"
                        error={form.errors.help}
                        hint="Shown under the field on the product form."
                    >
                        <Input
                            id="help"
                            value={form.data.help}
                            onChange={(e) =>
                                form.setData('help', e.target.value)
                            }
                            maxLength={255}
                        />
                    </FormField>
                    <div className="grid gap-3">
                        <Label className="flex items-center gap-2 font-normal">
                            <Checkbox
                                checked={form.data.required}
                                onCheckedChange={(on) =>
                                    form.setData('required', on === true)
                                }
                            />
                            Required on every product
                        </Label>
                        <Label className="flex items-center gap-2 font-normal">
                            <Checkbox
                                checked={form.data.show_on_storefront}
                                onCheckedChange={(on) =>
                                    form.setData(
                                        'show_on_storefront',
                                        on === true,
                                    )
                                }
                            />
                            Show on product page
                        </Label>
                        <InputError message={form.errors.required} />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {field ? 'Save field' : 'Add field'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

ProductFieldsIndex.layout = {
    breadcrumbs: [
        { title: 'Products', href: '/admin/products' },
        {
            title: 'Product fields',
            href: '/admin/m/modrixcommerce/product-fields',
        },
    ],
};
