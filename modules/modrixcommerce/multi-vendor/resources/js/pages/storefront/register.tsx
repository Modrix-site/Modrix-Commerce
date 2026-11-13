import { Head, Link, useForm } from '@inertiajs/react';
import {
    BadgeCheck,
    Building2,
    CheckCircle2,
    KeyRound,
    LogIn,
    Store,
    Wallet,
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

interface Props {
    businessTypes: Record<string, string>;
    payoutMethods: Record<string, string>;
    defaultCommissionRate: number;
    requireBusinessVerification: boolean;
    isAuthenticated?: boolean;
    authenticatedUser?: {
        name: string;
        email: string;
    } | null;
    existingVendor: {
        id: number;
        store_name: string;
        slug: string;
        status: string;
        verification_status: string;
    } | null;
    loginUrl?: string;
    dashboardUrl: string;
    vendorsDirectoryUrl: string;
}

export default function VendorRegistrationPage({
    businessTypes,
    payoutMethods,
    defaultCommissionRate,
    requireBusinessVerification,
    isAuthenticated = false,
    authenticatedUser = null,
    existingVendor,
    loginUrl = '/m/modrixcommerce/multi-vendor/login',
    dashboardUrl,
    vendorsDirectoryUrl,
}: Props) {
    const form = useForm({
        account_name: authenticatedUser?.name || '',
        store_name: '',
        email: authenticatedUser?.email || '',
        password: '',
        password_confirmation: '',
        phone: '',
        tagline: '',
        description: '',
        business_type: 'llc',
        legal_business_name: '',
        tax_id: '',
        trade_license_number: '',
        identity_document_ref: '',
        business_address: '',
        city: '',
        country_code: 'AE',
        payout_method: 'bank_transfer',
        auto_payout_enabled: true,
        bank_name: '',
        bank_account_name: '',
        bank_account_number: '',
        bank_swift_code: '',
        payout_email: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/m/modrixcommerce/multi-vendor/register');
    };

    return (
        <div className="mx-auto max-w-4xl space-y-8 px-4 py-10">
            <Head title="Become a Marketplace Seller — Vendor Registration & Account Creation" />

            <div className="flex flex-col items-center justify-between gap-4 rounded-2xl border bg-card p-6 sm:flex-row">
                <div className="space-y-1 text-center sm:text-left">
                    <div className="inline-flex items-center gap-2 rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary">
                        <Store className="size-3.5" />
                        Seller Account Creation & KYB Onboarding
                    </div>
                    <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">
                        Become a Verified Marketplace Seller
                    </h1>
                    <p className="max-w-xl text-sm text-muted-foreground">
                        Create your seller login account, launch your vendor
                        storefront, and receive payouts directly from the
                        marketplace store (Standard commission:{' '}
                        {defaultCommissionRate}%).
                    </p>
                </div>

                <div className="flex shrink-0 flex-col items-center gap-2 sm:items-end">
                    <span className="text-xs text-muted-foreground">
                        Already registered your store?
                    </span>
                    <Button variant="outline" size="sm" asChild>
                        <Link href={loginUrl}>
                            <LogIn className="mr-1.5 size-4" />
                            Sign In to Seller Portal
                        </Link>
                    </Button>
                </div>
            </div>

            {existingVendor && (
                <Card className="border-primary/40 bg-primary/5">
                    <CardContent className="flex flex-col justify-between gap-4 pt-6 sm:flex-row sm:items-center">
                        <div className="space-y-1">
                            <p className="flex items-center gap-2 font-semibold">
                                <CheckCircle2 className="size-4 text-primary" />
                                Active Seller Account Found:{' '}
                                {existingVendor.store_name}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                Status: <strong>{existingVendor.status}</strong>{' '}
                                • Business Verification:{' '}
                                <strong>
                                    {existingVendor.verification_status}
                                </strong>
                            </p>
                        </div>
                        <Button asChild size="sm">
                            <Link href={dashboardUrl}>Open Seller Portal</Link>
                        </Button>
                    </CardContent>
                </Card>
            )}

            <form onSubmit={submit} className="space-y-6">
                {/* Step 1: Seller Login Account Creation */}
                <Card className="border-primary/30">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <KeyRound className="size-5 text-primary" />
                            1. Seller Login Account Credentials
                        </CardTitle>
                        <CardDescription>
                            {isAuthenticated
                                ? `Signed in as ${authenticatedUser?.email}. Your vendor store will be linked to this account (you may optionally set a new password below).`
                                : 'Create your seller login email and password. You will use these credentials to sign in to your Seller Portal once your store is registered or approved.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-1.5">
                            <Label htmlFor="account_name">
                                Seller Full Name / Account Owner *
                            </Label>
                            <Input
                                id="account_name"
                                required
                                value={form.data.account_name}
                                onChange={(e) =>
                                    form.setData('account_name', e.target.value)
                                }
                                placeholder="e.g.,Tariq Al-Mansoor"
                            />
                            {form.errors.account_name && (
                                <p className="text-xs text-destructive">
                                    {form.errors.account_name}
                                </p>
                            )}
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="email">
                                Seller Login & Business Email *
                            </Label>
                            <Input
                                id="email"
                                type="email"
                                required
                                value={form.data.email}
                                onChange={(e) =>
                                    form.setData('email', e.target.value)
                                }
                                placeholder="seller@atlascraft.com"
                            />
                            {form.errors.email && (
                                <p className="text-xs text-destructive">
                                    {form.errors.email}
                                </p>
                            )}
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="password">
                                {isAuthenticated
                                    ? 'New Seller Password (Optional)'
                                    : 'Create Account Password (Min 8 characters) *'}
                            </Label>
                            <Input
                                id="password"
                                type="password"
                                required={!isAuthenticated}
                                minLength={8}
                                value={form.data.password}
                                onChange={(e) =>
                                    form.setData('password', e.target.value)
                                }
                                placeholder="••••••••"
                            />
                            {form.errors.password && (
                                <p className="text-xs text-destructive">
                                    {form.errors.password}
                                </p>
                            )}
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="password_confirmation">
                                {isAuthenticated
                                    ? 'Confirm New Password'
                                    : 'Confirm Account Password *'}
                            </Label>
                            <Input
                                id="password_confirmation"
                                type="password"
                                required={
                                    !isAuthenticated ||
                                    form.data.password.length > 0
                                }
                                minLength={8}
                                value={form.data.password_confirmation}
                                onChange={(e) =>
                                    form.setData(
                                        'password_confirmation',
                                        e.target.value,
                                    )
                                }
                                placeholder="••••••••"
                            />
                        </div>
                    </CardContent>
                </Card>

                {/* Step 2: Storefront Profile */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Store className="size-5 text-primary" />
                            2. Vendor Storefront Identity
                        </CardTitle>
                        <CardDescription>
                            Your public store name, contact phone, and brand
                            description shown to shoppers.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-1.5">
                            <Label htmlFor="store_name">
                                Public Store Name *
                            </Label>
                            <Input
                                id="store_name"
                                required
                                value={form.data.store_name}
                                onChange={(e) =>
                                    form.setData('store_name', e.target.value)
                                }
                                placeholder="e.g., Atlas Craft Co."
                            />
                            {form.errors.store_name && (
                                <p className="text-xs text-destructive">
                                    {form.errors.store_name}
                                </p>
                            )}
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="phone">Business Phone</Label>
                            <Input
                                id="phone"
                                value={form.data.phone}
                                onChange={(e) =>
                                    form.setData('phone', e.target.value)
                                }
                                placeholder="+971 50 000 0000"
                            />
                        </div>
                        <div className="space-y-1.5 sm:col-span-2">
                            <Label htmlFor="tagline">Short Tagline</Label>
                            <Input
                                id="tagline"
                                value={form.data.tagline}
                                onChange={(e) =>
                                    form.setData('tagline', e.target.value)
                                }
                                placeholder="Handcrafted leather goods & travel essentials"
                            />
                        </div>
                        <div className="space-y-1.5 sm:col-span-2">
                            <Label htmlFor="description">
                                Store Bio & Policies
                            </Label>
                            <Textarea
                                id="description"
                                rows={3}
                                value={form.data.description}
                                onChange={(e) =>
                                    form.setData('description', e.target.value)
                                }
                                placeholder="Tell customers about your brand, craftsmanship, and shipping standards..."
                            />
                        </div>
                    </CardContent>
                </Card>

                {/* Step 3: Business Verification (KYB / KYC) */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <BadgeCheck className="size-5 text-emerald-600" />
                            3. Business Verification (KYB / KYC Compliance)
                        </CardTitle>
                        <CardDescription>
                            {requireBusinessVerification
                                ? 'Business verification is required to publish products and receive the Verified Business badge.'
                                : 'Submit your official registration credentials to earn the Verified Business badge on your storefront and product cards.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-1.5">
                            <Label>Legal Entity Type *</Label>
                            <Select
                                value={form.data.business_type}
                                onValueChange={(v) =>
                                    form.setData('business_type', v)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(businessTypes).map(
                                        ([key, label]) => (
                                            <SelectItem key={key} value={key}>
                                                {label}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="legal_business_name">
                                Registered Legal Business Name *
                            </Label>
                            <Input
                                id="legal_business_name"
                                required
                                value={form.data.legal_business_name}
                                onChange={(e) =>
                                    form.setData(
                                        'legal_business_name',
                                        e.target.value,
                                    )
                                }
                                placeholder="Atlas Craft Trading LLC"
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="trade_license_number">
                                Commercial Trade License / Company Reg. No *
                            </Label>
                            <Input
                                id="trade_license_number"
                                required
                                value={form.data.trade_license_number}
                                onChange={(e) =>
                                    form.setData(
                                        'trade_license_number',
                                        e.target.value,
                                    )
                                }
                                placeholder="DED-8849201"
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="tax_id">
                                Tax Registration Number (TRN / VAT / EIN)
                            </Label>
                            <Input
                                id="tax_id"
                                value={form.data.tax_id}
                                onChange={(e) =>
                                    form.setData('tax_id', e.target.value)
                                }
                                placeholder="100394829100003"
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="identity_document_ref">
                                Authorized Signatory National ID / Passport Ref
                            </Label>
                            <Input
                                id="identity_document_ref"
                                value={form.data.identity_document_ref}
                                onChange={(e) =>
                                    form.setData(
                                        'identity_document_ref',
                                        e.target.value,
                                    )
                                }
                                placeholder="EID-784-1990-1234567-1"
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label>City & Country</Label>
                            <div className="flex gap-2">
                                <Input
                                    value={form.data.city}
                                    onChange={(e) =>
                                        form.setData('city', e.target.value)
                                    }
                                    placeholder="Dubai"
                                />
                                <Input
                                    className="w-24"
                                    value={form.data.country_code}
                                    onChange={(e) =>
                                        form.setData(
                                            'country_code',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="AE"
                                />
                            </div>
                        </div>
                        <div className="space-y-1.5 sm:col-span-2">
                            <Label htmlFor="business_address">
                                Registered Headquarters Address
                            </Label>
                            <Input
                                id="business_address"
                                value={form.data.business_address}
                                onChange={(e) =>
                                    form.setData(
                                        'business_address',
                                        e.target.value,
                                    )
                                }
                                placeholder="Building 4, Design District, Dubai, UAE"
                            />
                        </div>
                    </CardContent>
                </Card>

                {/* Step 4: Store-to-Vendor Payout Preferences */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Wallet className="size-5 text-primary" />
                            4. Marketplace Payout Preferences
                        </CardTitle>
                        <CardDescription>
                            Choose how you want to receive your net sales
                            earnings from the marketplace store.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-1.5">
                                <Label>Preferred Payout Method *</Label>
                                <Select
                                    value={form.data.payout_method}
                                    onValueChange={(v) =>
                                        form.setData('payout_method', v)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {Object.entries(payoutMethods).map(
                                            ([key, label]) => (
                                                <SelectItem
                                                    key={key}
                                                    value={key}
                                                >
                                                    {label}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                            </div>

                            {form.data.payout_method === 'bank_transfer' ? (
                                <>
                                    <div className="space-y-1.5">
                                        <Label>Bank Name</Label>
                                        <Input
                                            value={form.data.bank_name}
                                            onChange={(e) =>
                                                form.setData(
                                                    'bank_name',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Emirates NBD / HSBC / Chase"
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>Account Holder Name</Label>
                                        <Input
                                            value={form.data.bank_account_name}
                                            onChange={(e) =>
                                                form.setData(
                                                    'bank_account_name',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Atlas Craft Trading LLC"
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>IBAN / Account Number</Label>
                                        <Input
                                            value={
                                                form.data.bank_account_number
                                            }
                                            onChange={(e) =>
                                                form.setData(
                                                    'bank_account_number',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="AE070331234567890123456"
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>SWIFT / BIC Code</Label>
                                        <Input
                                            value={form.data.bank_swift_code}
                                            onChange={(e) =>
                                                form.setData(
                                                    'bank_swift_code',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="EBILAEAD"
                                        />
                                    </div>
                                </>
                            ) : (
                                <div className="space-y-1.5">
                                    <Label>
                                        Payout Account Email / Connected Account
                                        ID
                                    </Label>
                                    <Input
                                        value={form.data.payout_email}
                                        onChange={(e) =>
                                            form.setData(
                                                'payout_email',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="payouts@atlascraft.com or acct_123456"
                                    />
                                </div>
                            )}
                        </div>

                        <label className="flex items-center gap-2.5 rounded-lg border p-3 text-sm">
                            <Checkbox
                                checked={form.data.auto_payout_enabled}
                                onCheckedChange={(v) =>
                                    form.setData(
                                        'auto_payout_enabled',
                                        Boolean(v),
                                    )
                                }
                            />
                            <span>
                                Automatically request payout from the
                                marketplace store whenever my available balance
                                reaches the minimum payout threshold
                            </span>
                        </label>
                    </CardContent>
                </Card>

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Button variant="ghost" asChild>
                        <Link href={vendorsDirectoryUrl}>
                            Back to Marketplace Directory
                        </Link>
                    </Button>
                    <Button type="submit" size="lg" disabled={form.processing}>
                        <Building2 className="mr-2 size-4" />
                        Create Seller Account & Submit Verification
                    </Button>
                </div>
            </form>
        </div>
    );
}
