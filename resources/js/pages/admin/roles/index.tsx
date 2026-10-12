import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { create, edit, index } from '@/routes/admin/roles';

type RoleRow = {
    id: number;
    name: string;
    display_name: string;
    description: string | null;
    is_system: boolean;
    users_count: number;
    permissions_count: number | null;
    editable: boolean;
};

export default function RolesIndex({ roles }: { roles: RoleRow[] }) {
    return (
        <>
            <Head title="Roles & permissions" />
            <AdminPage>
                <PageHeader
                    title="Roles & permissions"
                    description="Roles bundle permissions. You can only edit roles whose permissions you hold yourself."
                    actions={
                        <Button asChild>
                            <Link href={create()}>
                                <Plus /> New role
                            </Link>
                        </Button>
                    }
                />

                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Role</TableHead>
                            <TableHead>Permissions</TableHead>
                            <TableHead>Users</TableHead>
                            <TableHead>
                                <span className="sr-only">Actions</span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {roles.map((role) => (
                            <TableRow key={role.id}>
                                <TableCell>
                                    <div className="flex items-center gap-2 font-medium">
                                        {role.display_name}
                                        {role.is_system && (
                                            <Badge variant="outline">
                                                System
                                            </Badge>
                                        )}
                                    </div>
                                    {role.description && (
                                        <div className="text-muted-foreground">
                                            {role.description}
                                        </div>
                                    )}
                                </TableCell>
                                <TableCell className="tabular-nums">
                                    {role.permissions_count === null
                                        ? 'All'
                                        : role.permissions_count}
                                </TableCell>
                                <TableCell className="tabular-nums">
                                    {role.users_count}
                                </TableCell>
                                <TableCell className="text-right">
                                    {role.editable ? (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <Link href={edit(role.id)}>
                                                Edit
                                            </Link>
                                        </Button>
                                    ) : (
                                        <span className="text-xs text-muted-foreground">
                                            {role.name === 'owner'
                                                ? 'Not editable'
                                                : 'Insufficient permissions'}
                                        </span>
                                    )}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </AdminPage>
        </>
    );
}

RolesIndex.layout = {
    breadcrumbs: [{ title: 'Roles & permissions', href: index() }],
};
