<?php

namespace Tests\Unit;

use Marketplace\Services\Signing;
use PHPUnit\Framework\TestCase;

/**
 * Stores verify marketplace answers with the store platform's
 * App\Core\Marketplace\Signing. The expected values below were produced by
 * that class (same secret, same data); Ed25519 is deterministic, so this
 * copy must reproduce them exactly.
 */
class SigningCompatibilityTest extends TestCase
{
    private const SECRET = 'BwcHBwcHBwcHBwcHBwcHBwcHBwcHBwcHBwcHBwcHBwfqSmxj4pxSCr71UHsTLsX5lUd2rr6+e5JCHuppFEbSLA==';

    private const PUBLIC = '6kpsY+KcUgq+9VB7Ey7F+ZVHdq6+vnuSQh7qaRRG0iw=';

    private const SIGNATURE = 'IFUMwVZfjJIJdOT7Q/yJgrpZIJNa/ESwilNU1D7I+jgyZQnUkKcAgbdfOxU30filP0Yy6Cc2R94yJCbGjHGmDA==';

    private const DATA = ['schema' => 1, 'license_id' => 7, 'product' => 'acme-reviews', 'extension' => 'acme/reviews', 'nested' => ['b' => 2, 'a' => 'é/x'], 'key_id' => 'fe812c12f3ab4ce6'];

    public function test_signatures_match_the_store_platform()
    {
        $this->assertSame(self::SIGNATURE, Signing::sign(self::DATA, self::SECRET));
        $this->assertSame(self::PUBLIC, Signing::publicKeyFromSecret(self::SECRET));
        $this->assertTrue(Signing::verify(self::DATA, self::SIGNATURE, [self::PUBLIC]));
    }

    public function test_key_order_does_not_change_the_signature()
    {
        $reordered = array_reverse(self::DATA, true);
        $reordered['nested'] = ['a' => 'é/x', 'b' => 2];

        $this->assertSame(self::SIGNATURE, Signing::sign($reordered, self::SECRET));
    }
}
