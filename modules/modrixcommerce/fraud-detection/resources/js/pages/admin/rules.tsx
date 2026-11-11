import { Head, router } from '@inertiajs/react';
import { Edit2, Plus, Power, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { EmptyState } from '@/components/admin/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

type Rule = {
    id: number;
    name: string;
    description: string | null;
    type: 'threshold' | 'pattern' | 'country' | 'custom' | (string & {});
    field: string;
    operator: string;
    value: string;
    score_impact: number;
    is_active: boolean;
    priority: number;
    created_by: string | null;
    created_at: string;
    update_url: string;
    delete_url: string;
    toggle_url: string;
};

type Props = {
    rules: Rule[];
    storeUrl: string;
    operators: string[];
    fields: string[];
    types: string[];
};

export default function FraudRules({
    rules,
    storeUrl,
    operators,
    fields,
    types,
}: Props) {
    const [search, setSearch] = useState('');
    const [typeFilter, setTypeFilter] = useState('all');
    const [modalOpen, setModalOpen] = useState(false);
    const [editingRule, setEditingRule] = useState<Rule | null>(null);
    const [deleteConfirm, setDeleteConfirm] = useState<Rule | null>(null);
    const [submitting, setSubmitting] = useState(false);

    const [form, setForm] = useState({
        name: '',
        description: '',
        type: 'threshold',
        field: 'order_total',
        operator: 'greater_than',
        value: '',
        score_impact: 20,
        priority: 100,
    });

    const openCreateModal = () => {
        setEditingRule(null);
        setForm({
            name: '',
            description: '',
            type: 'threshold',
            field: 'order_total',
            operator: 'greater_than',
            value: '',
            score_impact: 20,
            priority: 100,
        });
        setModalOpen(true);
    };

    const openEditModal = (rule: Rule) => {
        setEditingRule(rule);
        setForm({
            name: rule.name,
            description: rule.description || '',
            type: rule.type,
            field: rule.field,
            operator: rule.operator,
            value: rule.value,
            score_impact: rule.score_impact,
            priority: rule.priority,
        });
        setModalOpen(true);
    };

    const handleFormSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setSubmitting(true);

        if (editingRule) {
            router.put(editingRule.update_url, form, {
                onSuccess: () => setModalOpen(false),
                onFinish: () => setSubmitting(false),
            });
        } else {
            router.post(storeUrl, form, {
                onSuccess: () => setModalOpen(false),
                onFinish: () => setSubmitting(false),
            });
        }
    };

    const handleToggle = (rule: Rule) => {
        router.post(rule.toggle_url, {}, { preserveScroll: true });
    };

    const handleDelete = () => {
        if (!deleteConfirm) return;
        setSubmitting(true);
        router.delete(deleteConfirm.delete_url, {
            onSuccess: () => setDeleteConfirm(null),
            onFinish: () => setSubmitting(false),
        });
    };

    const filteredRules = rules.filter((r) => {
        const matchesSearch =
            r.name.toLowerCase().includes(search.toLowerCase()) ||
            (r.description &&
                r.description.toLowerCase().includes(search.toLowerCase())) ||
            r.field.toLowerCase().includes(search.toLowerCase());
        const matchesType = typeFilter === 'all' || r.type === typeFilter;
        return matchesSearch && matchesType;
    });

    return (
        <>
            <Head title="Fraud Detection — Custom Rules Engine" />
            <AdminPage>
                <PageHeader
                    title="Fraud Risk Rules"
                    description="Configure automated heuristic rules to customize scoring based on order value, country codes, patterns, and velocity."
                    actions={
                        <Button onClick={openCreateModal}>
                            <Plus className="mr-1.5 size-4" /> Add New Rule
                        </Button>
                    }
                />

                {/* Filter & Search Bar */}
                <Card className="mb-6 shadow-sm">
                    <CardContent className="p-4">
                        <div className="flex flex-col items-center justify-between gap-4 sm:flex-row">
                            <div className="relative w-full sm:w-80">
                                <Search className="absolute top-2.5 left-3 size-4 text-muted-foreground" />
                                <Input
                                    placeholder="Search rules by name, field..."
                                    className="pl-9 text-sm"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                />
                            </div>

                            <div className="flex w-full items-center gap-2 sm:w-auto">
                                <span className="text-xs whitespace-nowrap text-muted-foreground">
                                    Filter Type:
                                </span>
                                <select
                                    className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm transition-colors focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                    value={typeFilter}
                                    onChange={(e) =>
                                        setTypeFilter(e.target.value)
                                    }
                                >
                                    <option value="all">All Rule Types</option>
                                    <option value="threshold">Threshold</option>
                                    <option value="pattern">Pattern</option>
                                    <option value="country">Country</option>
                                    <option value="custom">Custom</option>
                                </select>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Rules List */}
                {filteredRules.length === 0 ? (
                    <EmptyState
                        title="No rules found"
                        description={
                            search
                                ? 'No fraud rules match your search query.'
                                : 'You have not defined any custom rules yet. Create one to adapt fraud scoring to your business model.'
                        }
                        action={
                            <Button onClick={openCreateModal}>
                                <Plus className="mr-1.5 size-4" /> Create First
                                Rule
                            </Button>
                        }
                    />
                ) : (
                    <div className="space-y-3">
                        {filteredRules.map((rule) => (
                            <Card
                                key={rule.id}
                                className={`shadow-sm transition-all ${
                                    !rule.is_active
                                        ? 'bg-muted/20 opacity-60'
                                        : 'hover:border-primary/40'
                                }`}
                            >
                                <CardContent className="p-5">
                                    <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                                        <div className="max-w-2xl space-y-1.5">
                                            <div className="flex flex-wrap items-center gap-2.5">
                                                <span className="text-base font-semibold text-foreground">
                                                    {rule.name}
                                                </span>
                                                <Badge
                                                    variant="outline"
                                                    className="text-xs capitalize"
                                                >
                                                    {rule.type}
                                                </Badge>
                                                <Badge
                                                    className={
                                                        rule.is_active
                                                            ? 'border-emerald-200 bg-emerald-500/15 text-emerald-700 dark:text-emerald-400'
                                                            : 'bg-muted text-muted-foreground'
                                                    }
                                                >
                                                    {rule.is_active
                                                        ? 'Active'
                                                        : 'Disabled'}
                                                </Badge>
                                            </div>

                                            {rule.description && (
                                                <p className="text-xs text-muted-foreground">
                                                    {rule.description}
                                                </p>
                                            )}

                                            {/* Rule Expression */}
                                            <div className="flex items-center gap-2 pt-1 font-mono text-xs">
                                                <span className="text-muted-foreground">
                                                    IF
                                                </span>
                                                <span className="rounded bg-muted px-2 py-0.5 font-semibold text-foreground">
                                                    {rule.field}
                                                </span>
                                                <span className="text-muted-foreground uppercase">
                                                    {rule.operator.replace(
                                                        '_',
                                                        ' ',
                                                    )}
                                                </span>
                                                <span className="rounded bg-muted px-2 py-0.5 font-semibold text-foreground">
                                                    {rule.value}
                                                </span>
                                                <span className="text-muted-foreground">
                                                    THEN ADD
                                                </span>
                                                <span className="rounded bg-red-500/15 px-2 py-0.5 font-bold text-red-600">
                                                    +{rule.score_impact} pts
                                                </span>
                                            </div>
                                        </div>

                                        {/* Action buttons */}
                                        <div className="flex shrink-0 items-center gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    handleToggle(rule)
                                                }
                                                className={
                                                    rule.is_active
                                                        ? 'text-amber-600'
                                                        : 'text-emerald-600'
                                                }
                                                title={
                                                    rule.is_active
                                                        ? 'Disable rule'
                                                        : 'Activate rule'
                                                }
                                            >
                                                <Power className="mr-1.5 size-3.5" />
                                                {rule.is_active
                                                    ? 'Disable'
                                                    : 'Enable'}
                                            </Button>

                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    openEditModal(rule)
                                                }
                                            >
                                                <Edit2 className="mr-1.5 size-3.5" />{' '}
                                                Edit
                                            </Button>

                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="text-red-600 hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-950/20"
                                                onClick={() =>
                                                    setDeleteConfirm(rule)
                                                }
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}

                {/* Create / Edit Rule Modal */}
                <Dialog open={modalOpen} onOpenChange={setModalOpen}>
                    <DialogContent className="sm:max-w-lg">
                        <form onSubmit={handleFormSubmit}>
                            <DialogHeader>
                                <DialogTitle>
                                    {editingRule
                                        ? 'Edit Fraud Rule'
                                        : 'Create Custom Fraud Rule'}
                                </DialogTitle>
                                <DialogDescription>
                                    Define condition criteria and score
                                    penalties for orders processed through
                                    checkout.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="space-y-4 py-4 text-sm">
                                <div>
                                    <label className="mb-1 block text-xs font-semibold text-muted-foreground">
                                        Rule Name
                                    </label>
                                    <Input
                                        placeholder="e.g. Excessive Cart Value Threshold"
                                        value={form.name}
                                        onChange={(e) =>
                                            setForm({
                                                ...form,
                                                name: e.target.value,
                                            })
                                        }
                                        required
                                    />
                                </div>

                                <div>
                                    <label className="mb-1 block text-xs font-semibold text-muted-foreground">
                                        Description (Optional)
                                    </label>
                                    <Textarea
                                        rows={2}
                                        placeholder="Explain what this rule protects against..."
                                        value={form.description}
                                        onChange={(e) =>
                                            setForm({
                                                ...form,
                                                description: e.target.value,
                                            })
                                        }
                                    />
                                </div>

                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label className="mb-1 block text-xs font-semibold text-muted-foreground">
                                            Rule Type
                                        </label>
                                        <select
                                            className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm"
                                            value={form.type}
                                            onChange={(e) =>
                                                setForm({
                                                    ...form,
                                                    type: e.target.value,
                                                })
                                            }
                                        >
                                            {types.map((t) => (
                                                <option
                                                    key={t}
                                                    value={t}
                                                    className="capitalize"
                                                >
                                                    {t}
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    <div>
                                        <label className="mb-1 block text-xs font-semibold text-muted-foreground">
                                            Evaluation Field
                                        </label>
                                        <select
                                            className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm"
                                            value={form.field}
                                            onChange={(e) =>
                                                setForm({
                                                    ...form,
                                                    field: e.target.value,
                                                })
                                            }
                                        >
                                            {fields.map((f) => (
                                                <option key={f} value={f}>
                                                    {f}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                </div>

                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label className="mb-1 block text-xs font-semibold text-muted-foreground">
                                            Operator
                                        </label>
                                        <select
                                            className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm"
                                            value={form.operator}
                                            onChange={(e) =>
                                                setForm({
                                                    ...form,
                                                    operator: e.target.value,
                                                })
                                            }
                                        >
                                            {operators.map((op) => (
                                                <option key={op} value={op}>
                                                    {op.replace('_', ' ')}
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    <div>
                                        <label className="mb-1 block text-xs font-semibold text-muted-foreground">
                                            Target Value
                                        </label>
                                        <Input
                                            placeholder="e.g. 500, US, suspicious-keyword"
                                            value={form.value}
                                            onChange={(e) =>
                                                setForm({
                                                    ...form,
                                                    value: e.target.value,
                                                })
                                            }
                                            required
                                        />
                                    </div>
                                </div>

                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label className="mb-1 block text-xs font-semibold text-muted-foreground">
                                            Score Impact (Points)
                                        </label>
                                        <Input
                                            type="number"
                                            min={1}
                                            max={100}
                                            value={form.score_impact}
                                            onChange={(e) =>
                                                setForm({
                                                    ...form,
                                                    score_impact:
                                                        parseInt(
                                                            e.target.value,
                                                        ) || 1,
                                                })
                                            }
                                            required
                                        />
                                    </div>

                                    <div>
                                        <label className="mb-1 block text-xs font-semibold text-muted-foreground">
                                            Evaluation Priority
                                        </label>
                                        <Input
                                            type="number"
                                            min={1}
                                            max={1000}
                                            value={form.priority}
                                            onChange={(e) =>
                                                setForm({
                                                    ...form,
                                                    priority:
                                                        parseInt(
                                                            e.target.value,
                                                        ) || 100,
                                                })
                                            }
                                        />
                                    </div>
                                </div>
                            </div>

                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setModalOpen(false)}
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={submitting}>
                                    {submitting
                                        ? 'Saving...'
                                        : editingRule
                                          ? 'Update Rule'
                                          : 'Create Rule'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>

                {/* Delete Confirmation Modal */}
                <Dialog
                    open={!!deleteConfirm}
                    onOpenChange={(open) => !open && setDeleteConfirm(null)}
                >
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>Delete Fraud Rule?</DialogTitle>
                            <DialogDescription>
                                Are you sure you want to delete{' '}
                                <span className="font-semibold text-foreground">
                                    "{deleteConfirm?.name}"
                                </span>
                                ? Orders will no longer be scored against this
                                rule.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter className="flex gap-2">
                            <Button
                                variant="outline"
                                onClick={() => setDeleteConfirm(null)}
                            >
                                Cancel
                            </Button>
                            <Button
                                variant="destructive"
                                onClick={handleDelete}
                                disabled={submitting}
                            >
                                {submitting ? 'Deleting...' : 'Delete Rule'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </AdminPage>
        </>
    );
}
