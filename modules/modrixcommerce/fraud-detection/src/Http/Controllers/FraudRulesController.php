<?php

namespace EcoModular\FraudDetection\Http\Controllers;

use App\Core\Audit\AuditLogger;
use App\Http\Controllers\Controller;
use EcoModular\FraudDetection\Models\FraudAuditLog;
use EcoModular\FraudDetection\Models\FraudRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FraudRulesController extends Controller
{
    private const ROUTE = 'admin.modrixcommerce.fraud-detection.rules.';

    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function index(): Response
    {
        $rules = FraudRule::query()
            ->with('creator')
            ->orderBy('priority')
            ->orderByDesc('id')
            ->get()
            ->map(fn (FraudRule $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'description' => $r->description,
                'type' => $r->type,
                'field' => $r->field,
                'operator' => $r->operator,
                'value' => $r->value,
                'score_impact' => $r->score_impact,
                'is_active' => $r->is_active,
                'priority' => $r->priority,
                'created_by' => $r->creator?->name,
                'created_at' => $r->created_at?->toFormattedDateString(),
                'update_url' => route(self::ROUTE . 'update', $r),
                'delete_url' => route(self::ROUTE . 'destroy', $r),
                'toggle_url' => route(self::ROUTE . 'toggle', $r),
            ]);

        return Inertia::render('module/modrixcommerce/fraud-detection/admin/rules', [
            'rules' => $rules,
            'storeUrl' => route(self::ROUTE . 'store'),
            'operators' => FraudRule::OPERATORS,
            'fields' => FraudRule::FIELDS,
            'types' => [FraudRule::TYPE_THRESHOLD, FraudRule::TYPE_PATTERN, FraudRule::TYPE_COUNTRY, FraudRule::TYPE_CUSTOM],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:500',
            'type' => 'required|string|in:threshold,pattern,country,custom',
            'field' => 'required|string|max:64',
            'operator' => 'required|string|in:' . implode(',', FraudRule::OPERATORS),
            'value' => 'required|string|max:2000',
            'score_impact' => 'required|integer|min:1|max:100',
            'priority' => 'integer|min:1|max:1000',
        ]);

        $rule = FraudRule::create([
            ...$validated,
            'priority' => $validated['priority'] ?? 100,
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);

        FraudAuditLog::record('rule.created', 'fraud_rule', $rule->id, [
            'name' => $rule->name,
            'type' => $rule->type,
        ]);

        $this->audit->record('fraud.rule_created', null, ['rule_id' => $rule->id, 'name' => $rule->name]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Rule \"{$rule->name}\" created."]);

        return back();
    }

    public function update(Request $request, FraudRule $fraudRule): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:500',
            'type' => 'required|string|in:threshold,pattern,country,custom',
            'field' => 'required|string|max:64',
            'operator' => 'required|string|in:' . implode(',', FraudRule::OPERATORS),
            'value' => 'required|string|max:2000',
            'score_impact' => 'required|integer|min:1|max:100',
            'priority' => 'integer|min:1|max:1000',
        ]);

        $fraudRule->update($validated);

        FraudAuditLog::record('rule.updated', 'fraud_rule', $fraudRule->id, ['name' => $fraudRule->name]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Rule \"{$fraudRule->name}\" updated."]);

        return back();
    }

    public function destroy(FraudRule $fraudRule): RedirectResponse
    {
        $name = $fraudRule->name;
        $id = $fraudRule->id;
        $fraudRule->delete();

        FraudAuditLog::record('rule.deleted', 'fraud_rule', $id, ['name' => $name]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Rule \"{$name}\" deleted."]);

        return back();
    }

    public function toggle(FraudRule $fraudRule): RedirectResponse
    {
        $fraudRule->update(['is_active' => ! $fraudRule->is_active]);

        FraudAuditLog::record('rule.toggled', 'fraud_rule', $fraudRule->id, [
            'name' => $fraudRule->name,
            'is_active' => $fraudRule->is_active,
        ]);

        $state = $fraudRule->is_active ? 'enabled' : 'disabled';
        Inertia::flash('toast', ['type' => 'success', 'message' => "Rule \"{$fraudRule->name}\" {$state}."]);

        return back();
    }
}
