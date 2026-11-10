<?php

namespace EcoModular\BuyTogetherSave\Http;

use App\Core\Audit\AuditLogger;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Http\Controllers\Controller;
use EcoModular\BuyTogetherSave\BuyTogetherSettings;
use EcoModular\BuyTogetherSave\Models\BuyTogetherDeal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin management for Buy Together & Save deals and settings.
 */
class BuyTogetherAdminController extends Controller
{
    public function index(Request $request, BuyTogetherSettings $settings): Response
    {
        $deals = BuyTogetherDeal::query()
            ->orderByDesc('is_active')
            ->orderByDesc('priority')
            ->latest('id')
            ->paginate(15);

        $totalViews = BuyTogetherDeal::query()->sum('views_count');
        $totalConversions = BuyTogetherDeal::query()->sum('conversions_count');
        $conversionRate = $totalViews > 0 ? round(($totalConversions / $totalViews) * 100, 1) : 0.0;

        return Inertia::render('module/modrixcommerce/buy-together-save/admin/index', [
            'deals' => $deals,
            'settings' => $settings->toArray(),
            'stats' => [
                'total_deals' => BuyTogetherDeal::query()->count(),
                'active_deals' => BuyTogetherDeal::query()->where('is_active', true)->count(),
                'total_views' => $totalViews,
                'total_conversions' => $totalConversions,
                'conversion_rate' => $conversionRate,
            ],
            'urls' => [
                'create' => route('admin.modrixcommerce.buy-together-save.create'),
                'settings' => route('admin.modrixcommerce.buy-together-save.settings'),
            ],
        ]);
    }

    public function create(): Response
    {
        $products = Product::query()
            ->visible()
            ->select(['id', 'name', 'sku'])
            ->limit(100)
            ->get();

        $categories = Category::query()
            ->select(['id', 'name'])
            ->get();

        return Inertia::render('module/modrixcommerce/buy-together-save/admin/edit', [
            'deal' => null,
            'products' => $products,
            'categories' => $categories,
            'actionUrl' => route('admin.modrixcommerce.buy-together-save.store'),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'badge_text' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'trigger_type' => ['required', 'in:product,category,all'],
            'trigger_ids' => ['nullable', 'array'],
            'trigger_ids.*' => ['integer'],
            'bundle_product_ids' => ['required', 'array', 'min:1', 'max:6'],
            'bundle_product_ids.*' => ['integer'],
            'discount_type' => ['required', 'in:percent,fixed'],
            'discount_value' => ['required', 'numeric', 'min:0.01'],
            'min_items_required' => ['required', 'integer', 'min:2', 'max:6'],
            'priority' => ['required', 'integer'],
            'is_active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);
        $data['trigger_ids'] = $data['trigger_ids'] ?? [];

        $deal = BuyTogetherDeal::create($data);

        $audit->record('buy_together.deal_created', null, [
            'deal_id' => $deal->id,
            'title' => $deal->title,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Buy Together & Save deal created successfully.'),
        ]);

        return redirect()->route('admin.modrixcommerce.buy-together-save.index');
    }

    public function edit(BuyTogetherDeal $deal): Response
    {
        $products = Product::query()
            ->visible()
            ->select(['id', 'name', 'sku'])
            ->limit(100)
            ->get();

        $categories = Category::query()
            ->select(['id', 'name'])
            ->get();

        return Inertia::render('module/modrixcommerce/buy-together-save/admin/edit', [
            'deal' => $deal,
            'products' => $products,
            'categories' => $categories,
            'actionUrl' => route('admin.modrixcommerce.buy-together-save.update', $deal),
        ]);
    }

    public function update(Request $request, BuyTogetherDeal $deal, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'badge_text' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'trigger_type' => ['required', 'in:product,category,all'],
            'trigger_ids' => ['nullable', 'array'],
            'trigger_ids.*' => ['integer'],
            'bundle_product_ids' => ['required', 'array', 'min:1', 'max:6'],
            'bundle_product_ids.*' => ['integer'],
            'discount_type' => ['required', 'in:percent,fixed'],
            'discount_value' => ['required', 'numeric', 'min:0.01'],
            'min_items_required' => ['required', 'integer', 'min:2', 'max:6'],
            'priority' => ['required', 'integer'],
            'is_active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['trigger_ids'] = $data['trigger_ids'] ?? [];

        $deal->update($data);

        $audit->record('buy_together.deal_updated', null, [
            'deal_id' => $deal->id,
            'title' => $deal->title,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Deal updated successfully.'),
        ]);

        return redirect()->route('admin.modrixcommerce.buy-together-save.index');
    }

    public function destroy(BuyTogetherDeal $deal, AuditLogger $audit): RedirectResponse
    {
        $id = $deal->id;
        $title = $deal->title;
        $deal->delete();

        $audit->record('buy_together.deal_deleted', null, [
            'deal_id' => $id,
            'title' => $title,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Deal deleted.'),
        ]);

        return redirect()->route('admin.modrixcommerce.buy-together-save.index');
    }

    public function toggle(BuyTogetherDeal $deal): RedirectResponse
    {
        $deal->update(['is_active' => ! $deal->is_active]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $deal->is_active ? __('Deal enabled.') : __('Deal disabled.'),
        ]);

        return back(303);
    }

    public function settings(BuyTogetherSettings $settings): Response
    {
        return Inertia::render('module/modrixcommerce/buy-together-save/admin/settings', [
            'settings' => $settings->toArray(),
            'updateUrl' => route('admin.modrixcommerce.buy-together-save.settings.update'),
        ]);
    }

    public function updateSettings(Request $request, BuyTogetherSettings $settings, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['boolean'],
            'heading' => ['required', 'string', 'max:120'],
            'subheading' => ['nullable', 'string', 'max:255'],
            'default_discount_type' => ['required', 'in:percent,fixed'],
            'default_discount_value' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'auto_bundle_fallback' => ['boolean'],
            'cta_button_text' => ['required', 'string', 'max:100'],
            'show_savings_badge' => ['boolean'],
        ]);

        $data['enabled'] = $request->boolean('enabled');
        $data['auto_bundle_fallback'] = $request->boolean('auto_bundle_fallback');
        $data['show_savings_badge'] = $request->boolean('show_savings_badge');

        $settings->save($data);

        $audit->record('buy_together.settings_updated', null, $data);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Buy Together & Save settings saved.'),
        ]);

        return redirect()->route('admin.modrixcommerce.buy-together-save.settings');
    }
}
