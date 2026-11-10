<?php

namespace EcoModular\ProductFields\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * A custom field every product can have a value for.
 *
 * @property int $id
 * @property string $key
 * @property string $label
 * @property string $type
 * @property list<string> $options
 * @property string|null $help
 * @property bool $required
 * @property bool $show_on_storefront
 * @property int $position
 */
class ProductField extends Model
{
    public const TYPES = [
        'text' => 'Text',
        'textarea' => 'Long text',
        'number' => 'Number',
        'boolean' => 'Yes / no',
        'date' => 'Date',
        'select' => 'Dropdown',
        'url' => 'Link',
    ];

    protected $table = 'product_fields';

    protected $fillable = ['key', 'label', 'type', 'options', 'help', 'required', 'show_on_storefront', 'position'];

    protected $attributes = ['options' => '[]'];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'required' => 'boolean',
            'show_on_storefront' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * Rules for this field's value (an empty value means "no value").
     *
     * @return list<mixed>
     */
    public function rules(): array
    {
        $rules = [$this->required ? 'required' : 'nullable'];

        return [...$rules, ...match ($this->type) {
            'textarea' => ['string', 'max:5000'],
            'number' => ['numeric', 'between:-1000000000,1000000000'],
            'boolean' => ['boolean'],
            'date' => ['date_format:Y-m-d'],
            'select' => ['string', Rule::in($this->options)],
            'url' => ['url:http,https', 'max:500'],
            default => ['string', 'max:255'],
        }];
    }

    /** The stored form of a valid value. */
    public function normalize(mixed $value): string
    {
        return match ($this->type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
            default => trim((string) $value),
        };
    }

    /** The value as shoppers read it. */
    public function display(string $value): string
    {
        return match ($this->type) {
            'boolean' => $value === '1' ? st('Yes') : st('No'),
            'date' => Carbon::createFromFormat('Y-m-d', $value)->translatedFormat('j F Y'),
            default => $value,
        };
    }
}
