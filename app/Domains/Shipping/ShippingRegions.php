<?php

namespace App\Domains\Shipping;

use Illuminate\Support\Str;

final class ShippingRegions
{
    public const UAE = ['Dubai', 'Abu Dhabi', 'Sharjah', 'Ajman', 'Umm Al Quwain', 'Ras Al Khaimah', 'Fujairah'];

    /**
     * Countries where shoppers pick a region instead of typing one. Kept in step
     * with the storefront list in resources/js/lib/regions.ts.
     *
     * @var array<string, list<string>>
     */
    public const KNOWN = [
        'AE' => self::UAE,
        'US' => [
            'Alabama', 'Alaska', 'Arizona', 'Arkansas', 'California', 'Colorado', 'Connecticut', 'Delaware',
            'District of Columbia', 'Florida', 'Georgia', 'Hawaii', 'Idaho', 'Illinois', 'Indiana', 'Iowa',
            'Kansas', 'Kentucky', 'Louisiana', 'Maine', 'Maryland', 'Massachusetts', 'Michigan', 'Minnesota',
            'Mississippi', 'Missouri', 'Montana', 'Nebraska', 'Nevada', 'New Hampshire', 'New Jersey',
            'New Mexico', 'New York', 'North Carolina', 'North Dakota', 'Ohio', 'Oklahoma', 'Oregon',
            'Pennsylvania', 'Rhode Island', 'South Carolina', 'South Dakota', 'Tennessee', 'Texas', 'Utah',
            'Vermont', 'Virginia', 'Washington', 'West Virginia', 'Wisconsin', 'Wyoming',
        ],
        'CA' => [
            'Alberta', 'British Columbia', 'Manitoba', 'New Brunswick', 'Newfoundland and Labrador',
            'Northwest Territories', 'Nova Scotia', 'Nunavut', 'Ontario', 'Prince Edward Island', 'Quebec',
            'Saskatchewan', 'Yukon',
        ],
        'AU' => [
            'Australian Capital Territory', 'New South Wales', 'Northern Territory', 'Queensland',
            'South Australia', 'Tasmania', 'Victoria', 'Western Australia',
        ],
    ];

    /**
     * The known regions for a country, or an empty list when it uses free text.
     *
     * @return list<string>
     */
    public static function for(?string $country): array
    {
        return self::KNOWN[strtoupper((string) $country)] ?? [];
    }

    /** Whether a region must be supplied for the given country. */
    public static function has(?string $country): bool
    {
        return self::for($country) !== [];
    }

    public static function normalize(?string $region, string $country): string
    {
        $key = Str::lower(preg_replace('/[\s\-_.]+/u', '', trim($region ?? '')) ?? '');
        if (strtoupper($country) !== 'AE') {
            return $key;
        }

        return match ($key) {
            'dubai','du','aedu','دبي' => 'dubai',
            'abudhabi','abudhabie','az','aeaz','أبوظبي','ابوظبي' => 'abudhabi',
            'sharjah','sh','aesh','الشارقة' => 'sharjah',
            'ajman','aj','aeaj','عجمان' => 'ajman',
            'ummalquwain','ummalqaiwain','uq','aeuq','أمالقيوين' => 'ummalquwain',
            'rasalkhaimah','rasalkhaima','rk','aerk','رأسالخيمة' => 'rasalkhaimah',
            'fujairah','fu','aefu','الفجيرة' => 'fujairah',
            default => $key,
        };
    }
}
