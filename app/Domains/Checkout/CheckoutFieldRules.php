<?php

namespace App\Domains\Checkout;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The merchant's Checkout page settings applied on the server: required
 * phone and terms, and fields the merchant hides are never stored, even
 * when a request still sends them.
 */
final class CheckoutFieldRules
{
    public const TERMS_SLUG = 'terms';

    /**
     * Extra validation rules.
     *
     * @param  array<string, mixed>  $checkout  resolved Checkout page settings
     * @return array<string, list<string>>
     */
    public static function rules(array $checkout): array
    {
        $rules = [];

        if ($checkout['phone'] === 'required') {
            $rules['phone'] = ['required', 'string', 'max:40'];
        }

        if ($checkout['terms']) {
            $rules['terms'] = ['accepted'];
        }

        return $rules;
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return ['terms.accepted' => 'Please accept the terms to place your order.'];
    }

    /**
     * Drops the values of hidden fields.
     *
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $checkout
     * @return array<string, mixed>
     */
    public static function clean(array $validated, array $checkout): array
    {
        $hiddenAddressFields = array_keys(array_filter([
            'company' => ! $checkout['company'],
            'line2' => ! $checkout['address_line2'],
            'phone' => $checkout['phone'] === 'hidden',
        ]));

        if ($checkout['phone'] === 'hidden') {
            unset($validated['phone']);
        }

        if (! $checkout['order_note']) {
            unset($validated['customer_note']);
        }

        foreach (['shipping', 'billing'] as $type) {
            if (isset($validated[$type]) && is_array($validated[$type])) {
                $validated[$type] = array_diff_key($validated[$type], array_flip($hiddenAddressFields));
            }
        }

        return $validated;
    }

    /** The published terms page, when the store has one. */
    public static function termsUrl(): ?string
    {
        $exists = Schema::hasTable('pages') && DB::table('pages')
            ->where('slug', self::TERMS_SLUG)
            ->where('status', 'published')
            ->exists();

        return $exists ? '/pages/'.self::TERMS_SLUG : null;
    }
}
