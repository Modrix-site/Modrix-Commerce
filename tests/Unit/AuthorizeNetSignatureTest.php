<?php

namespace Tests\Unit;

spl_autoload_register(static function (string $class): void {
    $prefix = 'EcoModular\\AuthorizeNet\\';

    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $path = dirname(__DIR__, 2).'/modules/modrixcommerce/authorize-net/src/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';

    if (is_file($path)) {
        require $path;
    }
});

use EcoModular\AuthorizeNet\AuthorizeNetSignature;
use PHPUnit\Framework\TestCase;

class AuthorizeNetSignatureTest extends TestCase
{
    public function test_webhook_hash_uses_the_hex_decoded_signature_key(): void
    {
        $key = str_repeat('ab', 32);
        $body = '{"notificationId":"d0e8e7fe-c3e7-4add-a480-27bc5ce28a18","eventType":"net.authorize.payment.authcapture.created"}';
        $hash = hash_hmac('sha512', $body, hex2bin($key));

        $this->assertTrue(AuthorizeNetSignature::matches($body, 'sha512='.$hash, $key));
        $this->assertTrue(AuthorizeNetSignature::matches($body, 'SHA512='.strtoupper($hash), $key));
        $this->assertFalse(AuthorizeNetSignature::matches($body, 'sha512='.hash('sha512', $body), $key));
        $this->assertFalse(AuthorizeNetSignature::matches($body.' ', 'sha512='.$hash, $key));
        $this->assertFalse(AuthorizeNetSignature::matches($body, 'sha512='.$hash, str_repeat('ab', 16)));
        $this->assertFalse(AuthorizeNetSignature::matches($body, $hash, $key));
    }
}
