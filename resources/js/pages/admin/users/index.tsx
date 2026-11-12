import { Form, Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { EmptyState } from '@/components/admin/empty-state';
import { NativeSelect } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { create, edit, index } from '@/routes/admin/users';
import type { Paginated, StaffUser } from '@/types';

type Props = {
    users: Paginated<StaffUser>;
    filters: { search: string; status: string };
    canManage: boolean;
};

export default function UsersIndex({ users, filters, canManage }: Props) {
    const filtered = filters.search !== '' || filters.status !== '';

    return (
        <>
            <Head title="Users" />
            <AdminPage>
                <PageHeader
                    title="Users"
                    description="Staff accounts with access to the admin area."
                    actions={
                        canManage && (
                            <Button asChild>
                                <Link href={create()}>
                                    <Plus /> Add staff user
                                </Link>
                            </Button>
                        )
                    }
                />

                <Form
                    {...index.form()}
                    className="flex flex-col gap-3 sm:flex-row sm:items-end"
                >
                    <div className="grid gap-2 sm:w-72">
                        <Label htmlFor="search">Search</Label>
                        <Input
                            id="search"
                            name="search"
                            type="search"
                            defaultValue={filters.search}
                            placeholder="Name or email"
                        />
                    </div>
                    <div className="grid gap-2 sm:w-44">
                        <Label htmlFor="status">Status</Label>
                        <NativeSelect
                            id="status"
                            name="status"
                            defaultValue={filters.status}
                        >
                            <option value="">Any status</option>
                            <option value="active">Active</option>
                            <option value="disabled">Disabled</option>
                        </NativeSelect>
                    </div>
                    <div className="flex gap-2">
                        <Button type="submit" variant="secondary">
                            Filter
                        </Button>
                        {filtered && (
                            <Button variant="ghost" asChild>
                                <Link href={index()}>Clear</Link>
                            </Button>
                        )}
                    </div>
                </Form>

                {users.data.length === 0 ? (
                    <EmptyState
                        title={
                            filtered
                                ? 'No users match these filters'
                                : 'No staff users yet'
                        }
                        description={
                            filtered
                                ? 'Try a different search or clear the filters.'
                                : 'Add a staff user to delegate store management.'
                        }
                    />
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Roles</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>2FA</TableHead>
                                {canManage && (
                                    <TableHead>
                                        <span className="sr-only">Actions</span>
                                    </TableHead>
                                )}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {users.data.map((user) => (
                                <TableRow key={user.id}>
                                    <TableCell>
                                        <div className="font-medium">
                                            {user.name}
                                        </div>
                                        <div className="text-muted-foreground">
                                            {user.email}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex flex-wrap gap-1">
                                            {user.roles.map((role) => (
                                                <Badge
                                                    key={role.id}
                                                    variant="secondary"
                                                >
                                                    {role.display_name}
                                                </Badge>
                                            ))}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        {user.disabled ? (
                                            <Badge variant="destructive">
                                                Disabled
                                            </Badge>
                                        ) : !user.email_verified ? (
                                            <Badge variant="outline">
                                                Unverified email
                                            </Badge>
                                        ) : (
                                            'Active'
                                        )}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {user.two_factor_enabled ? 'On' : 'Off'}
                                    </TableCell>
                                    {canManage && (
                                        <TableCell className="text-right">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                asChild
                                            >
                                                <Link href={edit(user.id)}>
                                                    Manage
                                                </Link>
                                            </Button>
                                        </TableCell>
                                    )}
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}

                <Pagination
                    from={users.from}
                    to={users.to}
                    total={users.total}
                    prevUrl={users.prev_page_url}
                    nextUrl={users.next_page_url}
                />
            </AdminPage>
        </>
    );
}

UsersIndex.layout = {
    breadcrumbs: [{ title: 'Users', href: index() }],
};
