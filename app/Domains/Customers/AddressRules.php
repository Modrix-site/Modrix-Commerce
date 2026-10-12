<?php

namespace App\Domains\Customers;

use App\Core\Settings\Countries;
use Illuminate\Validation\Rule;

/**
 * Validation rules for a postal address, reused by the address book and checkout.
 */
final class AddressRules
{
    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(string $prefix = '', bool $required = true): array
    {
        $req = $required ? 'required' : 'nullable';

        return [
            "{$prefix}first_name" => [$req, 'string', 'max:120'],
            "{$prefix}last_name" => [$req, 'string', 'max:120'],
            "{$prefix}company" => ['nullable', 'string', 'max:160'],
            "{$prefix}line1" => [$req, 'string', 'max:191'],
            "{$prefix}line2" => ['nullable', 'string', 'max:191'],
            "{$prefix}city" => [$req, 'string', 'max:120'],
            "{$prefix}region" => ['nullable', 'string', 'max:120'],
            "{$prefix}postal_code" => ['nullable', 'string', 'max:32'],
            "{$prefix}country_code" => [$req, 'string', 'size:2', Rule::in(Countries::codes())],
            "{$prefix}phone" => ['nullable', 'string', 'max:40'],
        ];
    }
}
