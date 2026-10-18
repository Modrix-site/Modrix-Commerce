<?php

namespace Tests\Unit;

spl_autoload_register(static function (string $class): void {
    $prefix = 'EcoModular\\Tap\\';

    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $path = dirname(__DIR__, 2).'/modules/ecomodular/tap/src/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';

    if (is_file($path)) {
        require $path;
    }
});

use EcoModular\Tap\TapEndpoint;
use PHPUnit\Framework\TestCase;

class TapEndpointTest extends TestCase
{
    public function test_payment_urls_must_be_https_without_embedded_credentials(): void
    {
        $this->assertSame('https://api.tap.company', TapEndpoint::apiBase());
        $this->assertTrue(TapEndpoint::isPaymentUrl('https://checkout.tap.company/?mode=page&token=abc'));
        $this->assertTrue(TapEndpoint::isPaymentUrl('https://www.knet.com.kw/pay?ref=1'));
        $this->assertFalse(TapEndpoint::isPaymentUrl('http://checkout.tap.company/pay'));
        $this->assertFalse(TapEndpoint::isPaymentUrl('https://user:pass@checkout.tap.company/pay'));
        $this->assertFalse(TapEndpoint::isPaymentUrl('https://checkout.tap.company:444/pay'));
    }
}
