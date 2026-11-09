<?php

namespace App\Core\Installation;

use RuntimeException;

/** A setup problem the merchant can fix; the message is safe to show. */
final class InstallerException extends RuntimeException {}
