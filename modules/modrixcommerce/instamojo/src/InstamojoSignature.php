<?php

namespace EcoModular\Instamojo;

/**
 * Message Authentication Code (MAC) signature generation and verification for Instamojo.
 */
final class InstamojoSignature
{
    /**
     * Calculates the HMAC-SHA1 MAC string for an Instamojo payload.
     *
     * @param  array<string, mixed>  $params
     */
    public function calculate(array $params, string $privateSalt): string
    {
        $data = $params;
        unset($data['mac']);

        uksort($data, 'strcasecmp');

        $string = implode('|', array_map(function ($val) {
            return is_scalar($val) ? (string) $val : '';
        }, $data));

        return hash_hmac('sha1', $string, $privateSalt);
    }

    /**
     * Verifies the MAC signature on an incoming webhook payload.
     *
     * @param  array<string, mixed>  $params
     */
    public function verify(array $params, string $privateSalt, ?string $receivedMac = null): bool
    {
        $macToVerify = $receivedMac ?? (string) ($params['mac'] ?? '');

        if ($macToVerify === '') {
            return false;
        }

        $calculated = $this->calculate($params, $privateSalt);

        return hash_equals($calculated, $macToVerify);
    }
}
