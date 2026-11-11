<?php

namespace EcoModular\TieredPricing\Http;

use App\Core\Audit\AuditLogger;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Http\Controllers\Controller;
use EcoModular\TieredPricing\Models\TieredPricingRule;
use EcoModular\TieredPricing\TieredPricingSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin management for Tiered Pricing rules and settings.
 */
class TieredPricingAdminController extends Controller
{
    public function index(TieredPricingSettings $settings): Response
    {
        $rules = TieredPricingRule::query()
            ->orderByDesc('is_active')
            ->orderByDesc('priority')
            ->latest('id')
            ->paginate(15);

        return Inertia::render('module/modrixcommerce/tiered-pricing/admin/index', [
            'rules' => $rules,
            'settings' => $settings->toArray(),
            'stats' => [
                'total_rules' => TieredPricingRule::query()->count(),
                'active_rules' => TieredPricingRule::query()->where('is_active', true)->count(),
            ],
            'urls' => [
                'create' => route('admin.modrixcommerce.tiered-pricing.create'),
                'settings' => route('admin.modrixcommerce.tiered-pricing.settings'),
            ],
        ]);
    }

    public function create(): Response
    {
        $products = Product::query()
            ->visible()
            ->select(['id', 'name'])
            // The SKU lives on variants: show the first one's.
            ->addSelect(['sku' => ProductVariant::query()
                ->select('sku')->whereColumn('product_id', 'products.id')->orderBy('id')->limit(1)])
            ->limit(100)
            ->get();

        $categories = Category::query()
            ->select(['id', 'name'])
            ->get();

        return Inertia::render('module/modrixcommerce/tiered-pricing/admin/edit', [
            'rule' => null,
            'products' => $products,
            'categories' => $categories,
            'actionUrl' => route('admin.modrixcommerce.tiered-pricing.store'),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'applies_to' => ['required', 'in:product,category,all'],
            'target_ids' => ['nullable', 'array'],
            'target_ids.*' => ['integer'],
            'discount_type' => ['required', 'in:percent,fixed_per_unit'],
            'tiers' => ['required', 'array', 'min:1', 'max:10'],
            'tiers.*.min_qty' => ['required', 'integer', 'min:2'],
            'tiers.*.max_qty' => ['nullable', 'integer'],
            'tiers.*.discount' => ['required', 'numeric', 'min:0.01'],
            'priority' => ['required', 'integer'],
            'is_active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);
        $data['target_ids'] = $data['target_ids'] ?? [];

        $rule = TieredPricingRule::create($data);

        $audit->record('tiered_pricing.rule_created', null, [
            'rule_id' => $rule->id,
            'title' => $rule->title,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Tiered pricing rule created successfully.'),
        ]);

        return redirect()->route('admin.modrixcommerce.tiered-pricing.index');
    }

    public function edit(TieredPricingRule $rule): Response
    {
        $products = Product::query()
            ->visible()
            ->select(['id', 'name'])
            // The SKU lives on variants: show the first one's.
            ->addSelect(['sku' => ProductVariant::query()
                ->select('sku')->whereColumn('product_id', 'products.id')->orderBy('id')->limit(1)])
            ->limit(100)
            ->get();

        $categories = Category::query()
            ->select(['id', 'name'])
            ->get();

        return Inertia::render('module/modrixcommerce/tiered-pricing/admin/edit', [
            'rule' => $rule,
            'products' => $products,
            'categories' => $categories,
            'actionUrl' => route('admin.modrixcommerce.tiered-pricing.update', $rule),
        ]);
    }

    public function update(Request $request, TieredPricingRule $rule, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'applies_to' => ['required', 'in:product,category,all'],
            'target_ids' => ['nullable', 'array'],
            'target_ids.*' => ['integer'],
            'discount_type' => ['required', 'in:percent,fixed_per_unit'],
            'tiers' => ['required', 'array', 'min:1', 'max:10'],
            'tiers.*.min_qty' => ['required', 'integer', 'min:2'],
            'tiers.*.max_qty' => ['nullable', 'integer'],
            'tiers.*.discount' => ['required', 'numeric', 'min:0.01'],
            'priority' => ['required', 'integer'],
            'is_active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['target_ids'] = $data['target_ids'] ?? [];

        $rule->update($data);

        $audit->record('tiered_pricing.rule_updated', null, [
            'rule_id' => $rule->id,
            'title' => $rule->title,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Tiered pricing rule updated successfully.'),
        ]);

        return redirect()->route('admin.modrixcommerce.tiered-pricing.index');
    }

    public function destroy(TieredPricingRule $rule, AuditLogger $audit): RedirectResponse
    {
        $id = $rule->id;
        $title = $rule->title;
        $rule->delete();

        $audit->record('tiered_pricing.rule_deleted', null, [
            'rule_id' => $id,
            'title' => $title,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Rule deleted.'),
        ]);

        return redirect()->route('admin.modrixcommerce.tiered-pricing.index');
    }

    public function toggle(TieredPricingRule $rule): RedirectResponse
    {
        $rule->update(['is_active' => ! $rule->is_active]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $rule->is_active ? __('Rule enabled.') : __('Rule disabled.'),
        ]);

        return back(303);
    }

    public function settings(TieredPricingSettings $settings): Response
    {
        return Inertia::render('module/modrixcommerce/tiered-pricing/admin/settings', [
            'settings' => $settings->toArray(),
            'updateUrl' => route('admin.modrixcommerce.tiered-pricing.settings.update'),
        ]);
    }

    public function updateSettings(Request $request, TieredPricingSettings $settings, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['boolean'],
            'table_heading' => ['required', 'string', 'max:120'],
            'table_subheading' => ['nullable', 'string', 'max:255'],
            'show_savings_badge' => ['boolean'],
            'highlight_active_tier' => ['boolean'],
        ]);

        $data['enabled'] = $request->boolean('enabled');
        $data['show_savings_badge'] = $request->boolean('show_savings_badge');
        $data['highlight_active_tier'] = $request->boolean('highlight_active_tier');

        $settings->save($data);

        $audit->record('tiered_pricing.settings_updated', null, $data);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Tiered pricing settings saved.'),
        ]);

        return redirect()->route('admin.modrixcommerce.tiered-pricing.settings');
    }
}
