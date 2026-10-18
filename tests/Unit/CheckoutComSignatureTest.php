<?php

namespace Tests\Unit;

spl_autoload_register(static function (string $class): void {
    $prefix = 'EcoModular\\CheckoutCom\\';

    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $path = dirname(__DIR__, 2).'/modules/ecomodular/checkout-com/src/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';

    if (is_file($path)) {
        require $path;
    }
});

use EcoModular\CheckoutCom\CheckoutComSignature;
use PHPUnit\Framework\TestCase;

class CheckoutComSignatureTest extends TestCase
{
    public function test_hex_hmac_of_the_raw_body_matches(): void
    {
        $body = '{"id":"evt_1","type":"payment_captured"}';
        $secret = 'signature-key';
        $header = hash_hmac('sha256', $body, $secret);

        $this->assertTrue(CheckoutComSignature::matches($body, $secret, $header));
        $this->assertTrue(CheckoutComSignature::matches($body, $secret, strtoupper($header)));
    }

    public function test_a_changed_body_or_key_is_rejected(): void
    {
        $header = hash_hmac('sha256', '{"id":"evt_1"}', 'signature-key');

        $this->assertFalse(CheckoutComSignature::matches('{"id":"evt_2"}', 'signature-key', $header));
        $this->assertFalse(CheckoutComSignature::matches('{"id":"evt_1"}', 'other-key', $header));
        $this->assertFalse(CheckoutComSignature::matches('{"id":"evt_1"}', '', $header));
        $this->assertFalse(CheckoutComSignature::matches('{"id":"evt_1"}', 'signature-key', 'not-a-signature'));
    }
}
