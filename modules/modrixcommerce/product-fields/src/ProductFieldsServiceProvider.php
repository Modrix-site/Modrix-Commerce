<?php

namespace EcoModular\ProductFields;

use App\Core\Extensions\StorefrontSlots;
use App\Domains\Catalog\Models\Product;
use App\Models\User;
use EcoModular\ProductFields\Models\ProductField;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

/**
 * Wires product fields into the platform:
 *  - the "Product fields" panel in the admin product editor (fill in values);
 *  - a details block on the product page in every theme, listing the fields
 *    switched to "Show on product page" that have a value.
 * Field definitions live under Products → Product fields.
 */
class ProductFieldsServiceProvider extends ServiceProvider
{
    public const MODULE = 'modrixcommerce/product-fields';

    public const ROUTE = 'admin.modrixcommerce.product-fields.';

    public function boot(StorefrontSlots $slots): void
    {
        $slots->register('admin.product.sidebar', self::MODULE, 'ProductFieldsPanel', function (array $context) {
            $product = $context['product'] ?? null;
            $user = auth()->user();

            if (! $product instanceof Product || ! $user instanceof User || ! $user->can('products.update')) {
                return null;
            }

            $values = $this->values($product);

            return [
                'updateUrl' => route(self::ROUTE.'product.update', $product),
                'manageUrl' => $user->can('product_fields.manage') ? route(self::ROUTE.'index') : null,
                'fields' => ProductField::query()->orderBy('position')->orderBy('id')->get()->map(fn (ProductField $f) => [
                    'id' => $f->id,
                    'label' => $f->label,
                    'type' => $f->type,
                    'options' => $f->options,
                    'help' => $f->help,
                    'required' => $f->required,
                    'value' => $values[$f->id] ?? null,
                ])->values()->all(),
            ];
        }, 30);

        $slots->block(self::MODULE, 'details', 'Product details (custom fields)', 'ProductDetails', ['product.after_description', 'product.after_add_to_cart', 'product.after_details'], 'product.after_description', function (array $context) {
            $product = $context['product'] ?? null;

            if (! $product instanceof Product) {
                return null;
            }

            $values = $this->values($product);
            $fields = ProductField::query()
                ->where('show_on_storefront', true)
                ->whereIn('id', array_keys($values))
                ->orderBy('position')
                ->orderBy('id')
                ->get()
                ->map(fn (ProductField $f) => ['label' => $f->label, 'value' => $f->display($values[$f->id]), 'type' => $f->type])
                ->values()
                ->all();

            return $fields === [] ? null : ['heading' => st('Specifications'), 'fields' => $fields];
        }, 60);
    }

    /**
     * @return array<int, string> field id => stored value
     */
    private function values(Product $product): array
    {
        return DB::table('product_field_values')
            ->where('product_id', $product->id)
            ->pluck('value', 'product_field_id')
            ->mapWithKeys(fn ($value, $id) => [(int) $id => (string) $value])
            ->all();
    }
}
