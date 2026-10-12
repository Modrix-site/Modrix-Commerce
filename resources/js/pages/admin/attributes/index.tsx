import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { EmptyState } from '@/components/admin/empty-state';
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
import { create, edit, index } from '@/routes/admin/attributes';

type AttributeRow = { id: number; name: string; values: string[] };

export default function AttributesIndex({
    attributes,
}: {
    attributes: AttributeRow[];
}) {
    return (
        <>
            <Head title="Options" />
            <AdminPage>
                <PageHeader
                    title="Options"
                    description="Option types such as Size or Color that define product variants."
                    actions={
                        <Button asChild>
                            <Link href={create()}>
                                <Plus /> Add option
                            </Link>
                        </Button>
                    }
                />
                {attributes.length === 0 ? (
                    <EmptyState
                        title="No options yet"
                        description="Create an option such as Size with values S, M and L."
                    />
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Option</TableHead>
                                <TableHead>Values</TableHead>
                                <TableHead>
                                    <span className="sr-only">Actions</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {attributes.map((attribute) => (
                                <TableRow key={attribute.id}>
                                    <TableCell className="font-medium">
                                        {attribute.name}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex flex-wrap gap-1">
                                            {attribute.values.map((v) => (
                                                <Badge
                                                    key={v}
                                                    variant="secondary"
                                                >
                                                    {v}
                                                </Badge>
                                            ))}
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <Link href={edit(attribute.id)}>
                                                Edit
                                            </Link>
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </AdminPage>
        </>
    );
}

AttributesIndex.layout = {
    breadcrumbs: [{ title: 'Options', href: index() }],
};
