<?php

namespace Tests\Unit;

spl_autoload_register(static function (string $class): void {
    $prefix = 'EcoModular\\Tap\\';

    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $path = dirname(__DIR__, 2).'/modules/modrixcommerce/tap/src/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';

    if (is_file($path)) {
        require $path;
    }
});

use EcoModular\Tap\TapSignature;
use PHPUnit\Framework\TestCase;

class TapSignatureTest extends TestCase
{
    public function test_hashstring_uses_the_posted_charge_fields_and_the_secret_key(): void
    {
        $secret = 'sk_test_examplekey1234567890';
        $charge = [
            'id' => 'chg_TS05A4120230736x9K22710693',
            'amount' => 1,
            'currency' => 'SAR',
            'status' => 'CAPTURED',
            'reference' => ['gateway' => 'mada_pg', 'payment' => '4327230736106619650'],
            'transaction' => ['created' => '1698392202943'],
        ];
        $string = 'x_idchg_TS05A4120230736x9K22710693x_amount1.00x_currencySARx_gateway_referencemada_pgx_payment_reference4327230736106619650x_statusCAPTUREDx_created1698392202943';
        $hash = hash_hmac('sha256', $string, $secret);

        $this->assertTrue(TapSignature::matches($charge, $hash, $secret));
        $this->assertTrue(TapSignature::matches($charge, strtoupper($hash), $secret));
        $this->assertFalse(TapSignature::matches($charge, hash('sha256', $string), $secret));
        $this->assertFalse(TapSignature::matches($charge, $hash, 'sk_test_differentkey1234567890'));
    }
}
