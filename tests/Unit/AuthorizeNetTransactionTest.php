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

use App\Domains\Payments\Gateway\PaymentUpdateKind;
use EcoModular\AuthorizeNet\AuthorizeNetTransaction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AuthorizeNetTransactionTest extends TestCase
{
    /**
     * @return array<string, array{string, PaymentUpdateKind}>
     */
    public static function statuses(): array
    {
        return [
            'captured' => ['capturedPendingSettlement', PaymentUpdateKind::Succeeded],
            'settled' => ['settledSuccessfully', PaymentUpdateKind::Succeeded],
            'held' => ['FDSPendingReview', PaymentUpdateKind::Processing],
            'review' => ['underReview', PaymentUpdateKind::Processing],
            'declined' => ['declined', PaymentUpdateKind::Failed],
            'voided' => ['voided', PaymentUpdateKind::Failed],
            'unknown' => ['pendingSettlement', PaymentUpdateKind::Pending],
        ];
    }

    #[DataProvider('statuses')]
    public function test_transaction_status_maps_to_a_payment_fact(string $status, PaymentUpdateKind $kind): void
    {
        $this->assertSame($kind, AuthorizeNetTransaction::kind($status));
    }

    public function test_card_last_four_is_taken_from_the_masked_account_number(): void
    {
        $this->assertSame('4242', AuthorizeNetTransaction::lastFour('XXXX4242'));
        $this->assertSame('0015', AuthorizeNetTransaction::lastFour('0015'));
        $this->assertNull(AuthorizeNetTransaction::lastFour('XXXX'));
        $this->assertNull(AuthorizeNetTransaction::lastFour(null));
    }
}
