<?php

namespace App\Http\Controllers\Admin;

use App\Core\Audit\AuditLogger;
use App\Core\Settings\Currencies;
use App\Core\Settings\StoreSettings;
use App\Domains\Catalog\CategoryTree;
use App\Domains\Catalog\Models\Product;
use App\Domains\Discounts\Models\Coupon;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CouponController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('admin/coupons/index', [
            'coupons' => Coupon::query()->latest('id')->paginate(25)->through(fn (Coupon $c) => [
                'id' => $c->id,
                'code' => $c->code,
                'description' => $c->description,
                'type' => $c->type,
                'value' => $c->value,
                'currency' => $c->currency,
                'times_used' => $c->times_used,
                'usage_limit' => $c->usage_limit,
                'starts_at' => $c->starts_at?->toIso8601String(),
                'ends_at' => $c->ends_at?->toIso8601String(),
                'is_active' => $c->is_active,
            ]),
            'canManage' => $request->user()?->can('discounts.manage') ?? false,
        ]);
    }

    public function create(StoreSettings $store): Response
    {
        return Inertia::render('admin/coupons/form', ['coupon' => null, ...$this->options($store)]);
    }

    public function store(Request $request, AuditLogger $audit, StoreSettings $store): RedirectResponse
    {
        $coupon = $this->save(new Coupon, $this->validated($request, null, $store));
        $audit->record('coupon.created', $coupon, ['code' => $coupon->code]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Coupon created.')]);

        return redirect()->route('admin.coupons.index');
    }

    public function edit(Coupon $coupon, StoreSettings $store): Response
    {
        return Inertia::render('admin/coupons/form', [
            'coupon' => [
                ...$coupon->only(['id', 'code', 'description', 'type', 'value', 'currency', 'min_subtotal', 'usage_limit', 'usage_limit_per_customer', 'applies_to', 'is_active', 'times_used']),
                'starts_at' => $coupon->starts_at?->format('Y-m-d\TH:i'),
                'ends_at' => $coupon->ends_at?->format('Y-m-d\TH:i'),
                'product_ids' => $coupon->products()->pluck('products.id')->all(),
                'category_ids' => $coupon->categories()->pluck('categories.id')->all(),
            ],
            ...$this->options($store),
        ]);
    }

    public function update(Request $request, Coupon $coupon, AuditLogger $audit, StoreSettings $store): RedirectResponse
    {
        $this->save($coupon, $this->validated($request, $coupon, $store));
        $audit->record('coupon.updated', $coupon, ['code' => $coupon->code, 'changes' => array_keys($coupon->getChanges())]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Coupon saved.')]);

        return redirect()->route('admin.coupons.index');
    }

    public function destroy(Coupon $coupon, AuditLogger $audit): RedirectResponse
    {
        // Soft delete keeps redemption history intact.
        $coupon->delete();
        $audit->record('coupon.deleted', $coupon, ['code' => $coupon->code]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Coupon deleted.')]);

        return redirect()->route('admin.coupons.index');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function save(Coupon $coupon, array $data): Coupon
    {
        return DB::transaction(function () use ($coupon, $data) {
            $coupon->fill(collect($data)->except(['product_ids', 'category_ids'])->all())->save();
            $coupon->products()->sync($data['applies_to'] === Coupon::APPLIES_PRODUCTS ? ($data['product_ids'] ?? []) : []);
            $coupon->categories()->sync($data['applies_to'] === Coupon::APPLIES_CATEGORIES ? ($data['category_ids'] ?? []) : []);

            return $coupon;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Coupon $coupon, StoreSettings $store): array
    {
        $request->merge([
            'code' => Str::upper(trim((string) $request->input('code'))),
            'is_active' => $request->boolean('is_active'),
        ]);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:64', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('coupons', 'code')->ignore($coupon?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in([Coupon::TYPE_PERCENTAGE, Coupon::TYPE_FIXED])],
            'value' => ['required', 'string', 'regex:/^\d{1,13}(\.\d{1,4})?$/'],
            'min_subtotal' => ['nullable', 'string', 'regex:/^\d{1,13}(\.\d{1,4})?$/'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_customer' => ['nullable', 'integer', 'min:1'],
            'applies_to' => ['required', Rule::in([Coupon::APPLIES_ALL, Coupon::APPLIES_PRODUCTS, Coupon::APPLIES_CATEGORIES])],
            'product_ids' => ['nullable', 'array', 'required_if:applies_to,products'],
            'product_ids.*' => ['integer', Rule::exists('products', 'id')],
            'category_ids' => ['nullable', 'array', 'required_if:applies_to,categories'],
            'category_ids.*' => ['integer', Rule::exists('categories', 'id')],
            'is_active' => ['boolean'],
        ]);

        if ($data['type'] === Coupon::TYPE_PERCENTAGE) {
            $request->validate(['value' => ['numeric', 'gt:0', 'max:100']]);
            $data['currency'] = null;
        } else {
            $request->validate(['value' => ['numeric', 'gt:0']]);
            $data['currency'] = $store->currency();
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function options(StoreSettings $store): array
    {
        return [
            'currency' => $store->currency(),
            'currencies' => Currencies::codes(),
            'products' => Product::query()->orderBy('name')->limit(500)->get(['id', 'name']),
            'categories' => array_map(fn ($c) => ['id' => $c['id'], 'name' => $c['name'], 'depth' => $c['depth']], CategoryTree::flatten()),
        ];
    }
}
