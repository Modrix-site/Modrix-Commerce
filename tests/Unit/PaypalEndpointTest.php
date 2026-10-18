<?php

namespace Tests\Unit;

spl_autoload_register(static function (string $class): void {
    $prefix = 'EcoModular\\Paypal\\';

    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $path = dirname(__DIR__, 2).'/modules/ecomodular/paypal/src/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';

    if (is_file($path)) {
        require $path;
    }
});

use EcoModular\Paypal\PaypalEndpoint;
use PHPUnit\Framework\TestCase;

class PaypalEndpointTest extends TestCase
{
    public function test_approval_urls_are_limited_to_paypal(): void
    {
        $this->assertTrue(PaypalEndpoint::isApprovalUrl('https://www.sandbox.paypal.com/checkoutnow?token=5O190127TN364715T'));
        $this->assertTrue(PaypalEndpoint::isApprovalUrl('https://www.paypal.com/checkoutnow?token=5O190127TN364715T'));
        $this->assertFalse(PaypalEndpoint::isApprovalUrl('http://www.paypal.com/checkoutnow?token=1'));
        $this->assertFalse(PaypalEndpoint::isApprovalUrl('https://www.paypal.com.evil.test/checkoutnow'));
        $this->assertFalse(PaypalEndpoint::isApprovalUrl('https://user:pass@www.paypal.com/checkoutnow'));
    }

    public function test_webhook_certificate_urls_are_limited_to_paypal_api_hosts(): void
    {
        $this->assertTrue(PaypalEndpoint::isCertificateUrl('https://api.sandbox.paypal.com/v1/notifications/certs/CERT-360caa42-fca2a594-ab66f33d'));
        $this->assertTrue(PaypalEndpoint::isCertificateUrl('https://api-m.paypal.com/v1/notifications/certs/CERT-1'));
        $this->assertFalse(PaypalEndpoint::isCertificateUrl('https://api.sandbox.paypal.com/v1/oauth2/token'));
        $this->assertFalse(PaypalEndpoint::isCertificateUrl('https://evil.example/v1/notifications/certs/CERT-1'));
        $this->assertSame('https://api-m.sandbox.paypal.com', PaypalEndpoint::apiBase(true));
        $this->assertSame('https://api-m.paypal.com', PaypalEndpoint::apiBase(false));
    }
}
