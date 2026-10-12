import { Form, Head } from '@inertiajs/react';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { RoleCheckboxes } from '@/components/admin/role-checkboxes';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { index } from '@/routes/admin/users';
import type { AssignableRole, StaffUser } from '@/types';

type Props = {
    user: StaffUser;
    roles: AssignableRole[];
    isSelf: boolean;
};

export default function EditUser({ user, roles, isSelf }: Props) {
    return (
        <>
            <Head title={user.name} />
            <AdminPage>
                <PageHeader
                    title={user.name}
                    description={user.email}
                    actions={
                        <div className="flex gap-1">
                            {user.disabled && (
                                <Badge variant="destructive">Disabled</Badge>
                            )}
                            {!user.email_verified && (
                                <Badge variant="outline">
                                    Unverified email
                                </Badge>
                            )}
                            {user.two_factor_enabled && (
                                <Badge variant="secondary">2FA on</Badge>
                            )}
                        </div>
                    }
                />

                {isSelf && (
                    <Alert>
                        <AlertDescription>
                            You cannot change your own roles or status. Ask
                            another owner or administrator.
                        </AlertDescription>
                    </Alert>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Roles</CardTitle>
                        <CardDescription>
                            Removing every role turns this account into a
                            customer account without admin access.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...UserController.updateRoles.form(user.id)}
                            options={{ preserveScroll: true }}
                            className="grid gap-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <RoleCheckboxes
                                        roles={roles}
                                        selected={user.roles.map((r) => r.id)}
                                        disabled={isSelf}
                                    />
                                    <InputError message={errors.roles} />
                                    {!isSelf && (
                                        <div>
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                Save roles
                                            </Button>
                                        </div>
                                    )}
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                {!isSelf && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Account status</CardTitle>
                            <CardDescription>
                                {user.disabled
                                    ? 'This account cannot sign in. Enabling it restores access with its current roles.'
                                    : 'Disabling signs the user out everywhere and blocks future sign-ins. Data and history are kept.'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <StatusDialog user={user} />
                        </CardContent>
                    </Card>
                )}
            </AdminPage>
        </>
    );
}

function StatusDialog({ user }: { user: StaffUser }) {
    const disabling = !user.disabled;

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant={disabling ? 'destructive' : 'default'}>
                    {disabling ? 'Disable account' : 'Enable account'}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>
                    {disabling
                        ? `Disable ${user.name}?`
                        : `Enable ${user.name}?`}
                </DialogTitle>
                <DialogDescription>
                    {disabling
                        ? 'They will be signed out immediately and will not be able to sign in until re-enabled.'
                        : 'They will be able to sign in again with their existing roles.'}
                </DialogDescription>
                <Form
                    {...UserController.updateStatus.form(user.id)}
                    options={{ preserveScroll: true }}
                >
                    {({ processing, errors }) => (
                        <>
                            <input
                                type="hidden"
                                name="disabled"
                                value={disabling ? '1' : '0'}
                            />
                            <InputError
                                message={errors.user}
                                className="mb-4"
                            />
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    variant={
                                        disabling ? 'destructive' : 'default'
                                    }
                                    disabled={processing}
                                >
                                    {disabling ? 'Disable' : 'Enable'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

EditUser.layout = {
    breadcrumbs: [{ title: 'Users', href: index() }],
};
