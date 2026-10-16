<?php

namespace App\Http\Requests\Admin;

use App\Domains\Catalog\Actions\SaveProduct;
use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Enums\ProductType;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductTag;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\ProductRelations;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * @phpstan-import-type ProductInput from SaveProduct
 */
class SaveProductRequest extends FormRequest
{
    public const MONEY_PATTERN = '/^\d{1,13}(\.\d{1,4})?$/';

    public function authorize(): bool
    {
        // Route middleware enforces products.create / products.update.
        return true;
    }

    protected function prepareForValidation(): void
    {
        $variants = array_map(function (mixed $variant) {
            if (! is_array($variant)) {
                return $variant;
            }

            foreach (['track_inventory', 'allow_backorder', 'is_active'] as $flag) {
                if (array_key_exists($flag, $variant)) {
                    $variant[$flag] = filter_var($variant[$flag], FILTER_VALIDATE_BOOLEAN);
                }
            }

            return $variant;
        }, (array) $this->input('variants', []));

        $this->merge(['variants' => $variants]);

        if ($this->has('is_featured')) {
            $this->merge(['is_featured' => filter_var($this->input('is_featured'), FILTER_VALIDATE_BOOLEAN)]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Product|null $product */
        $product = $this->route('product');
        $ownVariantIds = $product?->variants()->withTrashed()->pluck('id')->all() ?? [];

        return [
            'type' => ['required', Rule::enum(ProductType::class)],
            'name' => ['required', 'string', 'max:191'],
            'slug' => ['nullable', 'string', 'max:191', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'status' => ['required', Rule::enum(ProductStatus::class)],
            'published_at' => ['nullable', 'date'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:20000'],
            'meta_title' => ['nullable', 'string', 'max:191'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')],
            'is_featured' => ['boolean'],
            'tags' => ['nullable', 'array', 'max:'.ProductTag::MAX_PER_PRODUCT],
            'tags.*' => ['string', 'max:'.ProductTag::MAX_LENGTH],
            'related_ids' => ['nullable', 'array', 'max:'.ProductRelations::MAX],
            'related_ids.*' => ['integer', Rule::exists('products', 'id')],
            'upsell_ids' => ['nullable', 'array', 'max:'.ProductRelations::MAX],
            'upsell_ids.*' => ['integer', Rule::exists('products', 'id')],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'distinct', Rule::exists('categories', 'id')],
            'option_attribute_ids' => ['nullable', 'array', 'max:3'],
            'option_attribute_ids.*' => ['integer', 'distinct', Rule::exists('product_attributes', 'id')],
            'variants' => ['required', 'array', 'min:1', 'max:100'],
            'variants.*.id' => ['nullable', 'integer', Rule::in($ownVariantIds)],
            'variants.*.sku' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/', 'distinct:ignore_case'],
            'variants.*.name' => ['nullable', 'string', 'max:191'],
            'variants.*.price' => ['required', 'string', 'regex:'.self::MONEY_PATTERN],
            'variants.*.compare_at_price' => ['nullable', 'string', 'regex:'.self::MONEY_PATTERN],
            'variants.*.weight' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'variants.*.cost' => ['nullable', 'string', 'regex:'.self::MONEY_PATTERN],
            'variants.*.length' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'variants.*.width' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'variants.*.height' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'variants.*.barcode' => ['nullable', 'string', 'max:64'],
            'variants.*.track_inventory' => ['boolean'],
            'variants.*.allow_backorder' => ['boolean'],
            'variants.*.is_active' => ['boolean'],
            'variants.*.low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'variants.*.option_value_ids' => ['nullable', 'array'],
            'variants.*.option_value_ids.*' => ['integer', Rule::exists('product_attribute_values', 'id')],
            'variants.*.initial_stock' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    /**
     * Validated input in the shape SaveProduct expects.
     *
     * @return ProductInput
     */
    public function productData(): array
    {
        /** @var ProductInput */
        return $this->validated();
    }

    /**
     * SKUs are unique across all variants (including soft-deleted ones),
     * except the row being edited.
     */
    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                foreach ((array) $this->input('variants', []) as $index => $variant) {
                    if (! is_array($variant) || ! is_string($variant['sku'] ?? null)) {
                        continue;
                    }

                    $taken = ProductVariant::withTrashed()
                        ->whereRaw('UPPER(sku) = ?', [strtoupper(trim($variant['sku']))])
                        ->when(isset($variant['id']), fn ($q) => $q->whereKeyNot((int) $variant['id']))
                        ->exists();

                    if ($taken) {
                        $validator->errors()->add("variants.{$index}.sku", 'This SKU is already in use.');
                    }
                }
            },
        ];
    }
}
