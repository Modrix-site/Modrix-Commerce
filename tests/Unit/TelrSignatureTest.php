<?php

namespace Tests\Unit;

spl_autoload_register(static function (string $class): void {
    $prefix = 'EcoModular\\Telr\\';

    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $path = dirname(__DIR__, 2).'/modules/ecomodular/telr/src/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';

    if (is_file($path)) {
        require $path;
    }
});

use EcoModular\Telr\TelrSignature;
use PHPUnit\Framework\TestCase;

class TelrSignatureTest extends TestCase
{
    /**
     * @return array<string, string>
     */
    private function fields(): array
    {
        return [
            'tran_store' => '1234',
            'tran_type' => 'sale',
            'tran_class' => 'ecom',
            'tran_test' => '1',
            'tran_ref' => '040012345678',
            'tran_prevref' => '',
            'tran_firstref' => '040012345678',
            'tran_currency' => 'AED',
            'tran_amount' => '10.50',
            'tran_cartid' => 'p15',
            'tran_desc' => 'Order 1001',
            'tran_status' => 'A',
            'tran_authcode' => '123456',
            'tran_authmessage' => 'Authorised',
        ];
    }

    public function test_hash_uses_the_documented_field_order(): void
    {
        $fields = $this->fields();
        $expected = sha1(implode(':', [
            's3cret',
            $fields['tran_store'],
            $fields['tran_type'],
            $fields['tran_class'],
            $fields['tran_test'],
            $fields['tran_ref'],
            $fields['tran_prevref'],
            $fields['tran_firstref'],
            $fields['tran_currency'],
            $fields['tran_amount'],
            $fields['tran_cartid'],
            $fields['tran_desc'],
            $fields['tran_status'],
            $fields['tran_authcode'],
            $fields['tran_authmessage'],
        ]));

        $this->assertSame($expected, TelrSignature::hash($fields, 's3cret'));
        $this->assertTrue(TelrSignature::matches([...$fields, 'tran_check' => strtoupper($expected)], 's3cret'));
    }

    public function test_tran_order_is_inserted_before_the_currency_when_present(): void
    {
        $fields = [...$this->fields(), 'tran_order' => 'OrderRef'];
        $expected = sha1(implode(':', [
            's3cret',
            $fields['tran_store'],
            $fields['tran_type'],
            $fields['tran_class'],
            $fields['tran_test'],
            $fields['tran_ref'],
            $fields['tran_prevref'],
            $fields['tran_firstref'],
            'OrderRef',
            $fields['tran_currency'],
            $fields['tran_amount'],
            $fields['tran_cartid'],
            $fields['tran_desc'],
            $fields['tran_status'],
            $fields['tran_authcode'],
            $fields['tran_authmessage'],
        ]));

        $this->assertSame($expected, TelrSignature::hash($fields, 's3cret'));
    }

    public function test_a_changed_amount_fails_the_check(): void
    {
        $fields = $this->fields();
        $fields['tran_check'] = TelrSignature::hash($fields, 's3cret');
        $fields['tran_amount'] = '10.51';

        $this->assertFalse(TelrSignature::matches($fields, 's3cret'));
        $this->assertFalse(TelrSignature::matches($this->fields(), 's3cret'));
    }
}
