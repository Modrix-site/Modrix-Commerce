<?php

namespace EcoModular\Easebuzz;

/**
 * Handles SHA-512 cryptographic hash generation and reverse verification for Easebuzz India.
 */
final class EasebuzzSignature
{
    /**
     * Calculates the SHA-512 hash required for Easebuzz initiateLink API request.
     *
     * Formula:
     *   sha512(key|txnid|amount|productinfo|firstname|email|udf1|udf2|udf3|udf4|udf5|udf6|udf7|udf8|udf9|udf10|salt)
     */
    public function generateInitiateHash(
        string $key,
        string $txnid,
        string $amount,
        string $productinfo,
        string $firstname,
        string $email,
        string $salt,
        string $udf1 = '',
        string $udf2 = '',
        string $udf3 = '',
        string $udf4 = '',
        string $udf5 = '',
        string $udf6 = '',
        string $udf7 = '',
        string $udf8 = '',
        string $udf9 = '',
        string $udf10 = '',
    ): string {
        $sequence = sprintf(
            '%s|%s|%s|%s|%s|%s|%s|%s|%s|%s|%s|%s|%s|%s|%s|%s|%s',
            $key,
            $txnid,
            $amount,
            $productinfo,
            $firstname,
            $email,
            $udf1,
            $udf2,
            $udf3,
            $udf4,
            $udf5,
            $udf6,
            $udf7,
            $udf8,
            $udf9,
            $udf10,
            $salt,
        );

        return strtolower(hash('sha512', $sequence));
    }

    /**
     * Verifies the reverse hash sent by Easebuzz upon payment return or webhook notification.
     *
     * Formula:
     *   sha512(salt|status|udf10|udf9|udf8|udf7|udf6|udf5|udf4|udf3|udf2|udf1|email|firstname|productinfo|amount|txnid|key)
     *
     * @param  array<string, mixed>  $params
     */
    public function verifyResponseHash(array $params, string $salt, ?string $receivedHash = null): bool
    {
        $hashToVerify = $receivedHash ?? (string) ($params['hash'] ?? '');

        if ($hashToVerify === '') {
            return false;
        }

        $key = (string) ($params['key'] ?? '');
        $txnid = (string) ($params['txnid'] ?? '');
        $amount = (string) ($params['amount'] ?? '');
        $productinfo = (string) ($params['productinfo'] ?? '');
        $firstname = (string) ($params['firstname'] ?? '');
        $email = (string) ($params['email'] ?? '');
        $status = (string) ($params['status'] ?? '');

        $udf1 = (string) ($params['udf1'] ?? '');
        $udf2 = (string) ($params['udf2'] ?? '');
        $udf3 = (string) ($params['udf3'] ?? '');
        $udf4 = (string) ($params['udf4'] ?? '');
        $udf5 = (string) ($params['udf5'] ?? '');
        $udf6 = (string) ($params['udf6'] ?? '');
        $udf7 = (string) ($params['udf7'] ?? '');
        $udf8 = (string) ($params['udf8'] ?? '');
        $udf9 = (string) ($params['udf9'] ?? '');
        $udf10 = (string) ($params['udf10'] ?? '');

        $sequence = sprintf(
            '%s|%s|%s|%s|%s|%s|%s|%s|%s|%s|%s|%s|%s|%s|%s|%s|%s|%s',
            $salt,
            $status,
            $udf10,
            $udf9,
            $udf8,
            $udf7,
            $udf6,
            $udf5,
            $udf4,
            $udf3,
            $udf2,
            $udf1,
            $email,
            $firstname,
            $productinfo,
            $amount,
            $txnid,
            $key,
        );

        $expectedHash = strtolower(hash('sha512', $sequence));

        return hash_equals($expectedHash, strtolower($hashToVerify));
    }

    /**
     * Calculates the SHA-512 hash required for Easebuzz retrieve transaction status API.
     *
     * Formula:
     *   sha512(key|txnid|amount|email|phone|salt)
     */
    public function generateRetrieveHash(
        string $key,
        string $txnid,
        string $amount,
        string $email,
        string $phone,
        string $salt,
    ): string {
        $sequence = sprintf(
            '%s|%s|%s|%s|%s|%s',
            $key,
            $txnid,
            $amount,
            $email,
            $phone,
            $salt,
        );

        return strtolower(hash('sha512', $sequence));
    }

    /**
     * Calculates the SHA-512 hash required for Easebuzz refund API.
     *
     * Formula:
     *   sha512(key|txnid|phone|email|amount|refund_amount|salt)
     */
    public function generateRefundHash(
        string $key,
        string $txnid,
        string $phone,
        string $email,
        string $amount,
        string $refundAmount,
        string $salt,
    ): string {
        $sequence = sprintf(
            '%s|%s|%s|%s|%s|%s|%s',
            $key,
            $txnid,
            $phone,
            $email,
            $amount,
            $refundAmount,
            $salt,
        );

        return strtolower(hash('sha512', $sequence));
    }
}
