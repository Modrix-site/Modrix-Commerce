<?php

namespace EcoModular\AuthorizeNet;

use App\Domains\Payments\Gateway\PaymentUpdateKind;

/**
 * Transaction status values from getTransactionDetails.
 *
 * @see https://developer.authorize.net/api/reference/index.html#transaction-reporting-get-transaction-details
 */
final class AuthorizeNetTransaction
{
    public static function kind(string $status): PaymentUpdateKind
    {
        return match ($status) {
            'capturedPendingSettlement', 'settledSuccessfully' => PaymentUpdateKind::Succeeded,
            'authorizedPendingCapture', 'FDSPendingReview', 'FDSAuthorizedPendingReview', 'underReview', 'approvedReview' => PaymentUpdateKind::Processing,
            'declined', 'failedReview', 'voided', 'expired', 'generalError', 'communicationError', 'settlementError', 'returnedItem', 'couldNotVoid' => PaymentUpdateKind::Failed,
            default => PaymentUpdateKind::Pending,
        };
    }

    public static function lastFour(?string $accountNumber): ?string
    {
        if ($accountNumber === null || preg_match('/(\d{4})$/', $accountNumber, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }
}
