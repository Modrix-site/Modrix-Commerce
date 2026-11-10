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

use EcoModular\AuthorizeNet\AuthorizeNetEndpoint;
use PHPUnit\Framework\TestCase;

class AuthorizeNetEndpointTest extends TestCase
{
    public function test_api_and_payment_form_urls_are_the_official_hosts(): void
    {
        $this->assertSame('https://apitest.authorize.net/xml/v1/request.api', AuthorizeNetEndpoint::apiUrl(true));
        $this->assertSame('https://api.authorize.net/xml/v1/request.api', AuthorizeNetEndpoint::apiUrl(false));
        $this->assertSame('https://test.authorize.net/payment/payment', AuthorizeNetEndpoint::formUrl(true));
        $this->assertSame('https://accept.authorize.net/payment/payment', AuthorizeNetEndpoint::formUrl(false));
    }

    public function test_payment_form_urls_reject_lookalike_hosts(): void
    {
        $this->assertTrue(AuthorizeNetEndpoint::isFormUrl('https://test.authorize.net/payment/payment'));
        $this->assertTrue(AuthorizeNetEndpoint::isFormUrl('https://accept.authorize.net/payment/payment'));
        $this->assertFalse(AuthorizeNetEndpoint::isFormUrl('http://test.authorize.net/payment/payment'));
        $this->assertFalse(AuthorizeNetEndpoint::isFormUrl('https://test.authorize.net.evil.test/payment/payment'));
        $this->assertFalse(AuthorizeNetEndpoint::isFormUrl('https://user:pass@test.authorize.net/payment/payment'));
        $this->assertFalse(AuthorizeNetEndpoint::isFormUrl('https://test.authorize.net/payment/other'));
    }
}
