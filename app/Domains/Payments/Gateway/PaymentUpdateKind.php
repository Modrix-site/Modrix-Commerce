<?php

namespace App\Domains\Payments\Gateway;

enum PaymentUpdateKind: string
{
    /** The session is still open; nothing to apply. */
    case Pending = 'pending';

    /** Completed, but the funds are not confirmed yet (e.g. bank debits). */
    case Processing = 'processing';

    case Succeeded = 'succeeded';

    case Failed = 'failed';

    case Expired = 'expired';
}
