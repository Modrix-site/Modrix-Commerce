<?php

namespace ModrixCommerce\Sms;

/**
 * Normalises what customers type into E.164 (+<country><number>). A number
 * without "+" or "00" gets the store's default country code, dropping one
 * leading trunk 0 ("0917 123 4567" in the Philippines → +639171234567).
 */
final class PhoneNumber
{
    public static function normalize(string $input, string $defaultCountryCode): ?string
    {
        $raw = trim($input);

        if ($raw === '' || preg_match('/[^0-9+\s().\-]/', $raw)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $raw) ?? '';

        if (str_starts_with($raw, '+')) {
            $e164 = '+'.$digits;
        } elseif (str_starts_with($digits, '00')) {
            $e164 = '+'.substr($digits, 2);
        } else {
            $country = ltrim($defaultCountryCode, '+');

            if ($country === '') {
                return null;
            }

            $e164 = '+'.$country.(str_starts_with($digits, '0') ? substr($digits, 1) : $digits);
        }

        return preg_match('/^\+[1-9]\d{7,14}$/', $e164) ? $e164 : null;
    }

    /** "+639171234567" → "+63•••••••567" for logs and staff screens. */
    public static function mask(string $e164): string
    {
        return strlen($e164) <= 6 ? $e164 : substr($e164, 0, 3).str_repeat('•', strlen($e164) - 6).substr($e164, -3);
    }
}
