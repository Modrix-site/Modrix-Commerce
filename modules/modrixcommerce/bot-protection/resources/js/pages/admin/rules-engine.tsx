import { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { Layers, Plus, Sparkles, Trash2, X } from 'lucide-react';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type Rule = {
    id: number;
    name: string;
    description: string | null;
    rule_type: string;
    conditions: Record<string, any>;
    action: string;
    priority: number;
    is_active: boolean;
    hits_count: number;
    created_at: string;
};

type RuleTemplate = {
    name: string;
    rule_type: string;
    description: string;
    action: string;
    conditions: Record<string, any>;
};

type Props = {
    rules: Rule[];
    ruleTemplates: RuleTemplate[];
};

export default function RulesEngine({ rules = [], ruleTemplates = [] }: Props) {
    const [showCreate, setShowCreate] = useState(false);

    const form = useForm({
        name: '',
        description: '',
        rule_type: 'path_pattern',
        action: 'block',
        priority: 10,
        path_pattern: '',
        user_agent_regex: '',
        missing_header: '',
        methods: [] as string[],
    });

    const handleCreateRule = (e: React.FormEvent) => {
        e.preventDefault();

        const conditions: Record<string, any> = {};
        if (form.data.path_pattern)
            conditions.path_pattern = form.data.path_pattern;
        if (form.data.user_agent_regex)
            conditions.user_agent_regex = form.data.user_agent_regex;
        if (form.data.missing_header)
            conditions.missing_header = form.data.missing_header;
        if (form.data.methods.length > 0)
            conditions.methods = form.data.methods;

        form.transform((data) => ({
            name: data.name,
            description: data.description,
            rule_type: data.rule_type,
            action: data.action,
            priority: data.priority,
            conditions,
        }));
        form.post('/admin/m/modrixcommerce/bot-protection/rules', {
            onSuccess: () => {
                setShowCreate(false);
                form.reset();
            },
        });
    };

    const applyTemplate = (template: RuleTemplate) => {
        form.setData({
            name: template.name,
            description: template.description,
            rule_type: template.rule_type,
            action: template.action,
            priority: 10,
            path_pattern: template.conditions.path_pattern || '',
            user_agent_regex: template.conditions.user_agent_regex || '',
            missing_header: template.conditions.missing_header || '',
            methods: template.conditions.methods || [],
        });
        setShowCreate(true);
    };

    const actionBadge = (action: string) => {
        switch (action) {
            case 'block':
                return (
                    <Badge className="border-rose-500/20 bg-rose-500/15 text-rose-600">
                        Block (403)
                    </Badge>
                );
            case 'challenge':
                return (
                    <Badge className="border-amber-500/20 bg-amber-500/15 text-amber-600">
                        Challenge (Captcha)
                    </Badge>
                );
            case 'throttle':
                return (
                    <Badge className="border-purple-500/20 bg-purple-500/15 text-purple-600">
                        Throttle (429)
                    </Badge>
                );
            default:
                return <Badge variant="outline">Monitor Only</Badge>;
        }
    };

    return (
        <AdminPage>
            <Head title="Bot Protection - Rules Engine" />

            <PageHeader
                title="Security Rules Engine"
                description="Configure custom deterministic policies, URL pattern locks, and automated threat responses."
                actions={
                    <Button
                        onClick={() => setShowCreate(true)}
                        className="gap-2"
                    >
                        <Plus className="h-4 w-4" /> Create Custom Rule
                    </Button>
                }
            />

            {/* Quick Templates Banner */}
            <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {ruleTemplates.map((t, idx) => (
                    <Card
                        key={idx}
                        className="border border-border/60 bg-gradient-to-br from-card to-muted/20 shadow-sm transition hover:shadow"
                    >
                        <CardContent className="flex h-full flex-col justify-between p-4">
                            <div>
                                <div className="mb-2 flex items-center justify-between">
                                    <Badge
                                        variant="outline"
                                        className="text-[10px] font-bold text-primary uppercase"
                                    >
                                        Template
                                    </Badge>
                                    {actionBadge(t.action)}
                                </div>
                                <h4 className="mb-1 text-xs font-semibold text-foreground">
                                    {t.name}
                                </h4>
                                <p className="mb-3 line-clamp-2 text-[11px] text-muted-foreground">
                                    {t.description}
                                </p>
                            </div>
                            <Button
                                size="sm"
                                variant="outline"
                                className="h-7 w-full gap-1 text-xs"
                                onClick={() => applyTemplate(t)}
                            >
                                <Sparkles className="h-3 w-3 text-amber-500" />{' '}
                                Deploy Rule
                            </Button>
                        </CardContent>
                    </Card>
                ))}
            </div>

            {/* Active Rules Table */}
            <Card className="border border-border/60 shadow-sm">
                <CardContent className="p-6">
                    <div className="overflow-x-auto">
                        <table className="w-full text-xs">
                            <thead>
                                <tr className="border-b border-border/60 text-left text-muted-foreground">
                                    <th className="pb-3 font-medium">
                                        Priority
                                    </th>
                                    <th className="pb-3 font-medium">
                                        Rule Name
                                    </th>
                                    <th className="pb-3 font-medium">
                                        Conditions Summary
                                    </th>
                                    <th className="pb-3 font-medium">
                                        Enforcement Action
                                    </th>
                                    <th className="pb-3 text-right font-medium">
                                        Triggered Hits
                                    </th>
                                    <th className="pb-3 text-center font-medium">
                                        Status
                                    </th>
                                    <th className="pb-3 text-right font-medium">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border/30">
                                {rules.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={7}
                                            className="py-10 text-center text-muted-foreground"
                                        >
                                            No custom security rules defined
                                            yet. Click "Create Custom Rule" or
                                            deploy a template above.
                                        </td>
                                    </tr>
                                ) : (
                                    rules.map((rule) => (
                                        <tr
                                            key={rule.id}
                                            className="transition hover:bg-muted/30"
                                        >
                                            <td className="py-3 font-mono font-bold text-muted-foreground">
                                                #{rule.priority}
                                            </td>
                                            <td className="py-3">
                                                <div className="font-semibold text-foreground">
                                                    {rule.name}
                                                </div>
                                                <div className="max-w-xs truncate text-[11px] text-muted-foreground">
                                                    {rule.description}
                                                </div>
                                            </td>
                                            <td className="py-3 font-mono text-[11px] text-muted-foreground">
                                                {Object.entries(
                                                    rule.conditions || {},
                                                ).map(([k, v]) => (
                                                    <span
                                                        key={k}
                                                        className="mr-2 inline-block rounded bg-muted px-1.5 py-0.5"
                                                    >
                                                        {k}:{' '}
                                                        {Array.isArray(v)
                                                            ? v.join(', ')
                                                            : String(v)}
                                                    </span>
                                                ))}
                                            </td>
                                            <td className="py-3">
                                                {actionBadge(rule.action)}
                                            </td>
                                            <td className="py-3 text-right font-mono font-bold text-foreground">
                                                {rule.hits_count.toLocaleString()}
                                            </td>
                                            <td className="py-3 text-center">
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    className={`h-6 px-2 text-[10px] font-semibold ${
                                                        rule.is_active
                                                            ? 'bg-emerald-500/10 text-emerald-600'
                                                            : 'bg-muted text-muted-foreground'
                                                    }`}
                                                    onClick={() =>
                                                        router.post(
                                                            `/admin/m/modrixcommerce/bot-protection/rules/${rule.id}/toggle`,
                                                        )
                                                    }
                                                >
                                                    {rule.is_active
                                                        ? 'Active'
                                                        : 'Disabled'}
                                                </Button>
                                            </td>
                                            <td className="py-3 text-right">
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    className="h-7 px-2 text-rose-600 hover:bg-rose-500/10"
                                                    onClick={() =>
                                                        router.delete(
                                                            `/admin/m/modrixcommerce/bot-protection/rules/${rule.id}`,
                                                        )
                                                    }
                                                >
                                                    <Trash2 className="h-3.5 w-3.5" />
                                                </Button>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            {/* CREATE RULE MODAL */}
            {showCreate && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
                    <div className="relative max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl border border-border bg-card p-6 shadow-2xl">
                        <button
                            onClick={() => setShowCreate(false)}
                            className="absolute top-4 right-4 text-muted-foreground hover:text-foreground"
                        >
                            <X className="h-5 w-5" />
                        </button>

                        <h3 className="mb-4 flex items-center gap-2 text-base font-bold text-foreground">
                            <Layers className="h-5 w-5 text-primary" /> Create
                            Security Defense Rule
                        </h3>

                        <form
                            onSubmit={handleCreateRule}
                            className="space-y-4 text-xs"
                        >
                            <div>
                                <label className="mb-1 block font-semibold">
                                    Rule Name
                                </label>
                                <Input
                                    placeholder="e.g. Block Malicious Catalog Scraping"
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData('name', e.target.value)
                                    }
                                    required
                                />
                            </div>

                            <div>
                                <label className="mb-1 block font-semibold">
                                    Description (Optional)
                                </label>
                                <Input
                                    placeholder="Brief explanation of threat policy"
                                    value={form.data.description}
                                    onChange={(e) =>
                                        form.setData(
                                            'description',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="mb-1 block font-semibold">
                                        Action to Take
                                    </label>
                                    <select
                                        className="h-9 w-full rounded-md border border-input bg-background px-3 text-xs"
                                        value={form.data.action}
                                        onChange={(e) =>
                                            form.setData(
                                                'action',
                                                e.target.value,
                                            )
                                        }
                                    >
                                        <option value="block">
                                            Block (403 Forbidden)
                                        </option>
                                        <option value="challenge">
                                            Challenge (Captcha)
                                        </option>
                                        <option value="throttle">
                                            Throttle (429 Too Many Requests)
                                        </option>
                                        <option value="monitor">
                                            Monitor (Log Only)
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label className="mb-1 block font-semibold">
                                        Priority Rank (1-100)
                                    </label>
                                    <Input
                                        type="number"
                                        value={form.data.priority}
                                        onChange={(e) =>
                                            form.setData(
                                                'priority',
                                                parseInt(e.target.value) || 10,
                                            )
                                        }
                                    />
                                </div>
                            </div>

                            <div className="space-y-3 rounded-lg bg-muted/40 p-3">
                                <h4 className="text-[11px] font-bold tracking-wider text-muted-foreground uppercase">
                                    Conditions (Match Any)
                                </h4>
                                <div>
                                    <label className="mb-1 block font-medium">
                                        Path Pattern
                                    </label>
                                    <Input
                                        placeholder="e.g. /products* or /api/*"
                                        value={form.data.path_pattern}
                                        onChange={(e) =>
                                            form.setData(
                                                'path_pattern',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <div>
                                    <label className="mb-1 block font-medium">
                                        User-Agent Regex
                                    </label>
                                    <Input
                                        placeholder="e.g. /(curl|python|scrapy)/i"
                                        value={form.data.user_agent_regex}
                                        onChange={(e) =>
                                            form.setData(
                                                'user_agent_regex',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <div>
                                    <label className="mb-1 block font-medium">
                                        Required Missing Header
                                    </label>
                                    <Input
                                        placeholder="e.g. accept-language"
                                        value={form.data.missing_header}
                                        onChange={(e) =>
                                            form.setData(
                                                'missing_header',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                            </div>

                            <div className="flex justify-end gap-2 border-t border-border pt-3">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setShowCreate(false)}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    Save & Enable Rule
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AdminPage>
    );
}
