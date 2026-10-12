<?php

namespace Tests\Unit;

use App\Core\Settings\Countries;
use PHPUnit\Framework\TestCase;

class CountriesTest extends TestCase
{
    public function test_list_contains_iso_countries_only()
    {
        $codes = Countries::codes();

        foreach (['US', 'PH', 'DE', 'JP', 'BR'] as $code) {
            $this->assertContains($code, $codes);
        }

        foreach (['EU', 'UN', 'ZZ', 'AC', 'IC', 'EA', 'DG'] as $code) {
            $this->assertNotContains($code, $codes);
        }

        $this->assertGreaterThan(240, count($codes));
        $this->assertSame('Philippines', Countries::name('ph'));
    }
}
