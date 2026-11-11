import { Head, Link, router } from '@inertiajs/react';
import {
    AlertCircle,
    AlertTriangle,
    ArrowLeft,
    CheckCircle2,
    Clock,
    CreditCard,
    FileText,
    Globe,
    History,
    Mail,
    MapPin,
    Server,
    Shield,
    ShieldAlert,
    ShieldCheck,
    TrendingUp,
    UserCheck,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
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
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';

type Indicator = {
    key: string;
    label: string;
    score: number;
    details: string;
};

type ReviewHistory = {
    id: number;
    user_name: string;
    action: string;
    comment: string | null;
    previous_status: string | null;
    new_status: string | null;
    created_at: string;
    created_date: string;
};

type CustomerProfile = {
    id: number;
    customer_id: number;
    total_orders: number;
    approved_orders: number;
    rejected_orders: number;
    trust_score: number;
    risk_level: 'low' | 'medium' | 'high';
    notes?: string | null;
};

type FraudScoreDetail = {
    id: number;
    order_id: number;
    order_number: string;
    customer_email: string;
    customer_id: number | null;
    total_score: number;
    risk_level: 'low' | 'medium' | 'high';
    review_status: 'pending' | 'approved' | 'rejected' | 'under_review';
    indicators: Indicator[];
    ip_address: string | null;
    customer_country: string | null;
    payment_country: string | null;
    is_vpn: boolean;
    is_proxy: boolean;
    is_disposable_email: boolean;
    address_mismatch: boolean;
    failed_payment_attempts: number;
    order_total: string;
    currency: string;
    review_notes: string | null;
    reviewed_by: string | null;
    reviewed_at: string | null;
    created_at: string;
    created_date: string;
    approve_url: string;
    reject_url: string;
    review_url: string;
    note_url: string;
};

type Props = {
    fraudScore: FraudScoreDetail;
    reviews: ReviewHistory[];
    customerProfile: CustomerProfile | null;
    queueUrl: string;
};

function riskBadge(level: string) {
    switch (level) {
        case 'high':
            return (
                <Badge className="border-red-200 bg-red-500/15 text-red-700 hover:bg-red-500/25 dark:text-red-400">
                    <ShieldAlert className="mr-1 size-3.5" /> High Risk
                </Badge>
            );
        case 'medium':
            return (
                <Badge className="border-amber-200 bg-amber-500/15 text-amber-700 hover:bg-amber-500/25 dark:text-amber-400">
                    <AlertTriangle className="mr-1 size-3.5" /> Medium Risk
                </Badge>
            );
        case 'low':
            return (
                <Badge className="border-emerald-200 bg-emerald-500/15 text-emerald-700 hover:bg-emerald-500/25 dark:text-emerald-400">
                    <ShieldCheck className="mr-1 size-3.5" /> Low Risk
                </Badge>
            );
        default:
            return <Badge variant="outline">{level}</Badge>;
    }
}

function statusBadge(status: string) {
    switch (status) {
        case 'approved':
            return (
                <Badge className="border-emerald-200 bg-emerald-500/15 text-emerald-700 dark:text-emerald-400">
                    <CheckCircle2 className="mr-1 size-3.5" /> Approved
                </Badge>
            );
        case 'rejected':
            return (
                <Badge className="border-red-200 bg-red-500/15 text-red-700 dark:text-red-400">
                    <XCircle className="mr-1 size-3.5" /> Rejected
                </Badge>
            );
        case 'under_review':
            return (
                <Badge className="border-blue-200 bg-blue-500/15 text-blue-700 dark:text-blue-400">
                    <Clock className="mr-1 size-3.5" /> Under Review
                </Badge>
            );
        default:
            return (
                <Badge className="border-amber-200 bg-amber-500/15 text-amber-700 dark:text-amber-400">
                    <Clock className="mr-1 size-3.5" /> Pending Analysis
                </Badge>
            );
    }
}

function ScoreGauge({ score }: { score: number }) {
    const color = score >= 66 ? '#ef4444' : score >= 31 ? '#f59e0b' : '#22c55e';
    const circumference = 2 * Math.PI * 52;
    const strokeDashoffset = circumference - (score / 100) * circumference;

    return (
        <div className="relative flex items-center justify-center">
            <svg
                width="130"
                height="130"
                viewBox="0 0 130 130"
                className="-rotate-90"
            >
                <circle
                    cx="65"
                    cy="65"
                    r="52"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="10"
                    className="text-muted/20"
                />
                <circle
                    cx="65"
                    cy="65"
                    r="52"
                    fill="none"
                    stroke={color}
                    strokeWidth="10"
                    strokeLinecap="round"
                    strokeDasharray={circumference}
                    strokeDashoffset={strokeDashoffset}
                    className="transition-all duration-1000 ease-out"
                />
            </svg>
            <div className="absolute flex flex-col items-center">
                <span
                    className="text-3xl font-extrabold tracking-tight"
                    style={{ color }}
                >
                    {score}
                </span>
                <span className="text-[11px] font-medium tracking-wider text-muted-foreground uppercase">
                    / 100
                </span>
            </div>
        </div>
    );
}

export default function OrderFraudDetail({
    fraudScore,
    reviews,
    customerProfile,
    queueUrl,
}: Props) {
    const [actionModal, setActionModal] = useState<{
        open: boolean;
        type: 'approve' | 'reject' | 'review' | 'note' | null;
        notes: string;
    }>({
        open: false,
        type: null,
        notes: '',
    });
    const [submitting, setSubmitting] = useState(false);

    const handleActionSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!actionModal.type) return;

        setSubmitting(true);
        let url = '';
        let payloadKey = 'notes';

        if (actionModal.type === 'approve') url = fraudScore.approve_url;
        else if (actionModal.type === 'reject') url = fraudScore.reject_url;
        else if (actionModal.type === 'review') url = fraudScore.review_url;
        else if (actionModal.type === 'note') {
            url = fraudScore.note_url;
            payloadKey = 'note';
        }

        router.post(
            url,
            { [payloadKey]: actionModal.notes },
            {
                onSuccess: () => {
                    setActionModal({ open: false, type: null, notes: '' });
                },
                onFinish: () => setSubmitting(false),
            },
        );
    };

    return (
        <>
            <Head title={`Fraud Analysis — Order ${fraudScore.order_number}`} />
            <AdminPage>
                {/* Back to Queue Navigation */}
                <div className="mb-2">
                    <Link
                        href={queueUrl}
                        className="inline-flex items-center text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
                    >
                        <ArrowLeft className="mr-1.5 size-4" /> Back to Fraud
                        Queue
                    </Link>
                </div>

                <PageHeader
                    title={`Fraud Analysis: Order ${fraudScore.order_number}`}
                    description={`Placed ${fraudScore.created_date} (${fraudScore.created_at}) for ${fraudScore.currency} ${fraudScore.order_total}`}
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            {fraudScore.review_status !== 'approved' && (
                                <Button
                                    variant="outline"
                                    className="border-emerald-600/30 text-emerald-600 hover:bg-emerald-50 hover:text-emerald-700 dark:hover:bg-emerald-950/30"
                                    onClick={() =>
                                        setActionModal({
                                            open: true,
                                            type: 'approve',
                                            notes: '',
                                        })
                                    }
                                >
                                    <CheckCircle2 className="mr-1.5 size-4" />{' '}
                                    Approve Order
                                </Button>
                            )}
                            {fraudScore.review_status !== 'under_review' && (
                                <Button
                                    variant="outline"
                                    className="border-blue-600/30 text-blue-600 hover:bg-blue-50 hover:text-blue-700 dark:hover:bg-blue-950/30"
                                    onClick={() =>
                                        setActionModal({
                                            open: true,
                                            type: 'review',
                                            notes: '',
                                        })
                                    }
                                >
                                    <Clock className="mr-1.5 size-4" /> Mark
                                    Under Review
                                </Button>
                            )}
                            {fraudScore.review_status !== 'rejected' && (
                                <Button
                                    variant="destructive"
                                    onClick={() =>
                                        setActionModal({
                                            open: true,
                                            type: 'reject',
                                            notes: '',
                                        })
                                    }
                                >
                                    <XCircle className="mr-1.5 size-4" /> Reject
                                    Order
                                </Button>
                            )}
                            <Button
                                variant="secondary"
                                onClick={() =>
                                    setActionModal({
                                        open: true,
                                        type: 'note',
                                        notes: '',
                                    })
                                }
                            >
                                <FileText className="mr-1.5 size-4" /> Add Note
                            </Button>
                        </div>
                    }
                />

                {/* Main Overview Grid */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* Column 1: Risk Assessment Overview */}
                    <Card className="shadow-sm lg:col-span-1">
                        <CardHeader>
                            <CardTitle className="flex items-center justify-between text-base font-semibold">
                                <span>Risk Evaluation</span>
                                {riskBadge(fraudScore.risk_level)}
                            </CardTitle>
                            <CardDescription>
                                Overall machine-evaluated threat score
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col items-center pb-6">
                            <ScoreGauge score={fraudScore.total_score} />

                            <div className="mt-4 text-center">
                                <div className="text-sm font-semibold capitalize">
                                    {fraudScore.risk_level} Risk Transaction
                                </div>
                                <p className="mt-0.5 text-xs text-muted-foreground">
                                    {fraudScore.total_score >= 66
                                        ? 'High probability of fraud. Immediate review required.'
                                        : fraudScore.total_score >= 31
                                          ? 'Moderate risk indicators flagged. Review recommended.'
                                          : 'Low risk. Transaction appears genuine.'}
                                </p>
                            </div>

                            <div className="mt-6 w-full space-y-3 rounded-lg border bg-muted/20 p-4 text-xs">
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">
                                        Review Status
                                    </span>
                                    {statusBadge(fraudScore.review_status)}
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">
                                        Customer Email
                                    </span>
                                    <span className="font-medium text-foreground">
                                        {fraudScore.customer_email}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">
                                        Order Value
                                    </span>
                                    <span className="font-semibold text-foreground">
                                        {fraudScore.currency}{' '}
                                        {fraudScore.order_total}
                                    </span>
                                </div>
                                {fraudScore.reviewed_by && (
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground">
                                            Reviewed By
                                        </span>
                                        <span className="font-medium">
                                            {fraudScore.reviewed_by}
                                        </span>
                                    </div>
                                )}
                                {fraudScore.reviewed_at && (
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground">
                                            Reviewed Date
                                        </span>
                                        <span>{fraudScore.reviewed_at}</span>
                                    </div>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Column 2 & 3: Signals & Indicators */}
                    <div className="space-y-6 lg:col-span-2">
                        {/* Security Signals Matrix */}
                        <Card className="shadow-sm">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base font-semibold">
                                    <Shield className="size-4 text-primary" />{' '}
                                    Key Security Signals
                                </CardTitle>
                                <CardDescription>
                                    Automated verification tests conducted at
                                    checkout
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    {/* VPN / Proxy */}
                                    <div className="flex items-start gap-3 rounded-lg border bg-card p-3.5">
                                        <div
                                            className={`mt-0.5 rounded-full p-2 ${fraudScore.is_vpn || fraudScore.is_proxy ? 'bg-red-500/10 text-red-600' : 'bg-emerald-500/10 text-emerald-600'}`}
                                        >
                                            <Server className="size-4" />
                                        </div>
                                        <div>
                                            <div className="text-sm font-medium">
                                                VPN / Proxy Detection
                                            </div>
                                            <div className="mt-0.5 text-xs text-muted-foreground">
                                                {fraudScore.is_vpn ||
                                                fraudScore.is_proxy
                                                    ? 'IP anonymizer or proxy connection detected.'
                                                    : 'Clean residential/commercial connection.'}
                                            </div>
                                            <div className="mt-2 font-mono text-xs">
                                                IP:{' '}
                                                {fraudScore.ip_address ||
                                                    'Unknown'}
                                            </div>
                                        </div>
                                    </div>

                                    {/* Disposable Email */}
                                    <div className="flex items-start gap-3 rounded-lg border bg-card p-3.5">
                                        <div
                                            className={`mt-0.5 rounded-full p-2 ${fraudScore.is_disposable_email ? 'bg-red-500/10 text-red-600' : 'bg-emerald-500/10 text-emerald-600'}`}
                                        >
                                            <Mail className="size-4" />
                                        </div>
                                        <div>
                                            <div className="text-sm font-medium">
                                                Email Deliverability
                                            </div>
                                            <div className="mt-0.5 text-xs text-muted-foreground">
                                                {fraudScore.is_disposable_email
                                                    ? 'Temporary or disposable email address used.'
                                                    : 'Standard verified email domain.'}
                                            </div>
                                            <div className="mt-2 max-w-[200px] truncate font-mono text-xs">
                                                {fraudScore.customer_email}
                                            </div>
                                        </div>
                                    </div>

                                    {/* Address Mismatch */}
                                    <div className="flex items-start gap-3 rounded-lg border bg-card p-3.5">
                                        <div
                                            className={`mt-0.5 rounded-full p-2 ${fraudScore.address_mismatch ? 'bg-amber-500/10 text-amber-600' : 'bg-emerald-500/10 text-emerald-600'}`}
                                        >
                                            <MapPin className="size-4" />
                                        </div>
                                        <div>
                                            <div className="text-sm font-medium">
                                                Address Validation
                                            </div>
                                            <div className="mt-0.5 text-xs text-muted-foreground">
                                                {fraudScore.address_mismatch
                                                    ? 'Billing and shipping address mismatch.'
                                                    : 'Billing and shipping addresses match.'}
                                            </div>
                                        </div>
                                    </div>

                                    {/* Payment & Location Geo */}
                                    <div className="flex items-start gap-3 rounded-lg border bg-card p-3.5">
                                        <div
                                            className={`mt-0.5 rounded-full p-2 ${
                                                fraudScore.customer_country &&
                                                fraudScore.payment_country &&
                                                fraudScore.customer_country !==
                                                    fraudScore.payment_country
                                                    ? 'bg-amber-500/10 text-amber-600'
                                                    : 'bg-emerald-500/10 text-emerald-600'
                                            }`}
                                        >
                                            <Globe className="size-4" />
                                        </div>
                                        <div>
                                            <div className="text-sm font-medium">
                                                Geo Cross-Match
                                            </div>
                                            <div className="mt-0.5 text-xs text-muted-foreground">
                                                Customer:{' '}
                                                <span className="font-semibold text-foreground">
                                                    {fraudScore.customer_country ||
                                                        'N/A'}
                                                </span>
                                                {' • '}
                                                Card:{' '}
                                                <span className="font-semibold text-foreground">
                                                    {fraudScore.payment_country ||
                                                        'N/A'}
                                                </span>
                                            </div>
                                            {fraudScore.failed_payment_attempts >
                                                0 && (
                                                <div className="mt-2 flex items-center gap-1 text-xs font-medium text-red-600 dark:text-red-400">
                                                    <CreditCard className="size-3" />
                                                    {
                                                        fraudScore.failed_payment_attempts
                                                    }{' '}
                                                    failed payment attempt(s)
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Detailed Indicators Breakdown */}
                        <Card className="shadow-sm">
                            <CardHeader>
                                <CardTitle className="flex items-center justify-between text-base font-semibold">
                                    <span>
                                        Triggered Risk Indicators (
                                        {fraudScore.indicators?.length || 0})
                                    </span>
                                </CardTitle>
                                <CardDescription>
                                    Breakdown of individual point additions
                                    contributing to total fraud score
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {fraudScore.indicators &&
                                fraudScore.indicators.length > 0 ? (
                                    <div className="divide-y rounded-lg border">
                                        {fraudScore.indicators.map(
                                            (ind, idx) => (
                                                <div
                                                    key={idx}
                                                    className="flex items-center justify-between p-3.5 text-sm transition-colors hover:bg-muted/10"
                                                >
                                                    <div className="space-y-0.5">
                                                        <div className="flex items-center gap-2 font-medium text-foreground">
                                                            <AlertCircle className="size-4 text-amber-500" />
                                                            {ind.label}
                                                        </div>
                                                        <div className="text-xs text-muted-foreground">
                                                            {ind.details}
                                                        </div>
                                                    </div>
                                                    <div className="flex items-center gap-1.5 pl-4 font-bold whitespace-nowrap text-red-600 dark:text-red-400">
                                                        <TrendingUp className="size-3.5" />
                                                        +{ind.score} pts
                                                    </div>
                                                </div>
                                            ),
                                        )}
                                    </div>
                                ) : (
                                    <div className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                                        No high-risk indicators were triggered
                                        for this transaction.
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>

                {/* Bottom Row: Customer Intelligence & Review History */}
                <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {/* Customer Intelligence Profile */}
                    <Card className="shadow-sm">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base font-semibold">
                                <UserCheck className="size-4 text-primary" />{' '}
                                Customer Profile & Reputation
                            </CardTitle>
                            <CardDescription>
                                Historical trust and order track record for this
                                buyer
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {customerProfile ? (
                                <div className="space-y-4">
                                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                        <div className="rounded-lg border bg-muted/20 p-3 text-center">
                                            <div className="text-xs text-muted-foreground">
                                                Trust Score
                                            </div>
                                            <div className="mt-0.5 text-xl font-bold text-foreground">
                                                {customerProfile.trust_score}
                                                /100
                                            </div>
                                        </div>
                                        <div className="rounded-lg border bg-muted/20 p-3 text-center">
                                            <div className="text-xs text-muted-foreground">
                                                Total Orders
                                            </div>
                                            <div className="mt-0.5 text-xl font-bold text-foreground">
                                                {customerProfile.total_orders}
                                            </div>
                                        </div>
                                        <div className="rounded-lg border bg-muted/20 p-3 text-center">
                                            <div className="text-xs text-muted-foreground">
                                                Approved
                                            </div>
                                            <div className="mt-0.5 text-xl font-bold text-emerald-600">
                                                {
                                                    customerProfile.approved_orders
                                                }
                                            </div>
                                        </div>
                                        <div className="rounded-lg border bg-muted/20 p-3 text-center">
                                            <div className="text-xs text-muted-foreground">
                                                Rejected
                                            </div>
                                            <div className="mt-0.5 text-xl font-bold text-red-600">
                                                {
                                                    customerProfile.rejected_orders
                                                }
                                            </div>
                                        </div>
                                    </div>

                                    {customerProfile.notes && (
                                        <div className="rounded-lg border bg-muted/10 p-3 text-xs text-muted-foreground">
                                            <span className="font-semibold text-foreground">
                                                Customer Notes:{' '}
                                            </span>
                                            {customerProfile.notes}
                                        </div>
                                    )}
                                </div>
                            ) : (
                                <div className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                                    First-time buyer or guest checkout. No prior
                                    customer history recorded.
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Investigation Timeline & Action Logs */}
                    <Card className="shadow-sm">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base font-semibold">
                                <History className="size-4 text-primary" />{' '}
                                Investigation Log & Audit Trail
                            </CardTitle>
                            <CardDescription>
                                Actions, analyst notes, and status updates for
                                this order
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {reviews && reviews.length > 0 ? (
                                <div className="relative ml-2 space-y-4 border-l-2 border-muted-foreground/20 pl-6">
                                    {reviews.map((rev) => (
                                        <div key={rev.id} className="relative">
                                            <div className="absolute top-1 -left-[31px] size-3.5 rounded-full border-2 border-background bg-primary" />
                                            <div className="flex items-center justify-between text-xs">
                                                <span className="font-semibold text-foreground">
                                                    {rev.user_name}
                                                </span>
                                                <span className="text-muted-foreground">
                                                    {rev.created_at}
                                                </span>
                                            </div>
                                            <div className="mt-1 text-xs text-muted-foreground">
                                                <span className="font-medium text-foreground capitalize">
                                                    {rev.action.replace(
                                                        '_',
                                                        ' ',
                                                    )}
                                                </span>
                                                {rev.new_status && (
                                                    <span className="ml-1">
                                                        → Status changed to{' '}
                                                        <span className="font-mono">
                                                            {rev.new_status}
                                                        </span>
                                                    </span>
                                                )}
                                            </div>
                                            {rev.comment && (
                                                <div className="mt-1.5 rounded border bg-muted/30 p-2.5 text-xs text-foreground italic">
                                                    "{rev.comment}"
                                                </div>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                                    No review actions recorded yet. Order is
                                    awaiting manual investigation or automated
                                    processing.
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Review Action Dialog */}
                <Dialog
                    open={actionModal.open}
                    onOpenChange={(open) =>
                        !open &&
                        setActionModal({ open: false, type: null, notes: '' })
                    }
                >
                    <DialogContent className="sm:max-w-md">
                        <form onSubmit={handleActionSubmit}>
                            <DialogHeader>
                                <DialogTitle className="capitalize">
                                    {actionModal.type === 'approve' &&
                                        'Approve Order'}
                                    {actionModal.type === 'reject' &&
                                        'Reject Order for Fraud'}
                                    {actionModal.type === 'review' &&
                                        'Mark as Under Review'}
                                    {actionModal.type === 'note' &&
                                        'Add Investigation Note'}
                                </DialogTitle>
                                <DialogDescription>
                                    {actionModal.type === 'approve' &&
                                        'Mark this order as verified safe for fulfillment. This action will update customer trust scores.'}
                                    {actionModal.type === 'reject' &&
                                        'Flag and cancel this order due to fraudulent risk. Optionally add customer notes.'}
                                    {actionModal.type === 'review' &&
                                        'Hold this order pending manual verification from your fraud or finance team.'}
                                    {actionModal.type === 'note' &&
                                        'Add internal investigation details or proof gathered for other analysts.'}
                                </DialogDescription>
                            </DialogHeader>

                            <div className="space-y-2 py-4">
                                <label className="text-xs font-semibold text-muted-foreground">
                                    {actionModal.type === 'note'
                                        ? 'Investigation Note'
                                        : 'Optional Explanation / Reason'}
                                </label>
                                <Textarea
                                    rows={4}
                                    placeholder={
                                        actionModal.type === 'approve'
                                            ? 'e.g. Spoke with customer by phone, confirmed card ownership...'
                                            : actionModal.type === 'reject'
                                              ? 'e.g. Cardholder reported stolen card, shipping address in high-risk forwarding center...'
                                              : 'Add your observations here...'
                                    }
                                    value={actionModal.notes}
                                    onChange={(e) =>
                                        setActionModal((prev) => ({
                                            ...prev,
                                            notes: e.target.value,
                                        }))
                                    }
                                    required={actionModal.type === 'note'}
                                />
                            </div>

                            <DialogFooter className="flex gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() =>
                                        setActionModal({
                                            open: false,
                                            type: null,
                                            notes: '',
                                        })
                                    }
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={submitting}
                                    variant={
                                        actionModal.type === 'reject'
                                            ? 'destructive'
                                            : 'default'
                                    }
                                >
                                    {submitting ? 'Saving...' : 'Confirm'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </AdminPage>
        </>
    );
}
