<?php

namespace EcoModular\ProductFields\Http;

use App\Core\Audit\AuditLogger;
use App\Domains\Catalog\Models\Product;
use App\Http\Controllers\Controller;
use EcoModular\ProductFields\Models\ProductField;
use EcoModular\ProductFields\ProductFieldsServiceProvider as Module;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Products → Product fields (`can:product_fields.manage`), plus saving a
 * product's values from the editor panel (`products.update`).
 */
class ProductFieldAdminController extends Controller
{
    private const ROUTE = Module::ROUTE;

    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): Response
    {
        $counts = DB::table('product_field_values')->selectRaw('product_field_id, COUNT(*) as n')->groupBy('product_field_id')->pluck('n', 'product_field_id');

        return Inertia::render('module/modrixcommerce/product-fields/admin/index', [
            'types' => ProductField::TYPES,
            'fields' => ProductField::query()->orderBy('position')->orderBy('id')->get()->map(fn (ProductField $f) => [
                'id' => $f->id,
                'key' => $f->key,
                'label' => $f->label,
                'type' => $f->type,
                'options' => $f->options,
                'help' => $f->help,
                'required' => $f->required,
                'show_on_storefront' => $f->show_on_storefront,
                'products_count' => (int) ($counts[$f->id] ?? 0),
                'update_url' => route(self::ROUTE.'update', $f),
                'delete_url' => route(self::ROUTE.'destroy', $f),
            ])->values(),
            'storeUrl' => route(self::ROUTE.'store'),
            'reorderUrl' => route(self::ROUTE.'reorder'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['key'] = $this->uniqueKey($data['label']);
        $data['position'] = (int) ProductField::query()->max('position') + 1;
        $field = ProductField::query()->create($data);
        $this->audit->record('product_field.created', $field, ['label' => $field->label]);

        return $this->done(__('Field added.'));
    }

    public function update(Request $request, ProductField $field): RedirectResponse
    {
        $field->update($this->validated($request));
        $this->audit->record('product_field.updated', $field, ['label' => $field->label]);

        return $this->done(__('Field saved.'));
    }

    public function destroy(ProductField $field): RedirectResponse
    {
        $this->audit->record('product_field.deleted', $field, ['label' => $field->label]);
        $field->delete();

        return $this->done(__('Field deleted. Its values were removed from all products.'));
    }

    public function reorder(Request $request): RedirectResponse
    {
        $data = $request->validate(['ids' => ['required', 'array', 'max:500'], 'ids.*' => ['integer', 'distinct']]);

        foreach (array_values($data['ids']) as $position => $id) {
            ProductField::query()->whereKey((int) $id)->update(['position' => $position]);
        }

        return $this->done(__('Order saved.'));
    }

    /** The editor panel: this product's values (empty removes a value). */
    public function updateProduct(Request $request, Product $product): RedirectResponse
    {
        $fields = ProductField::query()->get()->keyBy('id');
        $rules = ['values' => ['array']];
        $attributes = [];

        foreach ($fields as $field) {
            $rules["values.{$field->id}"] = $field->rules();
            $attributes["values.{$field->id}"] = $field->label;
        }

        $data = $request->validate($rules, [], $attributes);
        $now = now();

        DB::transaction(function () use ($fields, $data, $product, $now) {
            foreach ($fields as $field) {
                $value = $data['values'][$field->id] ?? null;
                $empty = $value === null || (is_string($value) && trim($value) === '');

                if ($empty) {
                    DB::table('product_field_values')->where('product_id', $product->id)->where('product_field_id', $field->id)->delete();

                    continue;
                }

                DB::table('product_field_values')->updateOrInsert(
                    ['product_id' => $product->id, 'product_field_id' => $field->id],
                    ['value' => $field->normalize($value), 'created_at' => $now, 'updated_at' => $now],
                );
            }
        });

        $this->audit->record('product_field.values_updated', $product, ['product_id' => $product->id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product fields saved.')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(array_keys(ProductField::TYPES))],
            'options' => ['nullable', 'array', 'max:50', Rule::requiredIf(fn () => $request->input('type') === 'select')],
            'options.*' => ['string', 'max:120'],
            'help' => ['nullable', 'string', 'max:255'],
            'required' => ['boolean'],
            'show_on_storefront' => ['boolean'],
        ], ['options.required' => 'Add at least one choice for a dropdown.']);

        $options = $data['type'] === 'select'
            ? array_values(array_unique(array_filter(array_map('trim', $data['options'] ?? []), fn (string $o) => $o !== '')))
            : [];

        return [
            'label' => $data['label'],
            'type' => $data['type'],
            'options' => $options,
            'help' => ($data['help'] ?? '') !== '' ? $data['help'] : null,
            'required' => $request->boolean('required'),
            'show_on_storefront' => $request->boolean('show_on_storefront'),
        ];
    }

    private function uniqueKey(string $label): string
    {
        $base = Str::limit(Str::slug($label, '_'), 56, '') ?: 'field';
        $key = $base;

        for ($n = 2; ProductField::query()->where('key', $key)->exists(); $n++) {
            $key = "{$base}_{$n}";
        }

        return $key;
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return redirect()->route(self::ROUTE.'index');
    }
}
