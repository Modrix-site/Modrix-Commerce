import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowRight,
    Building2,
    CheckCircle2,
    KeyRound,
    Lock,
    Mail,
    Store,
} from 'lucide-react';
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

interface Props {
    allowRegistration: boolean;
    registerUrl: string;
    dashboardUrl: string;
    vendorsDirectoryUrl: string;
    existingVendor: {
        id: number;
        store_name: string;
        status: string;
    } | null;
}

export default function VendorLoginPage({
    allowRegistration,
    registerUrl,
    dashboardUrl,
    vendorsDirectoryUrl,
    existingVendor,
}: Props) {
    const form = useForm({
        email: '',
        password: '',
        remember: true,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/m/modrixcommerce/multi-vendor/login');
    };

    return (
        <div className="mx-auto max-w-md space-y-6 px-4 py-12">
            <Head title="Marketplace Seller Login — Seller Portal" />

            <div className="space-y-2 text-center">
                <div className="mx-auto inline-flex items-center gap-2 rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary">
                    <Store className="size-3.5" />
                    Multi-Vendor Seller Portal
                </div>
                <h1 className="text-2xl font-bold tracking-tight">
                    Sign In to Your Seller Account
                </h1>
                <p className="text-sm text-muted-foreground">
                    Manage your vendor product catalog, KYB business
                    verification, customer order fulfillment, and marketplace
                    payouts.
                </p>
            </div>

            {existingVendor && (
                <Card className="border-primary/40 bg-primary/5">
                    <CardContent className="flex items-center justify-between gap-3 pt-6">
                        <div className="space-y-0.5">
                            <p className="flex items-center gap-1.5 text-sm font-semibold">
                                <CheckCircle2 className="size-4 text-primary" />
                                Signed in as {existingVendor.store_name}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                Status: {existingVendor.status}
                            </p>
                        </div>
                        <Button size="sm" asChild>
                            <Link href={dashboardUrl}>
                                Open Portal
                                <ArrowRight className="ml-1 size-3.5" />
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            )}

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2 text-lg">
                        <KeyRound className="size-5 text-primary" />
                        Seller Credentials
                    </CardTitle>
                    <CardDescription>
                        Enter the email and password you created during vendor
                        registration.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form onSubmit={submit} className="space-y-4">
                        <div className="space-y-1.5">
                            <Label htmlFor="seller-email">
                                Seller Account Email
                            </Label>
                            <div className="relative">
                                <Mail className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    id="seller-email"
                                    type="email"
                                    required
                                    autoFocus
                                    className="pl-9"
                                    value={form.data.email}
                                    onChange={(e) =>
                                        form.setData('email', e.target.value)
                                    }
                                    placeholder="seller@yourbrand.com"
                                />
                            </div>
                            {form.errors.email && (
                                <p className="text-xs text-destructive">
                                    {form.errors.email}
                                </p>
                            )}
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="seller-password">Password</Label>
                            <div className="relative">
                                <Lock className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    id="seller-password"
                                    type="password"
                                    required
                                    className="pl-9"
                                    value={form.data.password}
                                    onChange={(e) =>
                                        form.setData('password', e.target.value)
                                    }
                                    placeholder="••••••••"
                                />
                            </div>
                            {form.errors.password && (
                                <p className="text-xs text-destructive">
                                    {form.errors.password}
                                </p>
                            )}
                        </div>

                        <label className="flex items-center gap-2 text-xs text-muted-foreground">
                            <Checkbox
                                checked={form.data.remember}
                                onCheckedChange={(v) =>
                                    form.setData('remember', Boolean(v))
                                }
                            />
                            Keep me signed in on this device
                        </label>

                        <Button
                            type="submit"
                            className="w-full"
                            disabled={form.processing}
                        >
                            Sign In to Seller Portal
                            <ArrowRight className="ml-1.5 size-4" />
                        </Button>
                    </form>
                </CardContent>
            </Card>

            <div className="space-y-2 rounded-xl border bg-muted/30 p-4 text-center">
                <p className="text-xs text-muted-foreground">
                    Don&apos;t have a marketplace seller account yet?
                </p>
                <div className="flex flex-wrap items-center justify-center gap-2">
                    {allowRegistration && (
                        <Button variant="outline" size="sm" asChild>
                            <Link href={registerUrl}>
                                <Building2 className="mr-1.5 size-3.5" />
                                Register Seller Account & KYB
                            </Link>
                        </Button>
                    )}
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={vendorsDirectoryUrl}>
                            Browse Marketplace Sellers
                        </Link>
                    </Button>
                </div>
            </div>
        </div>
    );
}
