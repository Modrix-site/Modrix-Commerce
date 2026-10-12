<?php

namespace App\Domains\Payments\Gateway;

use RuntimeException;

/**
 * A webhook failed signature or format verification and must be discarded.
 */
final class InvalidWebhook extends RuntimeException {}
