import { Form, Head, Link } from '@inertiajs/react';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import { FormField } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { RoleCheckboxes } from '@/components/admin/role-checkboxes';
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
import { create, index } from '@/routes/admin/users';
import type { AssignableRole } from '@/types';

export default function CreateUser({ roles }: { roles: AssignableRole[] }) {
    return (
        <>
            <Head title="Add staff user" />
            <AdminPage>
                <PageHeader
                    title="Add staff user"
                    description="The person receives an email link to choose their own password. You never set or see it."
                />

                <Form {...UserController.store.form()} className="grid gap-6">
                    {({ processing, errors }) => (
                        <>
                            <Card>
                                <CardHeader>
                                    <CardTitle>Account</CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-4 sm:grid-cols-2">
                                    <FormField
                                        id="name"
                                        label="Full name"
                                        error={errors.name}
                                    >
                                        <Input
                                            id="name"
                                            name="name"
                                            required
                                            autoComplete="off"
                                        />
                                    </FormField>
                                    <FormField
                                        id="email"
                                        label="Email address"
                                        error={errors.email}
                                    >
                                        <Input
                                            id="email"
                                            name="email"
                                            type="email"
                                            required
                                            autoComplete="off"
                                        />
                                    </FormField>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Roles</CardTitle>
                                    <CardDescription>
                                        Select at least one role. You can only
                                        grant roles whose permissions you hold
                                        yourself.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-2">
                                    <RoleCheckboxes
                                        roles={roles}
                                        selected={[]}
                                    />
                                    <InputError
                                        message={
                                            errors.roles ?? errors['roles.0']
                                        }
                                    />
                                </CardContent>
                            </Card>

                            <div className="flex gap-2">
                                <Button type="submit" disabled={processing}>
                                    Create and send invitation
                                </Button>
                                <Button variant="ghost" asChild>
                                    <Link href={index()}>Cancel</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </AdminPage>
        </>
    );
}

CreateUser.layout = {
    breadcrumbs: [
        { title: 'Users', href: index() },
        { title: 'Add staff user', href: create() },
    ],
};
