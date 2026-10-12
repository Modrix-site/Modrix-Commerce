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
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AttributeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/attributes/index', [
            'attributes' => ProductAttribute::query()->with('values')->orderBy('position')->orderBy('name')->get()
                ->map(fn (ProductAttribute $a) => [
                    'id' => $a->id,
                    'name' => $a->name,
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
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Option created.')]);

        return redirect()->route('admin.attributes.index');
    }

    public function edit(ProductAttribute $attribute): Response
    {
        return Inertia::render('admin/attributes/form', [
            'attribute' => [
                'id' => $attribute->id,
                'name' => $attribute->name,
                'values' => $attribute->values->map(fn (ProductAttributeValue $v) => ['id' => $v->id, 'value' => $v->value])->all(),
            ],
        ]);
    }

    public function update(Request $request, ProductAttribute $attribute, SaveAttribute $save): RedirectResponse
    {
        $save->handle($attribute, $this->validated($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Option saved.')]);

        return redirect()->route('admin.attributes.index');
    }

    public function destroy(ProductAttribute $attribute, AuditLogger $audit): RedirectResponse
    {
        $inUse = DB::table('product_variant_options')
            ->whereIn('attribute_value_id', $attribute->values()->pluck('id'))
            ->exists();

        if ($inUse) {
            throw ValidationException::withMessages(['attribute' => 'This option is used by product variants and cannot be deleted.']);
        }

        $audit->record('attribute.deleted', $attribute, ['name' => $attribute->name]);
        $attribute->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Option deleted.')]);

        return redirect()->route('admin.attributes.index');
    }

    /**
     * @return array{name: string, values: list<array{id?: int|null, value: string}>}
     */
    private function validated(Request $request): array
    {
        /** @var array{name: string, values: list<array{id?: int|null, value: string}>} */
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'values' => ['required', 'array', 'min:1', 'max:200'],
            'values.*.id' => ['nullable', 'integer'],
            'values.*.value' => ['required', 'string', 'max:120'],
        ]);
    }
}
