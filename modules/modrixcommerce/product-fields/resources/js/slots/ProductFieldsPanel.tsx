import { Link, useForm } from '@inertiajs/react';
import { ListPlus } from 'lucide-react';
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

type Field = {
    id: number;
    label: string;
    type: string;
    options: string[];
    help: string | null;
    required: boolean;
    value: string | null;
};

type Props = {
    updateUrl: string;
    manageUrl: string | null;
    fields: Field[];
};

/**
 * Product editor sidebar: this product's custom field values. Saves on its
 * own (not with the product form).
 */
export default function ProductFieldsPanel({
    updateUrl,
    manageUrl,
    fields,
}: Props) {
    const form = useForm<{ values: Record<string, string> }>({
        values: Object.fromEntries(
            fields.map((f) => [String(f.id), f.value ?? '']),
        ),
    });

    const set = (id: number, value: string) =>
        form.setData('values', { ...form.data.values, [String(id)]: value });

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <ListPlus className="size-4" aria-hidden /> Product fields
                </CardTitle>
                {fields.length === 0 && (
                    <CardDescription>
                        No fields yet.{' '}
                        {manageUrl && (
                            <Link href={manageUrl} className="underline">
                                Add product fields
                            </Link>
                        )}
                    </CardDescription>
                )}
            </CardHeader>
            {fields.length > 0 && (
                <CardContent>
                    <form
                        className="grid gap-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.put(updateUrl, { preserveScroll: true });
                        }}
                    >
                        {fields.map((field) => {
                            const id = `product-field-${field.id}`;
                            const value =
                                form.data.values[String(field.id)] ?? '';
                            const error = (
                                form.errors as Record<
                                    string,
                                    string | undefined
                                >
                            )[`values.${field.id}`];

                            if (field.type === 'boolean') {
                                return (
                                    <div key={field.id} className="grid gap-1">
                                        <Label
                                            htmlFor={id}
                                            className="flex items-center gap-2 font-normal"
                                        >
                                            <Checkbox
                                                id={id}
                                                checked={value === '1'}
                                                onCheckedChange={(on) =>
                                                    set(
                                                        field.id,
                                                        on === true ? '1' : '0',
                                                    )
                                                }
                                            />
                                            {field.label}
                                        </Label>
                                        {field.help && (
                                            <p className="text-xs text-muted-foreground">
                                                {field.help}
                                            </p>
                                        )}
                                        <InputError message={error} />
                                    </div>
                                );
                            }

                            return (
                                <div key={field.id} className="grid gap-1.5">
                                    <Label htmlFor={id}>
                                        {field.label}
                                        {field.required && (
                                            <span className="text-muted-foreground">
                                                {' '}
                                                (required)
                                            </span>
                                        )}
                                    </Label>
                                    {field.type === 'textarea' ? (
                                        <Textarea
                                            id={id}
                                            rows={3}
                                            value={value}
                                            onChange={(e) =>
                                                set(field.id, e.target.value)
                                            }
                                        />
                                    ) : field.type === 'select' ? (
                                        <NativeSelect
                                            id={id}
                                            value={value}
                                            onChange={(e) =>
                                                set(field.id, e.target.value)
                                            }
                                        >
                                            <option value="">—</option>
                                            {field.options.map((option) => (
                                                <option
                                                    key={option}
                                                    value={option}
                                                >
                                                    {option}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                    ) : (
                                        <Input
                                            id={id}
                                            type={
                                                field.type === 'number'
                                                    ? 'number'
                                                    : field.type === 'date'
                                                      ? 'date'
                                                      : field.type === 'url'
                                                        ? 'url'
                                                        : 'text'
                                            }
                                            step={
                                                field.type === 'number'
                                                    ? 'any'
                                                    : undefined
                                            }
                                            value={value}
                                            onChange={(e) =>
                                                set(field.id, e.target.value)
                                            }
                                        />
                                    )}
                                    {field.help && (
                                        <p className="text-xs text-muted-foreground">
                                            {field.help}
                                        </p>
                                    )}
                                    <InputError message={error} />
                                </div>
                            );
                        })}
                        <div className="flex items-center justify-between gap-2">
                            {manageUrl ? (
                                <Link
                                    href={manageUrl}
                                    className="text-xs text-muted-foreground hover:underline"
                                >
                                    Manage fields
                                </Link>
                            ) : (
                                <span />
                            )}
                            <Button
                                type="submit"
                                size="sm"
                                disabled={form.processing}
                            >
                                Save fields
                            </Button>
                        </div>
                    </form>
                </CardContent>
            )}
        </Card>
    );
}
