import { Form, Head, Link } from '@inertiajs/react';
import RoleController from '@/actions/App/Http/Controllers/Admin/RoleController';
import { FormField } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { index } from '@/routes/admin/roles';

type PermissionGroup = {
    group: string;
    permissions: { name: string; label: string; grantable: boolean }[];
};

type RoleData = {
    id: number;
    name: string;
    display_name: string;
    description: string | null;
    is_system: boolean;
    permissions: string[];
    users_count: number;
    editable: boolean;
};

export default function RoleForm({
    role,
    permissionGroups,
}: {
    role: RoleData | null;
    permissionGroups: PermissionGroup[];
}) {
    const form = role
        ? RoleController.update.form(role.id)
        : RoleController.store.form();
    const readOnly = role !== null && !role.editable;

    return (
        <>
            <Head title={role ? `Edit ${role.display_name}` : 'New role'} />
            <AdminPage>
                <PageHeader
                    title={role ? role.display_name : 'New role'}
                    description={
                        role?.is_system
                            ? 'System role. Its identifier is fixed but its label and permissions can be changed.'
                            : 'Custom role.'
                    }
                    actions={
                        role &&
                        !role.is_system &&
                        role.editable && <DeleteRoleDialog role={role} />
                    }
                />

                {readOnly && (
                    <Alert>
                        <AlertDescription>
                            This role has permissions you do not hold, so you
                            cannot edit it.
                        </AlertDescription>
                    </Alert>
                )}

                <Form
                    {...form}
                    options={{ preserveScroll: true }}
                    className="grid gap-6"
                >
                    {({ processing, errors }) => (
                        <fieldset disabled={readOnly} className="grid gap-6">
                            <InputError message={errors.role} />
                            <Card>
                                <CardHeader>
                                    <CardTitle>Details</CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-4 sm:grid-cols-2">
                                    <FormField
                                        id="display_name"
                                        label="Name"
                                        error={errors.display_name}
                                    >
                                        <Input
                                            id="display_name"
                                            name="display_name"
                                            defaultValue={role?.display_name}
                                            required
                                        />
                                    </FormField>
                                    <FormField
                                        id="description"
                                        label="Description"
                                        error={errors.description}
                                    >
                                        <Input
                                            id="description"
                                            name="description"
                                            defaultValue={
                                                role?.description ?? ''
                                            }
                                        />
                                    </FormField>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Permissions</CardTitle>
                                    <CardDescription>
                                        Permissions you do not hold are
                                        disabled. The server enforces the same
                                        rule.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-6 md:grid-cols-2">
                                    {permissionGroups.map((group) => (
                                        <fieldset
                                            key={group.group}
                                            className="grid gap-2"
                                        >
                                            <legend className="mb-2 text-sm font-semibold">
                                                {group.group}
                                            </legend>
                                            {group.permissions.map(
                                                (permission) => {
                                                    const checked =
                                                        role?.permissions.includes(
                                                            permission.name,
                                                        ) ?? false;
                                                    const id = `perm-${permission.name}`;

                                                    return (
                                                        <div
                                                            key={
                                                                permission.name
                                                            }
                                                            className="flex items-center gap-2"
                                                        >
                                                            <Checkbox
                                                                id={id}
                                                                name="permissions[]"
                                                                value={
                                                                    permission.name
                                                                }
                                                                defaultChecked={
                                                                    checked
                                                                }
                                                                disabled={
                                                                    !permission.grantable
                                                                }
                                                            />
                                                            <label
                                                                htmlFor={id}
                                                                className="text-sm"
                                                            >
                                                                {
                                                                    permission.label
                                                                }{' '}
                                                                <code className="text-xs text-muted-foreground">
                                                                    {
                                                                        permission.name
                                                                    }
                                                                </code>
                                                            </label>
                                                        </div>
                                                    );
                                                },
                                            )}
                                        </fieldset>
                                    ))}
                                    <InputError message={errors.permissions} />
                                </CardContent>
                            </Card>

                            {!readOnly && (
                                <div className="flex gap-2">
                                    <Button type="submit" disabled={processing}>
                                        {role ? 'Save role' : 'Create role'}
                                    </Button>
                                    <Button variant="ghost" asChild>
                                        <Link href={index()}>Cancel</Link>
                                    </Button>
                                </div>
                            )}
                        </fieldset>
                    )}
                </Form>
            </AdminPage>
        </>
    );
}

function DeleteRoleDialog({ role }: { role: RoleData }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="destructive">Delete role</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Delete “{role.display_name}”?</DialogTitle>
                <DialogDescription>
                    {role.users_count > 0
                        ? `This role is assigned to ${role.users_count} user(s). Remove it from them before deleting.`
                        : 'This permanently removes the role. This cannot be undone.'}
                </DialogDescription>
                <Form {...RoleController.destroy.form(role.id)}>
                    {({ processing, errors }) => (
                        <>
                            <InputError
                                message={errors.role}
                                className="mb-4"
                            />
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={
                                        processing || role.users_count > 0
                                    }
                                >
                                    Delete
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

RoleForm.layout = {
    breadcrumbs: [{ title: 'Roles & permissions', href: index() }],
};
