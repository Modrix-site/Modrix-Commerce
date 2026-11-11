<?php

namespace EcoModular\AmazonPaymentServices;

/**
 * Calculates and verifies cryptographic digital signatures for Amazon Payment Services (APS).
 *
 * Algorithm per APS official integration specification:
 * 1. Filter out parameters with null or empty string values, and the 'signature' parameter.
 * 2. Sort the parameter keys in ascending alphabetical order (ASCII).
 * 3. Concatenate each key and value as "key=value".
 * 4. Prepend and append the merchant's SHA Passphrase (Request or Response phrase).
 * 5. Compute the hash using the configured algorithm (SHA-256 or SHA-512).
 */
final class AmazonPaymentServicesSignature
{
    /**
     * @param  array<string, mixed>  $parameters
     */
    public function calculate(array $parameters, string $phrase, string $algorithm = 'sha256'): string
    {
        $filtered = [];

        foreach ($parameters as $key => $value) {
            if ($key === 'signature' || $value === null || $value === '') {
                continue;
            }

            $filtered[(string) $key] = (string) $value;
        }

        ksort($filtered, SORT_STRING);

        $concatenated = '';
        foreach ($filtered as $key => $value) {
            $concatenated .= "{$key}={$value}";
        }

        $rawString = $phrase.$concatenated.$phrase;
        $hashAlgo = strtolower(trim($algorithm)) === 'sha512' ? 'sha512' : 'sha256';

        return hash($hashAlgo, $rawString);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function verify(array $parameters, string $expectedSignature, string $phrase, string $algorithm = 'sha256'): bool
    {
        if ($expectedSignature === '' || $phrase === '') {
            return false;
        }

        $calculated = $this->calculate($parameters, $phrase, $algorithm);

        return hash_equals(strtolower($expectedSignature), strtolower($calculated));
    }
}
