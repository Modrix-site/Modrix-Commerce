<?php

namespace Tests\Unit;

use App\Core\Audit\AuditLogger;
use PHPUnit\Framework\TestCase;

class AuditRedactionTest extends TestCase
{
    public function test_redacts_sensitive_keys_recursively_and_keeps_others()
    {
        $redacted = AuditLogger::redact([
            'email' => 'a@example.com',
            'password_confirmation' => 'x',
            'two_factor_secret' => 'y',
            'stripe' => ['webhook_secret' => 'whsec', 'mode' => 'test'],
            'list' => [['token' => 't', 'id' => 5]],
            'company_name' => 'Acme',
        ]);

        $this->assertSame('a@example.com', $redacted['email']);
        $this->assertSame('[REDACTED]', $redacted['password_confirmation']);
        $this->assertSame('[REDACTED]', $redacted['two_factor_secret']);
        $this->assertSame('[REDACTED]', $redacted['stripe']['webhook_secret']);
        $this->assertSame('test', $redacted['stripe']['mode']);
        $this->assertSame('[REDACTED]', $redacted['list'][0]['token']);
        $this->assertSame(5, $redacted['list'][0]['id']);
        $this->assertSame('Acme', $redacted['company_name']);
    }
}
