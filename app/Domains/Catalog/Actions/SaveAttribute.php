<?php

namespace App\Domains\Catalog\Actions;

use App\Core\Audit\AuditLogger;
use App\Domains\Catalog\Models\ProductAttribute;
use App\Domains\Catalog\Models\ProductAttributeValue;
use App\Domains\Catalog\Slugger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creates/updates an option type and its values. Values in use by variants
 * cannot be removed (that would silently change existing variants).
 */
final class SaveAttribute
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name: string, display_type?: string, values: list<array{id?: int|null, value: string, color?: string|null}>}  $data
     */
    public function handle(?ProductAttribute $attribute, array $data): ProductAttribute
    {
        return DB::transaction(function () use ($attribute, $data) {
            $isNew = $attribute === null;
            $attribute ??= new ProductAttribute;
            $attribute->name = $data['name'];
            $attribute->display_type = $data['display_type'] ?? $attribute->display_type ?? 'buttons';

            if ($isNew) {
                $attribute->slug = Slugger::unique(ProductAttribute::class, $data['name']);
            }
            $attribute->save();

            $keptIds = [];
            $seenSlugs = [];

            foreach ($data['values'] as $position => $row) {
                $slug = Str::slug($row['value']) ?: (string) $position;

                if (in_array($slug, $seenSlugs, true)) {
                    throw ValidationException::withMessages(["values.{$position}.value" => 'Duplicate value.']);
                }
                $seenSlugs[] = $slug;

                $value = isset($row['id'])
                    ? $attribute->values()->whereKey($row['id'])->firstOrFail()
                    : new ProductAttributeValue;

                $value->attribute_id = $attribute->id;
                $value->fill(['value' => $row['value'], 'slug' => $slug, 'position' => $position, 'color' => array_key_exists('color', $row) ? $row['color'] : $value->color])->save();
                $keptIds[] = $value->id;
            }

            $removed = $attribute->values()->whereNotIn('id', $keptIds)->get();

            foreach ($removed as $value) {
                if (DB::table('product_variant_options')->where('attribute_value_id', $value->id)->exists()) {
                    throw ValidationException::withMessages(['values' => "\"{$value->value}\" is used by product variants and cannot be removed."]);
                }
                $value->delete();
            }

            $this->audit->record($isNew ? 'attribute.created' : 'attribute.updated', $attribute, [
                'name' => $attribute->name,
                'values' => array_column($data['values'], 'value'),
            ]);

            return $attribute;
        });
    }
}
