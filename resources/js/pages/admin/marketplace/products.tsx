import { Head, Link } from '@inertiajs/react';
import { Plus, ShieldAlert } from 'lucide-react';
import { EmptyState } from '@/components/admin/empty-state';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import { create, edit, index } from '@/routes/admin/marketplace/products';
import type { MoneyValue } from '@/types';

type Props = {
    products: {
        slug: string;
        name: string;
        type: string;
        status: string;
        price: MoneyValue;
        latest_version: string | null;
        pending_count: number;
        publisher: string | null;
    }[];
    setup: {
        signing: boolean;
        signing_public_key: string | null;
        payments: boolean;
    };
};

export default function MarketplaceProducts({ products, setup }: Props) {
    return (
        <>
            <Head title="Marketplace products" />
            <AdminPage>
                <PageHeader
                    title="Marketplace products"
                    description="Products sold on the official marketplace. Every release is validated on upload and must be reviewed before it is signed and offered."
                    actions={
                        <Button asChild>
                            <Link href={create()}>
                                <Plus /> New product
                            </Link>
                        </Button>
                    }
                />

                {!setup.signing && (
                    <Alert variant="destructive">
                        <ShieldAlert />
                        <AlertTitle>No signing key</AlertTitle>
                        <AlertDescription>
                            Run php artisan ecomodular:marketplace:keygen and
                            set ECOMODULAR_MARKETPLACE_SIGNING_KEY. Releases
                            cannot be approved until then.
                        </AlertDescription>
                    </Alert>
                )}
                {!setup.payments && (
                    <Alert>
                        <ShieldAlert />
                        <AlertTitle>
                            Paid products are not purchasable
                        </AlertTitle>
                        <AlertDescription>
                            Set MARKETPLACE_STRIPE_SECRET and
                            MARKETPLACE_STRIPE_WEBHOOK_SECRET. Free products
                            work without them.
                        </AlertDescription>
                    </Alert>
                )}
                {setup.signing_public_key && (
                    <p className="text-xs break-all text-muted-foreground">
                        Public key for stores
                        (ECOMODULAR_MARKETPLACE_TRUSTED_KEYS):{' '}
                        <code>{setup.signing_public_key}</code>
                    </p>
                )}

                {products.length === 0 ? (
                    <EmptyState
                        title="No products yet"
                        description="Create a product, then upload its first release."
                    />
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Product</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Latest</TableHead>
                                <TableHead className="text-right">
                                    Price
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {products.map((p) => (
                                <TableRow key={p.slug}>
                                    <TableCell>
                                        <Link
                                            href={edit(p.slug)}
                                            className="font-medium hover:underline"
                                        >
                                            {p.name}
                                        </Link>
                                        {p.publisher && (
                                            <span className="ml-2 text-xs text-muted-foreground">
                                                by {p.publisher}
                                            </span>
                                        )}
                                        {p.pending_count > 0 && (
                                            <Badge className="ml-2">
                                                {p.pending_count} to review
                                            </Badge>
                                        )}
                                    </TableCell>
                                    <TableCell>{p.type}</TableCell>
                                    <TableCell>
                                        <Badge variant="outline">
                                            {p.status}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        {p.latest_version
                                            ? `v${p.latest_version}`
                                            : '—'}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {p.price.formatted}
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

MarketplaceProducts.layout = {
    breadcrumbs: [{ title: 'Marketplace products', href: index() }],
};
