<?php

namespace EcoModular\PayU;

/**
 * Handles SHA-512 cryptographic hash generation and reverse verification for PayU India.
 */
final class PayuSignature
{
    /**
     * Calculates the SHA-512 hash required for PayU hosted payment form submission.
     *
     * Formula: sha512(key|txnid|amount|productinfo|firstname|email|udf1|udf2|udf3|udf4|udf5||||||SALT)
     */
    public function generateRequestHash(
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
    ): string {
        $sequence = sprintf(
            '%s|%s|%s|%s|%s|%s|%s|%s|%s|%s|%s||||||%s',
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
            $salt,
        );

        return strtolower(hash('sha512', $sequence));
    }

    /**
     * Verifies the reverse hash sent by PayU upon payment return / callback.
     *
     * Without additionalCharges:
     *   sha512(SALT|status||||||udf5|udf4|udf3|udf2|udf1|email|firstname|productinfo|amount|txnid|key)
     * With additionalCharges:
     *   sha512(additionalCharges|SALT|status||||||udf5|udf4|udf3|udf2|udf1|email|firstname|productinfo|amount|txnid|key)
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

        $additionalCharges = $params['additionalCharges'] ?? null;

        if ($additionalCharges !== null && $additionalCharges !== '') {
            $sequence = sprintf(
                '%s|%s|%s||||||%s|%s|%s|%s|%s|%s|%s|%s|%s|%s|%s',
                $additionalCharges,
                $salt,
                $status,
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
        } else {
            $sequence = sprintf(
                '%s|%s||||||%s|%s|%s|%s|%s|%s|%s|%s|%s|%s|%s',
                $salt,
                $status,
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
        }

        $calculated = strtolower(hash('sha512', $sequence));

        return hash_equals($calculated, strtolower($hashToVerify));
    }

    /**
     * Calculates the SHA-512 hash for PayU web service command APIs (verify_payment, cancel_refund_transaction).
     *
     * Formula: sha512(key|command|var1|salt)
     */
    public function generateCommandHash(string $key, string $command, string $var1, string $salt): string
    {
        $sequence = "{$key}|{$command}|{$var1}|{$salt}";

        return strtolower(hash('sha512', $sequence));
    }
}
