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

use EcoModular\CheckoutCom\CheckoutComEndpoint;
use PHPUnit\Framework\TestCase;

class CheckoutComEndpointTest extends TestCase
{
    public function test_classic_and_prefixed_api_hosts_are_accepted(): void
    {
        $this->assertTrue(CheckoutComEndpoint::isApiBase('https://api.sandbox.checkout.com'));
        $this->assertTrue(CheckoutComEndpoint::isApiBase('https://api.checkout.com/'));
        $this->assertTrue(CheckoutComEndpoint::isApiBase('https://abc.api.sandbox.checkout.com'));
        $this->assertTrue(CheckoutComEndpoint::isSandboxHost('https://api.sandbox.checkout.com'));
        $this->assertFalse(CheckoutComEndpoint::isSandboxHost('https://api.checkout.com'));
    }

    public function test_other_urls_are_refused(): void
    {
        $this->assertFalse(CheckoutComEndpoint::isApiBase('http://api.sandbox.checkout.com'));
        $this->assertFalse(CheckoutComEndpoint::isApiBase('https://user:pass@api.checkout.com'));
        $this->assertFalse(CheckoutComEndpoint::isApiBase('https://api.checkout.com/payments'));
        $this->assertFalse(CheckoutComEndpoint::isApiBase('https://api.checkout.com?x=1'));
        $this->assertFalse(CheckoutComEndpoint::isApiBase('https://evil.example'));
        $this->assertTrue(CheckoutComEndpoint::isCheckoutHost('https://pay.sandbox.checkout.com/page'));
        $this->assertFalse(CheckoutComEndpoint::isCheckoutHost('https://pay.checkout.com.evil.test/page'));
    }
}
