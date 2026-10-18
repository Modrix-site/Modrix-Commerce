<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Core\Audit\AuditLogger;
use App\Domains\Catalog\Actions\SaveAttribute;
use App\Domains\Catalog\Models\ProductAttribute;
use App\Domains\Catalog\Models\ProductAttributeValue;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AttributeController extends Controller
{
    public function index(): Response
    {
        $usage = DB::table('product_variant_options as options')
            ->join('product_attribute_values as attribute_values', 'attribute_values.id', '=', 'options.attribute_value_id')
            ->join('product_variants as variants', 'variants.id', '=', 'options.variant_id')
            ->join('products', 'products.id', '=', 'variants.product_id')
            ->whereNull('variants.deleted_at')->whereNull('products.deleted_at')
            ->selectRaw('attribute_values.attribute_id, COUNT(DISTINCT products.id) as product_count')
            ->groupBy('attribute_values.attribute_id')->pluck('product_count', 'attribute_id');

        return Inertia::render('admin/attributes/index', [
            'attributes' => ProductAttribute::query()->with('values')->orderBy('position')->orderBy('name')->get()
                ->map(fn (ProductAttribute $a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'product_count' => (int) ($usage[$a->id] ?? 0),
                    'values' => $a->values->pluck('value')->all(),
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/attributes/form', ['attribute' => null]);
    }

    public function store(Request $request, SaveAttribute $save): RedirectResponse
    {
        $save->handle(null, $this->validated($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Variant created.')]);

        return redirect()->route('admin.attributes.index');
    }

    public function edit(ProductAttribute $attribute): Response
    {
        return Inertia::render('admin/attributes/form', [
            'attribute' => [
                'id' => $attribute->id,
                'name' => $attribute->name,
                'display_type' => $attribute->display_type,
                'values' => $attribute->values->map(fn (ProductAttributeValue $v) => ['id' => $v->id, 'value' => $v->value, 'color' => $v->color])->all(),
            ],
        ]);
    }

    public function update(Request $request, ProductAttribute $attribute, SaveAttribute $save): RedirectResponse
    {
        $save->handle($attribute, $this->validated($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Variant saved.')]);

        return redirect()->route('admin.attributes.index');
    }

    public function destroy(ProductAttribute $attribute, AuditLogger $audit): RedirectResponse
    {
        $inUse = DB::table('product_variant_options')
            ->whereIn('attribute_value_id', $attribute->values()->pluck('id'))
            ->exists();

        if ($inUse) {
            throw ValidationException::withMessages(['attribute' => 'This variant type is used by product variants and cannot be deleted.']);
        }

        $audit->record('attribute.deleted', $attribute, ['name' => $attribute->name]);
        $attribute->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Variant deleted.')]);

        return redirect()->route('admin.attributes.index');
    }

    /**
     * @return array{name: string, display_type?: string, values: list<array{id?: int|null, value: string, color?: string|null}>}
     */
    private function validated(Request $request): array
    {
        /** @var array{name: string, display_type?: string, values: list<array{id?: int|null, value: string, color?: string|null}>} */
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'display_type' => ['sometimes', 'required', Rule::in(['buttons', 'circles', 'swatches', 'dropdown', 'radio'])],
            'values' => ['required', 'array', 'min:1', 'max:200'],
            'values.*.id' => ['nullable', 'integer'],
            'values.*.value' => ['required', 'string', 'max:120'],
            'values.*.color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/', 'required_if:display_type,swatches'],
        ]);
    }
}
