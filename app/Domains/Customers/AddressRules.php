<?php

namespace App\Domains\Customers;

use App\Core\Settings\Countries;
use App\Domains\Shipping\ShippingRegions;
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
            "{$prefix}region" => [self::regionRequired($prefix, $required) ? 'required' : 'nullable', 'string', 'max:120'],
            "{$prefix}postal_code" => ['nullable', 'string', 'max:32'],
            "{$prefix}country_code" => [$req, 'string', 'size:2', Rule::in(Countries::codes())],
            "{$prefix}phone" => ['nullable', 'string', 'max:40'],
        ];
    }

    /**
     * Whether a region is required depends on the country being submitted, which
     * is only known at validation time. The rule is resolved against the request
     * input for this address (the country code is validated alongside it).
     */
    private static function regionRequired(string $prefix, bool $required): bool
    {
        if (! $required) {
            return false;
        }

        $country = (string) request()->input("{$prefix}country_code", '');

        return ShippingRegions::has($country);
    }
}
